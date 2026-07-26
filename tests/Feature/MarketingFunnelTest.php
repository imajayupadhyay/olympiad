<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Coupon;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\Subject;
use App\Models\User;
use App\Services\CouponService;
use App\Services\EnrollmentService;
use App\Services\MarketingFunnelService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingFunnelTest extends TestCase
{
    use RefreshDatabase;

    private ClassLevel $classLevel;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classLevel = ClassLevel::create([
            'level' => 8, 'label' => 'Class 8', 'is_active' => true, 'sort_order' => 8,
        ]);

        $this->subject = Subject::create([
            'name' => 'Mathematics', 'slug' => 'mathematics', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function exam(array $overrides = []): Exam
    {
        static $n = 0;
        $n++;

        return Exam::create(array_merge([
            'subject_id'       => $this->subject->id,
            'class_level_id'   => $this->classLevel->id,
            'name'             => 'National Mathematics Olympiad',
            'slug'             => 'nmo-'.$n,
            'exam_code'        => 'NMO'.(1000 + $n),
            'starts_at'        => now()->addWeek(),
            'ends_at'          => now()->addWeeks(2),
            'duration_minutes' => 60,
            'fee_amount'       => 250,
            'fee_currency'     => 'INR',
            'status'           => 'published',
            'published_at'     => now(),
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Aarav Mehta',
            'email'          => 'aarav@example.com',
            'phone'          => '9876543210',
            'class_level_id' => $this->classLevel->id,
            'exam_ids'       => [],
        ], $overrides);
    }

    /**
     * Keep the suite off the live Razorpay API: everything up to the gateway call
     * runs for real, and openOrder() fails the way an unreachable gateway would.
     * That also exercises the funnel's "account created, payment outstanding" path.
     */
    private function fakeGatewayFailure(): void
    {
        $this->app->bind(PaymentService::class, fn ($app) => new class(
            $app->make(EnrollmentService::class),
            $app->make(CouponService::class),
        ) extends PaymentService {
            public function openOrder(Payment $payment): array
            {
                throw new \RuntimeException('gateway offline');
            }
        });
    }

    public function test_landing_page_lists_published_exams_and_class_levels(): void
    {
        $published = $this->exam();
        $this->exam(['status' => 'draft', 'name' => 'Hidden Draft']);

        $this->get('/marketing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Marketing/Index')
                ->has('exams', 1)
                ->where('exams.0.id', $published->id)
                ->has('classLevels', 1)
            );
    }

    public function test_landing_page_is_indexable(): void
    {
        $response = $this->get('/marketing');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function test_free_selection_registers_and_enrols_without_the_gateway(): void
    {
        Queue::fake();
        $exam = $this->exam(['fee_amount' => 0]);

        $response = $this->postJson(route('marketing.register'), $this->payload([
            'exam_ids' => [$exam->id],
        ]));

        $response->assertOk()->assertJson(['status' => 'free']);

        $user = User::where('email', 'aarav@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('student', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->password_changed_at, 'Student should still be on the emailed password.');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('exam_enrollments', [
            'user_id' => $user->id, 'exam_id' => $exam->id, 'status' => 'enrolled',
        ]);
        $this->assertSame(0, Payment::count());
    }

    public function test_registration_settles_every_calculation_without_touching_the_gateway(): void
    {
        // No gateway fake needed: registration must not reach Razorpay at all. If it
        // did, this test would hit the live API and fail.
        $exam = $this->exam(['fee_amount' => 250]);

        $response = $this->postJson(route('marketing.register'), $this->payload([
            'exam_ids' => [$exam->id],
        ]));

        $response->assertOk()->assertJson([
            'status'   => 'ready',
            'gross'    => 250.0,
            'discount' => 0.0,
            'payable'  => 250.0,
            'currency' => 'INR',
        ]);
        $response->assertJsonPath('items.0.name', $exam->name);

        $user = User::where('email', 'aarav@example.com')->first();
        $payment = Payment::where('user_id', $user->id)->first();

        $this->assertSame('created', $payment->status);
        $this->assertSame([$exam->id], $payment->notes['exam_ids']);
        // The order is only created when the Pay button asks for it.
        $this->assertNull($payment->razorpay_order_id);

        // Nothing is enrolled until the money lands.
        $this->assertSame(0, ExamEnrollment::count());
    }

    public function test_the_pay_step_reports_a_gateway_outage_without_losing_the_signup(): void
    {
        $exam = $this->exam(['fee_amount' => 250]);
        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]))->assertOk();

        $user = User::where('email', 'aarav@example.com')->first();
        $payment = Payment::where('user_id', $user->id)->first();

        $this->fakeGatewayFailure();

        $this->actingAs($user)
            ->postJson(route('marketing.payment.order', $payment))
            ->assertOk()
            ->assertJson(['status' => 'failed']);

        // Account and priced cart both survive so the student can retry.
        $this->assertSame('created', $payment->refresh()->status);
        $this->assertSame(250.0, (float) $payment->amount);
    }

    public function test_registration_queues_the_welcome_email(): void
    {
        Queue::fake();
        $exam = $this->exam(['fee_amount' => 0]);

        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]));

        $user = User::where('email', 'aarav@example.com')->first();
        $this->assertDatabaseHas('email_logs', [
            'template_key' => 'student_registered',
            'related_id'   => $user->id,
        ]);
    }

    public function test_a_referral_link_attributes_the_signup_and_discounts_the_cart(): void
    {
        $this->activeReferralProgram();

        $referrer = User::factory()->create(['is_active' => true]);
        $exam = $this->exam(['fee_amount' => 500]);

        // Referrals are link-only, exactly as on /register — landing on the link is
        // what carries the code, and the page confirms who referred you.
        $this->get('/marketing?ref='.$referrer->referral_code)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('referredBy.name', explode(' ', $referrer->name)[0])
                ->where('referredBy.label', '10% off (up to ₹50)')
            );

        $this->postJson(route('marketing.register'), $this->payload([
            'exam_ids' => [$exam->id],
        ]))->assertOk();

        $referee = User::where('email', 'aarav@example.com')->first();
        $this->assertSame($referrer->id, $referee->referred_by);

        $referral = Referral::where('referee_id', $referee->id)->first();
        $this->assertNotNull($referral);
        $this->assertNotNull($referral->referee_welcome_coupon_id);

        // The welcome coupon is auto-applied to the funnel's payment.
        $payment = Payment::where('user_id', $referee->id)->first();
        $this->assertSame($referral->referee_welcome_coupon_id, $payment->coupon_id);
        $this->assertSame(50.0, (float) $payment->discount_amount);   // 10% of 500, capped at 50
        $this->assertSame(450.0, (float) $payment->amount);
    }

    public function test_an_unknown_referral_link_is_ignored_but_registration_still_succeeds(): void
    {
        $this->activeReferralProgram();
        $exam = $this->exam(['fee_amount' => 0]);

        $this->get('/marketing?ref=NOPENOPE')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('referredBy', null));

        $this->postJson(route('marketing.register'), $this->payload([
            'exam_ids' => [$exam->id],
        ]))->assertOk()->assertJson(['status' => 'free']);

        $referee = User::where('email', 'aarav@example.com')->first();
        $this->assertNotNull($referee);
        $this->assertNull($referee->referred_by);
        $this->assertSame(0, Referral::count());
        $this->assertSame(0, Coupon::count());
    }

    public function test_validation_rejects_an_incomplete_submission(): void
    {
        $this->postJson(route('marketing.register'), [
            'name' => '', 'email' => 'not-an-email', 'phone' => '123', 'class_level_id' => '', 'exam_ids' => [],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['name', 'email', 'phone', 'class_level_id', 'exam_ids']);

        $this->assertSame(0, User::count());
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'aarav@example.com']);
        $exam = $this->exam(['fee_amount' => 0]);

        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_an_exam_from_another_class_cannot_be_smuggled_in(): void
    {
        $otherClass = ClassLevel::create(['level' => 3, 'label' => 'Class 3', 'is_active' => true, 'sort_order' => 3]);
        $foreign = $this->exam(['class_level_id' => $otherClass->id, 'fee_amount' => 0]);

        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$foreign->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exam_ids']);

        $this->assertSame(0, User::count(), 'A rejected selection must not leave an account behind.');
    }

    public function test_payment_endpoints_reject_another_students_payment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $owner->id, 'amount' => 250, 'gross_amount' => 250, 'discount_amount' => 0,
            'currency' => 'INR', 'status' => 'created', 'gateway' => 'razorpay', 'notes' => ['exam_ids' => []],
        ]);

        $this->actingAs($intruder)
            ->postJson(route('marketing.payment.order', $payment))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('marketing.payment.verify', $payment), [
                'razorpay_payment_id' => 'pay_x', 'razorpay_order_id' => 'order_x', 'razorpay_signature' => 'sig_x',
            ])->assertForbidden();
    }

    public function test_verified_marketing_payment_exposes_a_dynamic_inr_purchase_event(): void
    {
        $student = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $student->id,
            'amount' => 269.10,
            'gross_amount' => 299,
            'discount_amount' => 29.90,
            'currency' => 'INR',
            'status' => 'paid',
            'gateway' => 'razorpay',
            'source' => 'marketing',
            'razorpay_order_id' => 'order_verified',
            'razorpay_payment_id' => 'pay_verified',
            'notes' => ['exam_ids' => []],
            'paid_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('marketing.payment.verify', $payment), [
                'razorpay_payment_id' => 'pay_verified',
                'razorpay_order_id' => 'order_verified',
                'razorpay_signature' => 'already_verified',
            ])
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('meta_purchase', fn (array $event) =>
                $event['event_id'] === 'marketing_purchase_'.$payment->id
                && $event['payment_id'] === $payment->id
                && $event['value'] === 269.10
                && $event['currency'] === 'INR'
            );
    }

    public function test_the_referral_banner_exposes_only_the_referrers_first_name(): void
    {
        $this->activeReferralProgram();
        $referrer = User::factory()->create(['name' => 'Rahul Sharma', 'is_active' => true]);

        $response = $this->get('/marketing?ref='.$referrer->referral_code);

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('referredBy.name', 'Rahul')
            ->missing('referredBy.email')
            ->missing('referredBy.id')
        );
        $response->assertDontSee($referrer->email, false);
    }

    public function test_no_referral_banner_or_card_when_the_program_is_off(): void
    {
        ReferralSetting::current()->update(['is_active' => false]);
        $referrer = User::factory()->create(['is_active' => true]);

        $this->get('/marketing?ref='.$referrer->referral_code)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('referredBy', null)
                ->where('referral', null)
                ->where('program', null)
            );
    }

    public function test_the_refer_and_earn_card_shows_without_any_referral_link(): void
    {
        $this->activeReferralProgram();

        // The card is always present while the program runs — same as the wizard's
        // .ref-share block — even though there is no referrer to credit.
        $this->get('/marketing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('referredBy', null)
                ->where('program.welcome', '10% off (up to ₹50)')
                ->where('program.reward', '₹100 off')
                ->where('program.threshold', 1)
                ->where('program.mode', 'registration')
            );
    }

    public function test_registering_returns_the_new_students_own_share_state(): void
    {
        $this->activeReferralProgram();
        $exam = $this->exam(['fee_amount' => 250]);

        $response = $this->postJson(route('marketing.register'), $this->payload([
            'exam_ids' => [$exam->id],
        ]))->assertOk();

        $user = User::where('email', 'aarav@example.com')->first();

        // The card upgrades to a real link the moment the account exists.
        $response->assertJsonPath('referral.code', $user->referral_code);
        $response->assertJsonPath('referral.link', $user->referralLink());
        $response->assertJsonPath('referral.stats.threshold', 1);
    }

    public function test_a_marketing_referral_shows_up_in_the_admin_referral_module(): void
    {
        $this->activeReferralProgram();
        $referrer = User::factory()->create(['is_active' => true]);
        $exam = $this->exam(['fee_amount' => 0]);

        $this->get('/marketing?ref='.$referrer->referral_code);
        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]))->assertOk();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.referrals'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.referee.email', 'aarav@example.com')
                ->where('referrals.data.0.referrer.code', $referrer->referral_code)
                ->where('totals.all', 1)
            );
    }

    public function test_the_funnel_service_exposes_the_referee_discount_rule(): void
    {
        $this->activeReferralProgram();

        $rule = app(MarketingFunnelService::class)->refereePreviewRule();

        $this->assertSame('percentage', $rule['type']);
        $this->assertSame(10.0, $rule['value']);
        $this->assertSame(50.0, $rule['max_discount']);

        ReferralSetting::current()->update(['is_active' => false]);
        $this->assertNull(app(MarketingFunnelService::class)->refereePreviewRule());
    }

    /* ── admin attribution: a marketing signup must be identifiable everywhere ── */

    public function test_a_marketing_signup_is_tagged_on_the_account_the_payment_and_the_enrolment(): void
    {
        $free = $this->exam(['fee_amount' => 0]);
        $paid = $this->exam(['fee_amount' => 250]);

        $this->postJson(route('marketing.register'), $this->payload([
            'exam_ids' => [$free->id, $paid->id],
        ]))->assertOk();

        $user = User::where('email', 'aarav@example.com')->first();

        $this->assertSame('marketing', $user->registration_source);
        $this->assertSame('marketing', Payment::where('user_id', $user->id)->first()->source);
        $this->assertSame('marketing', ExamEnrollment::where('user_id', $user->id)->first()->enrollment_source);
    }

    public function test_the_other_signup_paths_keep_their_own_source(): void
    {
        $this->post('/register', [
            'name' => 'Website Student', 'email' => 'web@example.com', 'class_level_id' => $this->classLevel->id,
        ]);
        $this->assertSame('website', User::where('email', 'web@example.com')->first()->registration_source);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Admin Student', 'email' => 'byadmin@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'class_level_id' => $this->classLevel->id, 'is_active' => true,
        ]);
        $this->assertSame('admin', User::where('email', 'byadmin@example.com')->first()->registration_source);
    }

    public function test_admin_students_list_filters_and_counts_by_source(): void
    {
        $this->exam(['fee_amount' => 0]);
        $exam = Exam::first();
        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]));

        $website = User::factory()->create(['role' => 'student', 'registration_source' => 'website']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.users.index', ['source' => 'marketing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.email', 'aarav@example.com')
                ->where('totals.marketing', 1)
            );

        // The website filter must also catch legacy accounts with a null source.
        User::where('id', $website->id)->update(['registration_source' => null]);
        $this->actingAs($admin)->get(route('admin.users.index', ['source' => 'website']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('students.data', 1));
    }

    public function test_admin_payments_list_filters_by_source_and_totals_marketing_revenue(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student']);

        $base = [
            'user_id' => $student->id, 'gross_amount' => 500, 'discount_amount' => 0,
            'currency' => 'INR', 'status' => 'paid', 'gateway' => 'razorpay',
            'notes' => ['exam_ids' => []], 'paid_at' => now(),
        ];
        Payment::create($base + ['amount' => 500, 'source' => 'marketing']);
        Payment::create($base + ['amount' => 300, 'source' => 'checkout']);

        $this->actingAs($admin)->get(route('admin.payments', ['source' => 'marketing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('payments.data', 1)
                ->where('payments.data.0.source', 'marketing')
                ->where('payments.data.0.source_label', 'Marketing page')
                // sqlite hands back an int here where MySQL gives a float — compare numerically.
                ->where('totals.marketing', fn ($total) => (float) $total === 500.0)
            );
    }

    public function test_student_report_filters_by_source_and_exposes_it_on_every_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $exam = $this->exam(['fee_amount' => 0]);
        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]));
        User::factory()->create(['role' => 'student', 'registration_source' => 'website']);

        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 2)
                ->has('registrationSources')
            );

        $this->actingAs($admin)->get(route('admin.reports.index', ['registration_source' => 'marketing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.registration_source', 'marketing')
                ->where('students.data.0.registration_source_label', 'Marketing page')
            );
    }

    public function test_report_exports_carry_the_source_column(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $exam = $this->exam(['fee_amount' => 0]);
        $this->postJson(route('marketing.register'), $this->payload(['exam_ids' => [$exam->id]]));

        $xlsx = $this->actingAs($admin)->get(route('admin.reports.excel', ['registration_source' => 'marketing']));
        $xlsx->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            $xlsx->headers->get('content-type'),
            'Excel export must remain a real XLSX workbook.'
        );

        $pdf = $this->actingAs($admin)->get(route('admin.reports.pdf', ['registration_source' => 'marketing']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_an_invalid_source_filter_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['registration_source' => 'facebook']))
            ->assertSessionHasErrors('registration_source');
    }

    private function activeReferralProgram(): void
    {
        ReferralSetting::current()->update([
            'is_active'                => true,
            'referee_discount_type'    => 'percentage',
            'referee_discount_value'   => 10,
            'referee_max_discount'     => 50,
            'referee_min_order_amount' => 0,
            'referrer_reward_type'     => 'fixed',
            'referrer_reward_value'    => 100,
            'unlock_threshold'         => 1,
            'qualify_on'               => 'registration',
        ]);
    }
}
