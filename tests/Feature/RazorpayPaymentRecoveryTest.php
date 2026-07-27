<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use App\Services\CouponService;
use App\Services\EnrollmentService;
use App\Services\MarketingFunnelService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RazorpayPaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config()->set('services.razorpay.key_id', 'rzp_test_recovery');
        config()->set('services.razorpay.key_secret', 'payment-signing-secret');
        config()->set('services.razorpay.webhook_secret', 'webhook-signing-secret');

        $class = ClassLevel::create([
            'level' => 8,
            'label' => 'Class 8',
            'is_active' => true,
            'sort_order' => 8,
        ]);
        $subject = Subject::create([
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->student = User::factory()->create([
            'class_level_id' => $class->id,
            'role' => 'student',
            'is_active' => true,
        ]);
        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'class_level_id' => $class->id,
            'name' => 'National Mathematics Olympiad',
            'slug' => 'national-mathematics-olympiad',
            'exam_code' => 'NMO1001',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeeks(2),
            'duration_minutes' => 60,
            'fee_amount' => 250,
            'fee_currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_signed_marketing_callback_completes_a_fresh_payment_without_an_existing_session(): void
    {
        $payment = $this->pendingPayment();
        $gatewayPaymentId = 'pay_callback_123';
        $signature = hash_hmac(
            'sha256',
            $payment->razorpay_order_id.'|'.$gatewayPaymentId,
            'payment-signing-secret',
        );

        $this->post(route('marketing.payment.callback', $payment), [
            'razorpay_payment_id' => $gatewayPaymentId,
            'razorpay_order_id' => $payment->razorpay_order_id,
            'razorpay_signature' => $signature,
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($this->student);
        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame($gatewayPaymentId, $payment->razorpay_payment_id);
        $this->assertDatabaseHas('exam_enrollments', [
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'payment_id' => $payment->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_public_callback_always_verifies_the_signature_even_when_payment_is_already_paid(): void
    {
        $payment = $this->pendingPayment();
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $this->post(route('marketing.payment.callback', $payment), [
            'razorpay_payment_id' => 'pay_forged',
            'razorpay_order_id' => $payment->razorpay_order_id,
            'razorpay_signature' => 'forged',
        ])->assertBadRequest();

        $this->assertGuest();
    }

    public function test_captured_webhook_completes_and_idempotently_enrols_the_student(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->capturedWebhookPayload($payment);

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame('pay_webhook_123', $payment->razorpay_payment_id);
        $this->assertSame('upi', $payment->method);
        $this->assertSame(1, ExamEnrollment::where('payment_id', $payment->id)->count());
    }

    public function test_webhook_refuses_a_captured_payment_with_the_wrong_amount(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->capturedWebhookPayload($payment);
        $payload['payload']['payment']['entity']['amount'] = 24999;

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame('created', $payment->refresh()->status);
        $this->assertSame(0, ExamEnrollment::count());
    }

    public function test_scheduled_command_recovers_a_captured_order_missed_by_callback_and_webhook(): void
    {
        $payment = $this->pendingPayment();
        $payment->forceFill(['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)])->save();

        $service = $this->fakeGatewayService([
            'order' => [
                'id' => $payment->razorpay_order_id,
                'amount' => 25000,
                'currency' => 'INR',
                'status' => 'paid',
            ],
            'payments' => [[
                'id' => 'pay_reconciled_123',
                'order_id' => $payment->razorpay_order_id,
                'amount' => 25000,
                'currency' => 'INR',
                'status' => 'captured',
                'method' => 'card',
            ]],
        ]);
        $this->app->instance(PaymentService::class, $service);

        $this->artisan('payments:reconcile-razorpay', ['--minutes' => 2])
            ->expectsOutputToContain('1 recovered')
            ->assertSuccessful();

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame('pay_reconciled_123', $payment->razorpay_payment_id);
        $this->assertSame('card', $payment->method);
        $this->assertDatabaseHas('exam_enrollments', ['payment_id' => $payment->id]);
    }

    public function test_reconciliation_refuses_gateway_order_mismatches(): void
    {
        $payment = $this->pendingPayment();
        $service = $this->fakeGatewayService([
            'order' => [
                'id' => $payment->razorpay_order_id,
                'amount' => 99900,
                'currency' => 'INR',
                'status' => 'paid',
            ],
            'payments' => [],
        ]);

        $result = $service->reconcileCapturedPayment($payment);

        $this->assertSame('mismatch', $result['status']);
        $this->assertSame('created', $payment->refresh()->status);
        $this->assertSame(0, ExamEnrollment::count());
    }

    public function test_payment_retry_reuses_the_existing_order_instead_of_overwriting_it(): void
    {
        $payment = $this->pendingPayment();
        $service = $this->fakeGatewayService([
            'order' => [
                'id' => $payment->razorpay_order_id,
                'amount' => 25000,
                'currency' => 'INR',
                'status' => 'attempted',
            ],
            'payments' => [],
        ]);

        $result = $service->openOrder($payment);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('order_recovery_123', $result['order_id']);
        $this->assertSame('order_recovery_123', $payment->refresh()->razorpay_order_id);
    }

    public function test_marketing_order_payload_contains_the_signed_webview_callback_url(): void
    {
        $payment = $this->pendingPayment(['razorpay_order_id' => null]);
        $service = new class(app(EnrollmentService::class), app(CouponService::class)) extends PaymentService
        {
            public function openOrder(Payment $payment): array
            {
                $payment->update(['razorpay_order_id' => 'order_opened_123']);

                return [
                    'status' => 'ok',
                    'order_id' => 'order_opened_123',
                    'amount_paise' => 25000,
                    'key_id' => 'rzp_test_recovery',
                    'currency' => 'INR',
                ];
            }
        };
        $this->app->instance(PaymentService::class, $service);

        $result = app(MarketingFunnelService::class)->openOrderFor($payment);

        $this->assertSame(route('marketing.payment.callback', $payment), $result['callback_url']);
    }

    private function pendingPayment(array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'user_id' => $this->student->id,
            'amount' => 250,
            'gross_amount' => 250,
            'discount_amount' => 0,
            'currency' => 'INR',
            'status' => 'created',
            'gateway' => 'razorpay',
            'source' => 'marketing',
            'razorpay_order_id' => 'order_recovery_123',
            'notes' => ['exam_ids' => [$this->exam->id]],
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function capturedWebhookPayload(Payment $payment): array
    {
        return [
            'entity' => 'event',
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_webhook_123',
                        'order_id' => $payment->razorpay_order_id,
                        'amount' => 25000,
                        'currency' => 'INR',
                        'status' => 'captured',
                        'method' => 'upi',
                    ],
                ],
            ],
        ];
    }

    private function postSignedWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, 'webhook-signing-secret');

        return $this->call('POST', route('razorpay.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            'HTTP_X_RAZORPAY_EVENT_ID' => 'event_recovery_123',
        ], $body);
    }

    private function fakeGatewayService(array $gatewayResponse): PaymentService
    {
        return new class(app(EnrollmentService::class), app(CouponService::class), $gatewayResponse) extends PaymentService
        {
            public function __construct(
                EnrollmentService $enrollments,
                CouponService $coupons,
                private array $gatewayResponse,
            ) {
                parent::__construct($enrollments, $coupons);
            }

            protected function fetchRazorpayOrderWithPayments(string $orderId): array
            {
                return $this->gatewayResponse;
            }
        };
    }
}
