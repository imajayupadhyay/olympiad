<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\User;
use Razorpay\Api\Api;

/**
 * Payment seam — **Razorpay Standard Web Checkout** (TEST mode) with coupon support.
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
     * Record a pending payment for the given (paid) exams. No Razorpay order is
     * created yet — that happens at openOrder() once any coupon is settled.
     */
    public function createPendingPayment(User $user, array $examIds): Payment
    {
        $summary = $this->enrollments->selectionSummary($examIds, $user);
        $examIds = collect($summary['items'])->pluck('id')->all();
        $total = (float) $summary['total'];

        return Payment::create([
            'user_id'         => $user->id,
            'amount'          => $total,
            'gross_amount'    => $total,
            'discount_amount' => 0,
            'currency'        => $summary['currency'],
            'status'          => 'created',
            'gateway'         => 'razorpay',
            'notes'           => ['exam_ids' => $examIds],
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
            'coupon_id'       => null,
            'discount_amount' => 0,
            'amount'          => $payment->gross_amount,
        ]);
    }

    /**
     * Create the Razorpay order for the final payable amount.
     *
     * @return array{status: 'ok'|'free'|'coupon_dropped', ...}
     * @throws \RuntimeException when the Razorpay API call fails.
     */
    public function openOrder(Payment $payment): array
    {
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
                'amount'   => $paise,
                'currency' => $payment->currency,
                'receipt'  => 'rcpt_'.uniqid(),
                'notes'    => [
                    'payment_id' => (string) $payment->id,
                    'user_id'    => (string) $payment->user_id,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not create Razorpay order: '.$e->getMessage(), 0, $e);
        }

        $payment->update(['razorpay_order_id' => $order['id']]);

        return [
            'status'       => 'ok',
            'order_id'     => $order['id'],
            'amount_paise' => $paise,
            'key_id'       => config('services.razorpay.key_id'),
            'currency'     => $payment->currency,
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

    /** Mark paid from an (already verified) webhook event + enrol. Idempotent. */
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

    /** Persist the final discount + recomputed payable amount. */
    protected function setDiscount(Payment $payment, Coupon $coupon, float $discount): void
    {
        $payment->update([
            'coupon_id'       => $coupon->id,
            'discount_amount' => $discount,
            'amount'          => max(0, round((float) $payment->gross_amount - $discount, 2)),
        ]);
    }

    /** Persist the paid state, enrol, and redeem the coupon. Shared by all paid paths. */
    protected function fulfil(Payment $payment, ?string $paymentId, ?string $signature, string $method = 'razorpay'): void
    {
        $payment->update([
            'status'              => 'paid',
            'paid_at'             => now(),
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature,
            'method'              => $method,
        ]);

        $examIds = $payment->notes['exam_ids'] ?? [];
        $this->enrollments->enrollAfterPayment($payment->user, $payment, $examIds);

        if ($payment->coupon_id && $payment->coupon) {
            $this->coupons->redeem($payment->coupon, $payment->user, $payment, (float) $payment->discount_amount);
        }

        // Qualify a referral on first paid enrolment (no-op in registration mode).
        app(ReferralService::class)->qualifyReferral($payment->user, 'first_paid_enrollment');
    }
}
