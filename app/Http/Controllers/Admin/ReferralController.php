<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponRedemption;
use App\Models\Referral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Referral::with([
            'referrer:id,name,email,referral_code',
            'referee:id,name,email',
            'rewardCoupon:id,code',
        ])->latest();

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('referral_code', 'like', '%'.strtoupper($term).'%')
                    ->orWhereHas('referrer', fn ($r) => $r->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('referee', fn ($r) => $r->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $referrals = $query->paginate(15)->withQueryString()->through(fn (Referral $r) => [
            'id'            => $r->id,
            'referrer'      => $r->referrer ? ['name' => $r->referrer->name, 'email' => $r->referrer->email, 'code' => $r->referrer->referral_code] : null,
            'referee'       => $r->referee ? ['name' => $r->referee->name, 'email' => $r->referee->email] : null,
            'code'          => $r->referral_code,
            'status'        => $r->status,
            'reward_code'   => $r->rewardCoupon?->code,
            'qualified_at'  => optional($r->qualified_at)->toDateString(),
            'rewarded_at'   => optional($r->rewarded_at)->toDateString(),
            'created_at'    => optional($r->created_at)->toDateString(),
        ]);

        return Inertia::render('Admin/Referrals/Index', [
            'referrals' => $referrals,
            'filters'   => $request->only(['search', 'status']),
            'totals'    => [
                'all'         => Referral::count(),
                'qualified'   => Referral::whereIn('status', ['qualified', 'rewarded'])->count(),
                'rewarded'    => Referral::where('status', 'rewarded')->count(),
                'discount'    => (float) CouponRedemption::whereHas('coupon', fn ($q) => $q->where('source', 'referral_reward'))->sum('discount_amount'),
            ],
        ]);
    }
}
