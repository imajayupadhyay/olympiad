<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Razorpay\Api\Api;

/**
 * Payment seam — **Razorpay Standard Web Checkout** (TEST mode).
 *
 * Flow: createOrder() opens a Razorpay order and stores a pending Payment; the
 * browser then completes checkout and the signed response is verified server-side
 * in verifyAndEnroll(). The webhook (markPaidFromWebhook) is the reliability net
 * for the browser-closed-after-pay case. Enrolment itself stays in EnrollmentService.
 */
class PaymentService
{
    public function __construct(
        protected EnrollmentService $enrollments,
    ) {
    }

    /** Lazily build the Razorpay API client from config. */
    public function api(): Api
    {
        return new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret'),
        );
    }

    /**
     * Create a Razorpay order for the given (paid) exams and store a pending
     * Payment. The front-end then opens the checkout modal with this order id.
     *
     * @throws \InvalidArgumentException when the amount is below the ₹1 minimum.
     * @throws \RuntimeException         when the Razorpay API call fails.
     */
    public function createOrder(User $user, array $examIds): Payment
    {
        $summary = $this->enrollments->selectionSummary($examIds, $user);
        $examIds = collect($summary['items'])->pluck('id')->all();

        $paise = (int) round($summary['total'] * 100);
        if ($paise < 100) {
            throw new \InvalidArgumentException('Payment amount must be at least ₹1 (100 paise).');
        }

        try {
            $order = $this->api()->order->create([
                'amount'   => $paise,
                'currency' => $summary['currency'],
                'receipt'  => 'rcpt_'.uniqid(),
                'notes'    => [
                    'user_id'  => (string) $user->id,
                    'exam_ids' => implode(',', $examIds),
                ],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not create Razorpay order: '.$e->getMessage(), 0, $e);
        }

        return Payment::create([
            'user_id'           => $user->id,
            'amount'            => $summary['total'],
            'currency'          => $summary['currency'],
            'status'            => 'created',
            'gateway'           => 'razorpay',
            'razorpay_order_id' => $order['id'],
            'notes'             => ['exam_ids' => $examIds],
        ]);
    }

    /**
     * Verify the signed checkout response and, if valid, mark the payment paid and
     * enrol the student. Idempotent.
     *
     * @throws \Razorpay\Api\Errors\SignatureVerificationError on a bad signature.
     */
    public function verifyAndEnroll(Payment $payment, string $paymentId, string $signature): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $this->api()->utility->verifyPaymentSignature([
            'razorpay_order_id'   => $payment->razorpay_order_id,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature,
        ]);

        $this->fulfil($payment, $paymentId, $signature);
    }

    /**
     * Mark a payment paid from a (already signature-verified) webhook event and
     * enrol the student. Idempotent.
     */
    public function markPaidFromWebhook(Payment $payment, string $paymentId): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $this->fulfil($payment, $paymentId, null);
    }

    public function markFailed(Payment $payment): void
    {
        if ($payment->status !== 'paid') {
            $payment->update(['status' => 'failed']);
        }
    }

    /** Persist the paid state and trigger enrolment. Shared by client + webhook paths. */
    protected function fulfil(Payment $payment, string $paymentId, ?string $signature): void
    {
        $payment->update([
            'status'              => 'paid',
            'paid_at'             => now(),
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature,
            'method'              => 'razorpay',
        ]);

        $examIds = $payment->notes['exam_ids'] ?? [];
        $this->enrollments->enrollAfterPayment($payment->user, $payment, $examIds);
    }
}
