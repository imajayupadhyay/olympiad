<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Coupon::withCount('redemptions')->latest();

        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.strtoupper(trim($request->search)).'%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $status = $request->status;
            $query->where(function ($q) use ($status) {
                if ($status === 'disabled') {
                    $q->where('is_active', false);
                } elseif ($status === 'expired') {
                    $q->where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<', now());
                } elseif ($status === 'active') {
                    $q->where('is_active', true)
                      ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
                }
            });
        }

        $coupons = $query->paginate(15)->withQueryString()->through(fn (Coupon $c) => [
            'id'                   => $c->id,
            'code'                 => $c->code,
            'description'          => $c->description,
            'type'                 => $c->type,
            'value'                => (float) $c->value,
            'max_discount'         => $c->max_discount !== null ? (float) $c->max_discount : null,
            'min_order_amount'     => (float) $c->min_order_amount,
            'usage_limit'          => $c->usage_limit,
            'usage_limit_per_user' => $c->usage_limit_per_user,
            'used_count'           => $c->used_count,
            'redemptions_count'    => $c->redemptions_count,
            'starts_at'            => optional($c->starts_at)->toDateString(),
            'expires_at'           => optional($c->expires_at)->toDateString(),
            'is_active'            => $c->is_active,
            'status'               => $c->status,
        ]);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'filters' => $request->only(['search', 'type', 'status']),
            'totals'  => [
                'all'         => Coupon::count(),
                'active'      => Coupon::where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))->count(),
                'redemptions' => CouponRedemption::count(),
                'discount'    => (float) CouponRedemption::sum('discount_amount'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;

        Coupon::create($data);

        return back()->with('success', "Coupon {$data['code']} created.");
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validateData($request, $coupon->id));

        return back()->with('success', "Coupon {$coupon->code} updated.");
    }

    public function toggle(Request $request, Coupon $coupon)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $coupon->update(['is_active' => $request->is_active]);

        return back()->with('success', 'Coupon '.($request->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->code))]);

        $data = $request->validate([
            'code'                 => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'description'          => ['nullable', 'string', 'max:160'],
            'type'                 => ['required', Rule::in(['percentage', 'fixed'])],
            'value'                => ['required', 'numeric', 'min:0.01', $request->type === 'percentage' ? 'max:100' : 'max:1000000'],
            'max_discount'         => ['nullable', 'numeric', 'min:1'],
            'min_order_amount'     => ['nullable', 'numeric', 'min:0'],
            'usage_limit'          => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at'            => ['nullable', 'date'],
            'expires_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'            => ['boolean'],
        ], [
            'code.regex' => 'Use only letters, numbers, hyphen or underscore.',
            'value.max'  => $request->type === 'percentage' ? 'A percentage cannot exceed 100.' : 'Value is too large.',
        ]);

        // max_discount only meaningful for percentage coupons.
        if (($data['type'] ?? null) !== 'percentage') {
            $data['max_discount'] = null;
        }

        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }
}
