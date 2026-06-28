<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Referral;
use App\Models\ReferralClick;
use App\Models\ReferralSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referral attribution, qualification and reward delivery.
 *
 * The program's source of truth lives in `referrals` + `referral_settings`. The
 * actual discounts are delivered as auto-minted *personal* coupons (owned by one
 * user), which ride the existing CouponService / PaymentService path — so the
 * gateway, webhook and redemption idempotency are reused untouched.
 */
class ReferralService
{
    public function __construct(
        protected CouponService $coupons,
    ) {
    }

    /** Resolve a referral code to an active referrer (never the referee). */
    public function resolveReferrer(string $code, ?User $referee = null): ?User
    {
        $referrer = User::where('referral_code', Str::upper(trim($code)))
            ->where('is_active', true)
            ->first();

        if (! $referrer) {
            return null;
        }

        if ($referee && $referrer->id === $referee->id) {
            return null; // no self-referral
        }

        return $referrer;
    }

    /**
     * Attribute a freshly-registered student to a referrer. Guards inactive
     * program / unknown code / self / already-referred. Mints the referee's
     * welcome coupon and, in registration mode, qualifies immediately.
     */
    public function attribute(User $referee, string $code): ?Referral
    {
        $settings = ReferralSetting::current();
        if (! $settings->is_active) {
            return null;
        }

        $code = trim($code);
        if ($code === '') {
            return null;
        }

        if (Referral::where('referee_id', $referee->id)->exists()) {
            return null; // already referred (also enforced by the unique index)
        }

        $referrer = $this->resolveReferrer($code, $referee);
        if (! $referrer) {
            return null;
        }

        $referral = DB::transaction(function () use ($referee, $referrer, $code) {
            $referral = Referral::create([
                'referrer_id'   => $referrer->id,
                'referee_id'    => $referee->id,
                'referral_code' => $code,
                'status'        => 'pending',
            ]);

            $referee->update(['referred_by' => $referrer->id]);

            if ($coupon = $this->mintWelcomeCoupon($referee)) {
                $referral->update(['referee_welcome_coupon_id' => $coupon->id]);
            }

            return $referral;
        });

        // Registration-mode qualification happens straight away.
        $this->qualifyReferral($referee, 'registration');

        return $referral->refresh();
    }

    /**
     * The single switchable qualification hook. Both call sites (registration in
     * attribute(), first paid enrolment in PaymentService::fulfil) always run; the
     * method self-gates so only the call matching the active `qualify_on` mode acts.
     * Idempotent — status only moves forward.
     */
    public function qualifyReferral(User $referee, string $trigger): void
    {
        if (ReferralSetting::current()->qualify_on !== $trigger) {
            return;
        }

        $referral = $referee->referralRecord;
        if (! $referral || $referral->status !== 'pending') {
            return;
        }

        $referral->update(['status' => 'qualified', 'qualified_at' => now()]);

        $this->maybeRewardReferrer($referral->referrer);
    }

    /**
     * Record an open of a referrer's link (link_click mode only). De-duped per
     * (referrer, IP); a newly-counted click may unlock a reward.
     */
    public function recordClick(User $referrer, ?string $ip): void
    {
        $settings = ReferralSetting::current();
        if (! $settings->is_active || $settings->qualify_on !== 'link_click') {
            return;
        }

        $click = ReferralClick::firstOrCreate(
            ['referrer_id' => $referrer->id, 'ip_address' => $ip],
            ['clicked_at' => now()],
        );

        if ($click->wasRecentlyCreated) {
            $this->maybeRewardReferrer($referrer);
        }
    }

    /**
     * Resolve a code to a referrer and record a link open (link_click mode).
     * Ignores the referrer opening their own link ($openerId === referrer).
     */
    public function recordClickByCode(string $code, ?string $ip, ?int $openerId = null): void
    {
        if (ReferralSetting::current()->qualify_on !== 'link_click') {
            return;
        }

        $referrer = $this->resolveReferrer($code);
        if (! $referrer || $openerId === $referrer->id) {
            return;
        }

        $this->recordClick($referrer, $ip);
    }

    /**
     * The count that drives the referrer's reward + progress UI: link opens in
     * link_click mode, otherwise qualified (or rewarded) referrals.
     */
    public function progressCount(User $referrer): int
    {
        if (ReferralSetting::current()->qualify_on === 'link_click') {
            return ReferralClick::where('referrer_id', $referrer->id)->count();
        }

        return $referrer->qualifiedReferralsCount();
    }

    /**
     * Share-widget state for a logged-in user (Refer & Earn card): the user's own
     * link, the configured referee/referrer labels, and the mode-aware progress
     * stats that drive the reward meter. Returns null when the program is inactive.
     * Single source of truth for the onboarding Step 2 + student Exams referral card.
     *
     * @return array<string, mixed>|null
     */
    public function shareState(User $user): ?array
    {
        $settings = ReferralSetting::current();
        if (! $settings->is_active) {
            return null;
        }

        $applied   = $user->referred_by !== null || $user->referralRecord()->exists();
        $referred  = $user->referralsMade()->count();
        $progress  = $this->progressCount($user);   // clicks or qualified, per mode
        $rewarded  = Coupon::where('owner_user_id', $user->id)
            ->where('source', 'referral_reward')->count();
        $threshold = max(1, (int) $settings->unlock_threshold);
        $toward    = $progress % $threshold;

        return [
            'applied' => $applied,                              // did THIS user sign up via a link?
            'welcome' => $settings->refereeDiscountLabel(),     // discount a referee gets
            'reward'  => $settings->referrerRewardLabel(),      // what the sharer earns
            'link'    => $user->referralLink(),                 // this user's own shareable link
            'code'    => $user->referral_code,
            'stats'   => [
                'referred'  => $referred,
                'progress'  => $progress,
                'rewarded'  => $rewarded,
                'threshold' => $threshold,
                'toward'    => $toward,
                'remaining' => $threshold - $toward,
                'mode'      => $settings->qualify_on,
            ],
        ];
    }

    /**
     * Mint referrer rewards for every full threshold of qualifying actions —
     * repeatable: refer N → a reward, another N → another. Idempotent (mints only
     * the rewards not yet granted). Locks the counted rows to avoid double-mint.
     * Works for both referral- and click-based counting.
     */
    protected function maybeRewardReferrer(User $referrer): void
    {
        $settings = ReferralSetting::current();
        $threshold = max(1, (int) $settings->unlock_threshold);

        if ((float) $settings->referrer_reward_value <= 0) {
            return; // nothing to award
        }

        DB::transaction(function () use ($referrer, $threshold, $settings) {
            if ($settings->qualify_on === 'link_click') {
                $count = ReferralClick::where('referrer_id', $referrer->id)->lockForUpdate()->count();
            } else {
                $count = Referral::where('referrer_id', $referrer->id)
                    ->whereIn('status', ['qualified', 'rewarded'])
                    ->lockForUpdate()
                    ->count();
            }

            $shouldHave = intdiv($count, $threshold);                 // rewards earned in total
            $earned = Coupon::where('owner_user_id', $referrer->id)
                ->where('source', 'referral_reward')->count();        // rewards already minted

            for ($i = $earned; $i < $shouldHave; $i++) {
                $coupon = $this->mintRewardCoupon($referrer);

                // Mark one not-yet-rewarded qualified referral (registration/paid modes);
                // link_click rewards have no referee row to mark.
                Referral::where('referrer_id', $referrer->id)
                    ->where('status', 'qualified')
                    ->latest()
                    ->first()
                    ?->update([
                        'status'                    => 'rewarded',
                        'rewarded_at'               => now(),
                        'referrer_reward_coupon_id' => $coupon->id,
                    ]);
            }
        });
    }

    /**
     * Best live, owner-matched personal coupon for this user against the cart —
     * the referee welcome coupon or an earned reward, whichever discounts more.
     */
    public function autoCouponFor(User $user, float $gross): ?Coupon
    {
        if (! ReferralSetting::current()->is_active) {
            return null;
        }

        $candidates = Coupon::where('owner_user_id', $user->id)
            ->whereIn('source', ['referral_welcome', 'referral_reward'])
            ->where('is_active', true)
            ->where('used_count', 0)
            ->get()
            ->filter(fn (Coupon $c) => $c->isLive());

        $best = null;
        $bestDiscount = 0.0;

        foreach ($candidates as $coupon) {
            if ($gross < (float) $coupon->min_order_amount) {
                continue;
            }

            $discount = $this->coupons->discountFor($coupon, $gross);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = $coupon;
            }
        }

        return $best;
    }

    /**
     * The student's usable personal discount rules (welcome + earned rewards),
     * for an instant client-side price preview. Mirrors the candidates that
     * autoCouponFor / checkout will actually apply.
     *
     * @return array<int, array{type:string,value:float,max_discount:?float,min_order_amount:float,source:string,label:string}>
     */
    public function usableCouponRules(User $user): array
    {
        if (! ReferralSetting::current()->is_active) {
            return [];
        }

        return Coupon::where('owner_user_id', $user->id)
            ->whereIn('source', ['referral_welcome', 'referral_reward'])
            ->where('is_active', true)
            ->where('used_count', 0)
            ->get()
            ->filter(fn (Coupon $c) => $c->isLive())
            ->map(fn (Coupon $c) => [
                'type'             => $c->type,
                'value'            => (float) $c->value,
                'max_discount'     => $c->max_discount !== null ? (float) $c->max_discount : null,
                'min_order_amount' => (float) $c->min_order_amount,
                'source'           => $c->source,
                'label'            => ReferralSetting::discountLabel($c->type, (float) $c->value, $c->max_discount !== null ? (float) $c->max_discount : null),
            ])
            ->values()
            ->all();
    }

    /** Single-use personal welcome coupon for a referee (null if none configured). */
    protected function mintWelcomeCoupon(User $referee): ?Coupon
    {
        $s = ReferralSetting::current();

        if ((float) $s->referee_discount_value <= 0) {
            return null;
        }

        return Coupon::create([
            'code'                 => $this->uniqueCode('WELCOME'),
            'source'               => 'referral_welcome',
            'owner_user_id'        => $referee->id,
            'description'          => 'Referral welcome discount',
            'type'                 => $s->referee_discount_type,
            'value'                => $s->referee_discount_value,
            'max_discount'         => $s->referee_max_discount,
            'min_order_amount'     => $s->referee_min_order_amount,
            'usage_limit'          => 1,
            'usage_limit_per_user' => 1,
            'used_count'           => 0,
            'is_active'            => true,
            'expires_at'           => $s->reward_validity_days ? now()->addDays($s->reward_validity_days) : null,
        ]);
    }

    /** Single-use personal reward coupon for a referrer. */
    protected function mintRewardCoupon(User $referrer): Coupon
    {
        $s = ReferralSetting::current();

        return Coupon::create([
            'code'                 => $this->uniqueCode('REWARD'),
            'source'               => 'referral_reward',
            'owner_user_id'        => $referrer->id,
            'description'          => 'Referral reward',
            'type'                 => $s->referrer_reward_type,
            'value'                => $s->referrer_reward_value,
            'max_discount'         => $s->referrer_max_discount,
            'min_order_amount'     => 0,
            'usage_limit'          => 1,
            'usage_limit_per_user' => 1,
            'used_count'           => 0,
            'is_active'            => true,
            'expires_at'           => $s->reward_validity_days ? now()->addDays($s->reward_validity_days) : null,
        ]);
    }

    /** Generate a coupon code that doesn't collide. */
    protected function uniqueCode(string $prefix): string
    {
        do {
            $code = $prefix.Str::upper(Str::random(6));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
