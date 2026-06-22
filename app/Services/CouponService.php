<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\User;

/**
 * Coupon validation, discount calculation and redemption. All amounts are rupees.
 * Discounts are always recomputed server-side from the authoritative cart total —
 * the client never supplies a discount.
 */
class CouponService
{
    /**
     * Validate a code against a cart total for a user.
     *
     * @return array{ok: bool, coupon: ?Coupon, discount: float, message: string}
     */
    public function validate(string $code, float $gross, User $user): array
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();

        if (! $coupon) {
            return $this->fail('This coupon code is not valid.');
        }

        if (! $coupon->isLive()) {
            return $this->fail('This coupon is not active.');
        }

        // Personal (referral) coupons are locked to their owner.
        if ($coupon->owner_user_id !== null && $coupon->owner_user_id !== $user->id) {
            return $this->fail('This reward isn’t available on your account.');
        }

        if ($coupon->isExhausted()) {
            return $this->fail('This coupon has reached its usage limit.');
        }

        if ($gross < (float) $coupon->min_order_amount) {
            return $this->fail('Add items worth ₹'.number_format((float) $coupon->min_order_amount, 0).' to use this coupon.');
        }

        if ($coupon->usage_limit_per_user !== null) {
            $usedByUser = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->count();

            if ($usedByUser >= $coupon->usage_limit_per_user) {
                return $this->fail('You have already used this coupon.');
            }
        }

        $discount = $this->discountFor($coupon, $gross);

        if ($discount <= 0) {
            return $this->fail('This coupon does not apply to your selection.');
        }

        return [
            'ok'       => true,
            'coupon'   => $coupon,
            'discount' => $discount,
            'message'  => 'Coupon applied.',
        ];
    }

    /** Compute the rupee discount for a coupon against a gross total. */
    public function discountFor(Coupon $coupon, float $gross): float
    {
        if ($coupon->type === 'percentage') {
            $discount = round($gross * ((float) $coupon->value) / 100, 2);

            if ($coupon->max_discount !== null) {
                $discount = min($discount, (float) $coupon->max_discount);
            }
        } else {
            $discount = (float) $coupon->value;
        }

        // Never discount below zero.
        return round(min($discount, $gross), 2);
    }

    /**
     * Record a redemption and bump the global counter. Idempotent per payment
     * (unique coupon_id + payment_id), so re-runs from webhook/verify are safe.
     */
    public function redeem(Coupon $coupon, User $user, Payment $payment, float $discount): void
    {
        $redemption = CouponRedemption::firstOrCreate(
            ['coupon_id' => $coupon->id, 'payment_id' => $payment->id],
            ['user_id' => $user->id, 'discount_amount' => $discount, 'redeemed_at' => now()],
        );

        // Only increment the global counter the first time this payment is redeemed.
        if ($redemption->wasRecentlyCreated) {
            $coupon->increment('used_count');
        }
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'coupon' => null, 'discount' => 0.0, 'message' => $message];
    }
}
