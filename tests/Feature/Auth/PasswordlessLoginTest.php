<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendLoginOtp;
use App\Models\ClassLevel;
use App\Models\LoginOtpChallenge;
use App\Models\User;
use App\Services\AuthenticationOtpDeliveryService;
use App\Services\LoginOtpService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.brevo.api_key' => 'test-brevo-key',
            'services.brevo.sender_email' => 'login@example.com',
            'services.brevo.sender_name' => 'NEO',
            'services.aisensy.api_key' => 'test-aisensy-key',
            'services.aisensy.campaign_name' => 'neo_student_login_otp',
            'services.auth_otp.pepper' => 'test-only-otp-pepper',
        ]);
    }

    public function test_login_page_exposes_channel_availability_but_no_student_name(): void
    {
        $student = $this->student(['name' => 'Private Student']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee($student->name)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('otpFlow.step', 'identifier')
                ->where('otpFlow.availability.email', true)
                ->where('otpFlow.availability.whatsapp', true));
    }

    public function test_email_request_stores_only_a_peppered_hash_and_dispatches_an_encrypted_job(): void
    {
        Queue::fake();
        $student = $this->student(['name' => 'Aarav Sharma', 'email' => 'aarav@example.com']);

        $response = $this->post(route('login.otp.send'), [
            'identifier' => ' AARAV@example.com ',
            'remember' => true,
        ]);

        $response->assertRedirect()->assertSessionHas('status');
        $challenge = LoginOtpChallenge::sole();

        $this->assertSame('email', $challenge->channel);
        $this->assertSame([$student->id], $challenge->candidate_user_ids);
        $this->assertTrue($challenge->remember);
        $this->assertStringStartsWith('$2y$', $challenge->otp_hash);
        $this->assertArrayNotHasKey('otp', session(LoginOtpService::SESSION_KEY));

        Queue::assertPushed(SendLoginOtp::class, function (SendLoginOtp $job): bool {
            $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
            $this->assertSame('email', $job->channel);
            $this->assertSame('Aarav', $job->recipientName);
            $this->assertMatchesRegularExpression('/^\d{6}$/', $job->code);

            return true;
        });

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->where('otpFlow.step', 'otp')
            ->where('otpFlow.resendIn', fn ($seconds) => $seconds > 0 && $seconds <= 60));
    }

    public function test_database_queue_payload_encrypts_every_sensitive_delivery_value(): void
    {
        app('queue')->connection('database')->push(new SendLoginOtp(
            999,
            'email',
            'private-student@example.com',
            'Private Student',
            '417293',
        ));

        $payload = (string) DB::table('jobs')->value('payload');

        $this->assertStringNotContainsString('417293', $payload);
        $this->assertStringNotContainsString('private-student@example.com', $payload);
        $this->assertStringNotContainsString('Private Student', $payload);
        $this->assertSame(SendLoginOtp::class, json_decode($payload, true)['data']['commandName']);
    }

    public function test_unknown_inactive_and_admin_identifiers_receive_the_same_public_response_without_delivery(): void
    {
        Queue::fake();
        $this->student(['email' => 'inactive@example.com', 'is_active' => false]);
        User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin', 'is_active' => true]);

        foreach (['missing@example.com', 'inactive@example.com', 'admin@example.com'] as $email) {
            $this->withSession([])->post(route('login.otp.send'), ['identifier' => $email])
                ->assertRedirect()
                ->assertSessionHas('status', 'If an active student account matches, a secure login code is on its way.');
        }

        $this->assertCount(3, LoginOtpChallenge::all());
        $this->assertTrue(LoginOtpChallenge::get()->every(fn ($challenge) => $challenge->candidate_user_ids === []));
        Queue::assertNothingPushed();
    }

    public function test_correct_email_code_is_consumed_logs_in_and_verifies_email(): void
    {
        Queue::fake();
        $student = $this->student(['email' => 'student@example.com', 'email_verified_at' => null]);
        $code = $this->requestCode('student@example.com');

        $this->post(route('login.otp.verify'), ['otp' => $code])
            ->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertAuthenticatedAs($student);
        $challenge = LoginOtpChallenge::sole();
        $this->assertNotNull($challenge->consumed_at);
        $this->assertSame($student->id, $challenge->selected_user_id);
        $this->assertNotNull($student->fresh()->email_verified_at);

        Auth()->logout();
        $this->post(route('login.otp.verify'), ['otp' => $code])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_code_is_limited_and_challenge_self_destructs(): void
    {
        Queue::fake();
        $this->student(['email' => 'student@example.com']);
        $this->requestCode('student@example.com');

        for ($attempt = 1; $attempt <= LoginOtpService::MAX_ATTEMPTS; $attempt++) {
            $this->post(route('login.otp.verify'), ['otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $challenge = LoginOtpChallenge::sole();
        $this->assertSame(LoginOtpService::MAX_ATTEMPTS, $challenge->attempts);
        $this->assertNotNull($challenge->consumed_at);
        $this->assertGuest();
    }

    public function test_shared_parent_phone_requires_verification_before_names_and_then_allows_selection(): void
    {
        Queue::fake();
        $class = ClassLevel::create(['level' => 6, 'label' => 'Class 6', 'is_active' => true, 'sort_order' => 6]);
        $first = $this->student(['name' => 'Aarav Sharma', 'email' => 'aarav@example.com', 'phone' => '9876543210', 'class_level_id' => $class->id]);
        $second = $this->student(['name' => 'Anaya Sharma', 'email' => 'anaya@example.com', 'phone' => '+91 98765 43210', 'class_level_id' => $class->id]);

        $code = $this->requestCode('98765 43210');

        $this->get(route('login'))
            ->assertDontSee('Aarav Sharma')
            ->assertDontSee('Anaya Sharma');

        $this->post(route('login.otp.verify'), ['otp' => $code])
            ->assertRedirect();
        $this->assertGuest();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->where('otpFlow.step', 'select')
            ->has('otpFlow.candidates', 2)
            ->where('otpFlow.candidates.0.name', 'Aarav Sharma')
            ->where('otpFlow.candidates.1.name', 'Anaya Sharma'));

        $this->post(route('login.otp.select'), ['user_id' => $second->id])
            ->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertAuthenticatedAs($second);
        $this->assertNotNull($second->fresh()->phone_verified_at);
        $this->assertNull($first->fresh()->phone_verified_at);
    }

    public function test_resend_cooldown_blocks_immediate_second_delivery(): void
    {
        Queue::fake();
        $this->student(['email' => 'student@example.com']);
        $this->requestCode('student@example.com');

        $this->post(route('login.otp.resend'))
            ->assertSessionHasErrors('identifier');

        Queue::assertPushed(SendLoginOtp::class, 1);
    }

    public function test_resend_invalidates_the_previous_challenge_and_latest_code_can_login(): void
    {
        Queue::fake();
        $student = $this->student(['email' => 'student@example.com']);
        $this->requestCode('student@example.com');
        $oldChallengeId = LoginOtpChallenge::sole()->id;

        $this->travel(61)->seconds();
        $this->post(route('login.otp.resend'))->assertRedirect();

        $jobs = Queue::pushed(SendLoginOtp::class);
        $newCode = $jobs->last()->code;
        $this->assertNotNull(LoginOtpChallenge::findOrFail($oldChallengeId)->consumed_at);
        $this->assertNotSame($oldChallengeId, session(LoginOtpService::SESSION_KEY.'.challenge_id'));

        $this->post(route('login.otp.verify'), ['otp' => $newCode])
            ->assertRedirect(route('student.dashboard', absolute: false));
        $this->assertAuthenticatedAs($student);
    }

    public function test_expired_code_cannot_be_replayed(): void
    {
        Queue::fake();
        $this->student(['email' => 'student@example.com']);
        $code = $this->requestCode('student@example.com');

        $this->travel(LoginOtpService::EXPIRY_MINUTES + 1)->minutes();

        $this->post(route('login.otp.verify'), ['otp' => $code])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_delivery_payloads_include_name_for_email_and_aisensy_contact_context(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'mail-1'], 201),
            'backend.aisensy.com/*' => Http::response(['success' => true], 200),
        ]);

        $delivery = app(AuthenticationOtpDeliveryService::class);
        $delivery->send('email', 'aarav@example.com', 'Aarav', '123456');
        $delivery->send('whatsapp', '+919876543210', 'Aarav', '654321');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.brevo.com')
            && $request['to'][0]['name'] === 'Aarav'
            && str_contains($request['htmlContent'], 'Hi Aarav')
            && str_contains($request['htmlContent'], '123456'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'backend.aisensy.com')
            && $request['destination'] === '919876543210'
            && $request['userName'] === 'Aarav'
            && $request['templateParams'] === ['654321']
            && $request['buttons'][0]['parameters'][0]['text'] === '654321');
    }

    private function requestCode(string $identifier): string
    {
        $code = '';
        $this->post(route('login.otp.send'), ['identifier' => $identifier])->assertRedirect();

        Queue::assertPushed(SendLoginOtp::class, function (SendLoginOtp $job) use (&$code): bool {
            $code = $job->code;

            return true;
        });

        $this->assertNotSame('', $code);

        return $code;
    }

    private function student(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'student',
            'is_active' => true,
        ], $overrides));
    }
}
