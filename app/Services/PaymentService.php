<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Payment seam. Currently a **demo gateway** that simulates a checkout end-to-end
 * (no real money). Swapping to Razorpay later only changes this class: replace
 * createDemoOrder() with a real order + add signature verification in markPaid().
 */
class PaymentService
{
    public function __construct(
        protected EnrollmentService $enrollments,
    ) {
    }

    /**
     * Create a pending payment for the given (paid) exams and return it. The
     * front-end then sends the student to the demo checkout screen.
     */
    public function createDemoOrder(User $user, array $examIds): Payment
    {
        $summary = $this->enrollments->selectionSummary($examIds, $user);

        return Payment::create([
            'user_id'           => $user->id,
            'amount'            => $summary['total'],
            'currency'          => $summary['currency'],
            'status'            => 'created',
            'gateway'           => 'demo',
            'razorpay_order_id' => 'demo_order_'.Str::random(16),
            'notes'             => ['exam_ids' => collect($summary['items'])->pluck('id')->all()],
        ]);
    }

    /**
     * Mark a payment successful and enrol the student. Idempotent.
     */
    public function markPaid(Payment $payment): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $payment->update([
            'status'              => 'paid',
            'paid_at'             => now(),
            'razorpay_payment_id' => 'demo_pay_'.Str::random(16),
            'method'              => 'demo',
        ]);

        $examIds = $payment->notes['exam_ids'] ?? [];
        $this->enrollments->enrollAfterPayment($payment->user, $payment, $examIds);
    }

    public function markFailed(Payment $payment): void
    {
        if ($payment->status !== 'paid') {
            $payment->update(['status' => 'failed']);
        }
    }
}
