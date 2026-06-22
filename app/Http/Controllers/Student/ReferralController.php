<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\ReferralSetting;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referrals,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $settings = ReferralSetting::current();

        $made = $user->referralsMade()
            ->with('referee:id,name')
            ->latest()
            ->get();

        $threshold = max(1, (int) $settings->unlock_threshold);
        $progress  = $this->referrals->progressCount($user);   // clicks or qualified, per mode
        $rewarded  = Coupon::where('owner_user_id', $user->id)->where('source', 'referral_reward')->count();
        $toward    = $progress % $threshold;

        $rewards = Coupon::where('owner_user_id', $user->id)
            ->where('source', 'referral_reward')
            ->latest()
            ->get()
            ->map(fn (Coupon $c) => [
                'code'       => $c->code,
                'label'      => ReferralSetting::discountLabel($c->type, (float) $c->value, $c->max_discount !== null ? (float) $c->max_discount : null),
                'status'     => $c->used_count > 0 ? 'used' : ($c->isLive() ? 'available' : 'expired'),
                'expires_at' => optional($c->expires_at)->toDateString(),
            ]);

        return Inertia::render('Student/Referrals/Index', [
            'active' => (bool) $settings->is_active,
            'link'   => $user->referralLink(),
            'code'   => $user->referral_code,
            'program' => [
                'threshold'        => $threshold,
                'qualify_on'       => $settings->qualify_on,
                'referee_discount' => $settings->refereeDiscountLabel() ?: '—',
                'referrer_reward'  => $settings->referrerRewardLabel() ?: '—',
            ],
            'stats' => [
                'referred' => $made->count(),
                'progress' => $progress,
                'rewarded' => $rewarded,
                'threshold' => $threshold,
                'toward'   => $toward,
                'remaining' => $threshold - $toward,
                'percent'  => min(100, (int) round($toward / $threshold * 100)),
                'mode'     => $settings->qualify_on,
            ],
            'rewards'   => $rewards,
            'referrals' => $made->map(fn ($r) => [
                'name'       => $r->referee?->name ? $this->maskName($r->referee->name) : 'A student',
                'status'     => $r->status,
                'created_at' => optional($r->created_at)->toDateString(),
            ])->values(),
        ]);
    }

    /** Show only the first name + an initial for privacy. */
    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));

        return $parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');
    }
}
