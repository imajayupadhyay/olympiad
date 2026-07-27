<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * Payment seam — **Razorpay Standard Web Checkout** with coupon support.
 *
 * Flow: createPendingPayment() records the cart (no gateway call yet). The student
 * may apply/remove a coupon on the checkout screen (server recomputes the total).
 * openOrder() then creates the Razorpay order for the *final* amount so the order
 * always matches what's charged; the signed response is verified in verifyAndEnroll().
 * A fully-discounted cart (₹0) enrols for free via enrollFreeByCoupon().
 */
class PaymentService
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected CouponService $coupons,
    ) {}

    /** Lazily build the Razorpay API client from config. */
    public function api(): Api
    {
        return new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret'),
        );
    }

    /**
     * Record a pending payment for the given (paid) exams. No Razorpay order is
     * created yet — that happens at openOrder() once any coupon is settled.
     */
    public function createPendingPayment(User $user, array $examIds, string $source = 'checkout'): Payment
    {
        $summary = $this->enrollments->selectionSummary($examIds, $user);
        $examIds = collect($summary['items'])->pluck('id')->all();
        $total = (float) $summary['total'];

        return Payment::create([
            'user_id' => $user->id,
            'amount' => $total,
            'gross_amount' => $total,
            'discount_amount' => 0,
            'currency' => $summary['currency'],
            'status' => 'created',
            'gateway' => 'razorpay',
            'source' => $source,
            'notes' => ['exam_ids' => $examIds],
        ]);
    }

    /** Validate + apply a coupon to a pending payment. Returns the CouponService result. */
    public function applyCoupon(Payment $payment, string $code): array
    {
        $result = $this->coupons->validate($code, (float) $payment->gross_amount, $payment->user);

        if ($result['ok']) {
            $this->setDiscount($payment, $result['coupon'], $result['discount']);
        }

        return $result;
    }

    public function removeCoupon(Payment $payment): void
    {
        $payment->update([
            'coupon_id' => null,
            'discount_amount' => 0,
            'amount' => $payment->gross_amount,
        ]);
    }

    /**
     * Create the Razorpay order for the final payable amount.
     *
     * @return array{status: 'ok'|'free'|'coupon_dropped', ...}
     *
     * @throws \RuntimeException when the Razorpay API call fails.
     */
    public function openOrder(Payment $payment): array
    {
        $payment->refresh();

        // Never overwrite an order ID on retry. A UPI attempt can complete after
        // the customer returns to the page; replacing its order ID would orphan
        // that captured payment from both webhook and scheduled reconciliation.
        if ($payment->razorpay_order_id) {
            return $this->reuseExistingOrder($payment);
        }

        // A coupon may have expired / hit its limit since it was applied — re-check.
        if ($payment->coupon_id) {
            $result = $this->coupons->validate($payment->coupon->code, (float) $payment->gross_amount, $payment->user);

            if (! $result['ok']) {
                $this->removeCoupon($payment);

                return ['status' => 'coupon_dropped', 'message' => $result['message']];
            }

            $this->setDiscount($payment, $result['coupon'], $result['discount']);
        }

        $payment->refresh();

        // Fully covered by the coupon (or below the gateway minimum) → free enrolment.
        $paise = (int) round((float) $payment->amount * 100);
        if ($paise < 100) {
            return ['status' => 'free'];
        }

        try {
            $order = $this->api()->order->create([
                'amount' => $paise,
                'currency' => $payment->currency,
                'receipt' => 'rcpt_'.uniqid(),
                'notes' => [
                    'payment_id' => (string) $payment->id,
                    'user_id' => (string) $payment->user_id,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not create Razorpay order: '.$e->getMessage(), 0, $e);
        }

        $payment->update(['razorpay_order_id' => $order['id']]);

        return [
            'status' => 'ok',
            'order_id' => $order['id'],
            'amount_paise' => $paise,
            'key_id' => config('services.razorpay.key_id'),
            'currency' => $payment->currency,
        ];
    }

    /** Reopen the same gateway order for a retry, or recover it if already paid. */
    protected function reuseExistingOrder(Payment $payment): array
    {
        $gateway = $this->fetchRazorpayOrderWithPayments($payment->razorpay_order_id);
        $order = $gateway['order'];
        $expectedPaise = $this->expectedAmountPaise($payment);

        if (($order['id'] ?? null) !== $payment->razorpay_order_id
            || (int) ($order['amount'] ?? -1) !== $expectedPaise
            || strtoupper((string) ($order['currency'] ?? '')) !== strtoupper((string) $payment->currency)) {
            throw new \RuntimeException('The existing Razorpay order does not match this payment.');
        }

        if (($order['status'] ?? null) === 'paid') {
            $result = $this->reconcileCapturedPayment($payment);

            if (in_array($result['status'], ['paid', 'already_paid'], true)) {
                return ['status' => 'paid'];
            }

            throw new \RuntimeException('Razorpay reports the order paid but no matching captured payment was found.');
        }

        if (! in_array(($order['status'] ?? null), ['created', 'attempted'], true)) {
            throw new \RuntimeException('The existing Razorpay order cannot accept another payment attempt.');
        }

        return [
            'status' => 'ok',
            'order_id' => $payment->razorpay_order_id,
            'amount_paise' => $expectedPaise,
            'key_id' => config('services.razorpay.key_id'),
            'currency' => $payment->currency,
        ];
    }

    /** Enrol when a coupon brings the total to ₹0 — no gateway involved. Idempotent. */
    public function enrollFreeByCoupon(Payment $payment): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        $this->fulfil($payment, null, null, 'coupon');
    }

    /**
     * Verify the signed checkout response and, if valid, mark paid + enrol. Idempotent.
     *
     * @throws SignatureVerificationError on a bad signature.
     */
    public function verifyAndEnroll(Payment $payment, string $paymentId, string $signature): bool
    {
        // Always verify first. The marketing callback is intentionally public for
        // WebView support, so an already-paid order must never become a login bypass.
        $this->api()->utility->verifyPaymentSignature([
            'razorpay_order_id' => $payment->razorpay_order_id,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);

        if ($payment->status === 'paid') {
            return true;
        }

        return $this->fulfil($payment, $paymentId, $signature, 'razorpay', $payment->razorpay_order_id);
    }

    /** Mark paid from an (already verified) webhook event + enrol. Idempotent. */
    public function markPaidFromWebhook(
        Payment $payment,
        string $paymentId,
        ?int $amountPaise = null,
        ?string $currency = null,
        ?string $method = null,
    ): bool {
        if ($payment->status === 'paid') {
            return false;
        }

        if (! $this->gatewayAmountMatches($payment, $amountPaise, $currency)) {
            Log::warning('Rejected Razorpay webhook with mismatched payment details.', [
                'payment_id' => $payment->id,
                'razorpay_order_id' => $payment->razorpay_order_id,
                'razorpay_payment_id' => $paymentId,
                'expected_amount_paise' => $this->expectedAmountPaise($payment),
                'received_amount_paise' => $amountPaise,
                'expected_currency' => $payment->currency,
                'received_currency' => $currency,
            ]);

            return false;
        }

        return $this->fulfil(
            $payment,
            $paymentId,
            null,
            $method ?: 'razorpay',
            $payment->razorpay_order_id,
        );
    }

    /**
     * Recover a pending local payment by querying Razorpay server-to-server.
     * Both the paid order and a captured payment must match the local amount and
     * currency before fulfilment is allowed.
     *
     * @return array{status:'paid'|'already_paid'|'pending'|'skipped'|'mismatch', message:string}
     */
    public function reconcileCapturedPayment(Payment $payment): array
    {
        $payment->refresh();

        if ($payment->status === 'paid') {
            return ['status' => 'already_paid', 'message' => 'Payment was already complete.'];
        }

        if ($payment->status !== 'created' || $payment->gateway !== 'razorpay' || ! $payment->razorpay_order_id) {
            return ['status' => 'skipped', 'message' => 'Payment is not an eligible pending Razorpay order.'];
        }

        $gateway = $this->fetchRazorpayOrderWithPayments($payment->razorpay_order_id);
        $order = $gateway['order'];
        $expectedPaise = $this->expectedAmountPaise($payment);
        $currency = strtoupper((string) $payment->currency);

        if (($order['id'] ?? null) !== $payment->razorpay_order_id
            || (int) ($order['amount'] ?? -1) !== $expectedPaise
            || strtoupper((string) ($order['currency'] ?? '')) !== $currency) {
            Log::warning('Rejected Razorpay reconciliation with mismatched order details.', [
                'payment_id' => $payment->id,
                'razorpay_order_id' => $payment->razorpay_order_id,
                'gateway_order_id' => $order['id'] ?? null,
                'expected_amount_paise' => $expectedPaise,
                'gateway_amount_paise' => $order['amount'] ?? null,
                'expected_currency' => $currency,
                'gateway_currency' => $order['currency'] ?? null,
            ]);

            return ['status' => 'mismatch', 'message' => 'Gateway order details did not match the local payment.'];
        }

        $captured = collect($gateway['payments'])->first(fn (array $item) => ($item['order_id'] ?? null) === $payment->razorpay_order_id
            && ($item['status'] ?? null) === 'captured'
            && (int) ($item['amount'] ?? -1) === $expectedPaise
            && strtoupper((string) ($item['currency'] ?? '')) === $currency
        );

        if (($order['status'] ?? null) !== 'paid' || ! $captured) {
            return ['status' => 'pending', 'message' => 'Razorpay has not reported a matching captured payment yet.'];
        }

        $completed = $this->fulfil(
            $payment,
            (string) $captured['id'],
            null,
            (string) ($captured['method'] ?? 'razorpay'),
            $payment->razorpay_order_id,
        );

        if ($completed) {
            return ['status' => 'paid', 'message' => 'Captured Razorpay payment reconciled successfully.'];
        }

        return $payment->refresh()->isPaid()
            ? ['status' => 'already_paid', 'message' => 'Payment was completed by another recovery path.']
            : ['status' => 'pending', 'message' => 'The local Razorpay order changed while reconciliation was running.'];
    }

    public function markFailed(Payment $payment): void
    {
        if ($payment->status !== 'paid') {
            $payment->update(['status' => 'failed']);
        }
    }

    public function recordManualExamPayment(User $user, Exam $exam, User $admin, ?string $reference = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($user, $exam, $admin, $reference, $note) {
            $amount = (float) $exam->fee_amount;

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'gross_amount' => $amount,
                'discount_amount' => 0,
                'currency' => $exam->fee_currency,
                'status' => 'paid',
                'gateway' => 'manual',
                'source' => 'admin',
                'method' => 'manual_admin',
                'is_manual' => true,
                'recorded_by_admin_id' => $admin->id,
                'manually_recorded_at' => now(),
                'manual_reference' => $reference,
                'manual_note' => $note,
                'notes' => [
                    'manual' => true,
                    'source' => 'admin_assignment',
                    'exam_ids' => [$exam->id],
                    'recorded_by_admin_id' => $admin->id,
                ],
                'paid_at' => now(),
            ]);

            $this->enrollments->enrollAfterPayment($user, $payment, [$exam->id], 'manual_payment');
            $this->completePaidSideEffects($payment);

            return $payment->refresh();
        });
    }

    public function reconcileManually(Payment $payment, User $admin, ?string $reference = null, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $admin, $reference, $note) {
            $payment->refresh();

            if ($payment->status === 'paid') {
                return $payment;
            }

            $notes = $payment->notes ?? [];
            $notes['manual'] = true;
            $notes['source'] = 'admin_reconciliation';
            $notes['recorded_by_admin_id'] = $admin->id;

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'method' => 'manual_reconcile',
                'is_manual' => true,
                'recorded_by_admin_id' => $admin->id,
                'manually_recorded_at' => now(),
                'manual_reference' => $reference,
                'manual_note' => $note,
                'notes' => $notes,
            ]);

            $examIds = $payment->notes['exam_ids'] ?? [];
            $this->enrollments->enrollAfterPayment($payment->user, $payment, $examIds, 'manual_reconcile');
            $this->completePaidSideEffects($payment);

            return $payment->refresh();
        });
    }

    public function downgrade(Payment $payment, User $admin, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $admin, $note) {
            $payment->refresh();

            if (! in_array($payment->status, ['created', 'paid'], true)) {
                return $payment;
            }

            $activeEnrollments = $payment->enrollments()
                ->where('status', 'enrolled')
                ->get(['id', 'exam_id']);

            if ($activeEnrollments->isNotEmpty()) {
                $hasAttempt = ExamAttempt::where('user_id', $payment->user_id)
                    ->whereIn('exam_id', $activeEnrollments->pluck('exam_id'))
                    ->exists();

                if ($hasAttempt) {
                    throw ValidationException::withMessages([
                        'payment' => 'This payment cannot be downgraded because the student has already attempted one of the linked olympiads.',
                    ]);
                }

                $payment->enrollments()
                    ->whereIn('id', $activeEnrollments->pluck('id'))
                    ->update(['status' => 'cancelled']);
            }

            $previousStatus = $payment->status;
            $notes = $payment->notes ?? [];
            $notes['downgrade'] = [
                'previous_status' => $previousStatus,
                'by_admin_id' => $admin->id,
                'at' => now()->toIso8601String(),
                'note' => $note,
            ];

            $payment->update([
                'status' => $previousStatus === 'paid' ? 'refunded' : 'failed',
                'manual_note' => $note ?: $payment->manual_note,
                'notes' => $notes,
            ]);

            return $payment->refresh();
        });
    }

    /** Persist the final discount + recomputed payable amount. */
    protected function setDiscount(Payment $payment, Coupon $coupon, float $discount): void
    {
        $payment->update([
            'coupon_id' => $coupon->id,
            'discount_amount' => $discount,
            'amount' => max(0, round((float) $payment->gross_amount - $discount, 2)),
        ]);
    }

    /** Persist the paid state, enrol, and redeem the coupon. Shared by all paid paths. */
    protected function fulfil(
        Payment $payment,
        ?string $paymentId,
        ?string $signature,
        string $method = 'razorpay',
        ?string $expectedOrderId = null,
    ): bool {
        $completed = DB::transaction(function () use ($payment, $paymentId, $signature, $method, $expectedOrderId): ?Payment {
            /** @var Payment $locked */
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                return null;
            }

            if ($locked->status !== 'created') {
                return null;
            }

            // Do not attach a captured attempt to a newer order if the customer
            // retried checkout while another recovery path was in flight.
            if ($expectedOrderId !== null && $locked->razorpay_order_id !== $expectedOrderId) {
                return null;
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'method' => $method,
            ]);

            $examIds = $locked->notes['exam_ids'] ?? [];
            $this->enrollments->enrollAfterPayment($locked->user, $locked, $examIds);

            return $locked->refresh();
        });

        if (! $completed) {
            $payment->refresh();

            return false;
        }

        $payment->setRawAttributes($completed->getAttributes(), true);

        $this->completePaidSideEffects($payment);

        return true;
    }

    /** @return array{order:array<string,mixed>, payments:list<array<string,mixed>>} */
    protected function fetchRazorpayOrderWithPayments(string $orderId): array
    {
        $order = $this->api()->order->fetch($orderId);
        $payments = $order->payments()->toArray();

        return [
            'order' => $order->toArray(),
            'payments' => array_values($payments['items'] ?? []),
        ];
    }

    protected function gatewayAmountMatches(Payment $payment, ?int $amountPaise, ?string $currency): bool
    {
        if ($amountPaise === null || $currency === null) {
            return false;
        }

        return $amountPaise === $this->expectedAmountPaise($payment)
            && strtoupper($currency) === strtoupper((string) $payment->currency);
    }

    protected function expectedAmountPaise(Payment $payment): int
    {
        return (int) round((float) $payment->amount * 100);
    }

    protected function completePaidSideEffects(Payment $payment): void
    {
        try {
            app(ManagedEmailService::class)->queue(
                'payment_success',
                $payment->user,
                app(ManagedEmailService::class)->paymentVariables($payment->refresh()),
                ['related_type' => Payment::class, 'related_id' => $payment->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        if ($payment->coupon_id && $payment->coupon) {
            try {
                $this->coupons->redeem($payment->coupon, $payment->user, $payment, (float) $payment->discount_amount);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Qualify a referral on first paid enrolment (no-op in registration mode).
        try {
            app(ReferralService::class)->qualifyReferral($payment->user, 'first_paid_enrollment');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
