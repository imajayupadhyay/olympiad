<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReferralSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Referrals/Settings', [
            'settings' => ReferralSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active'                => ['boolean'],

            'referee_discount_type'    => ['required', Rule::in(['percentage', 'fixed'])],
            'referee_discount_value'   => ['required', 'numeric', 'min:0', $request->referee_discount_type === 'percentage' ? 'max:100' : 'max:1000000'],
            'referee_max_discount'     => ['nullable', 'numeric', 'min:1'],
            'referee_min_order_amount' => ['nullable', 'numeric', 'min:0'],

            'referrer_reward_type'     => ['required', Rule::in(['percentage', 'fixed'])],
            'referrer_reward_value'    => ['required', 'numeric', 'min:0', $request->referrer_reward_type === 'percentage' ? 'max:100' : 'max:1000000'],
            'referrer_max_discount'    => ['nullable', 'numeric', 'min:1'],

            'unlock_threshold'         => ['required', 'integer', 'min:1'],
            'qualify_on'               => ['required', Rule::in(['registration', 'first_paid_enrollment', 'link_click'])],
            'reward_validity_days'     => ['nullable', 'integer', 'min:1'],
        ]);

        // Caps only meaningful for percentage discounts.
        if ($data['referee_discount_type'] !== 'percentage') {
            $data['referee_max_discount'] = null;
        }
        if ($data['referrer_reward_type'] !== 'percentage') {
            $data['referrer_max_discount'] = null;
        }

        $data['is_active'] = $data['is_active'] ?? false;
        $data['referee_min_order_amount'] = $data['referee_min_order_amount'] ?? 0;

        ReferralSetting::current()->update($data);

        return back()->with('success', 'Referral program settings saved.');
    }
}
