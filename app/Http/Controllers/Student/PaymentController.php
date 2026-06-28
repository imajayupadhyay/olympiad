<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected ReferralService $referrals,
    ) {
    }

    /** Payment history. */
    public function index(Request $request): Response
    {
        $rows = $request->user()->payments()
            ->with('enrollments.exam:id,name')
            ->latest()
            ->get()
            ->map(fn (Payment $p) => [
                'id'        => $p->id,
                'amount'    => (float) $p->amount,
                'discount'  => (float) $p->discount_amount,
                'currency'  => $p->currency,
                'status'    => $p->status,
                'gateway'   => $p->gateway,
                'reference' => $p->razorpay_payment_id ?? $p->razorpay_order_id,
                'created_at'=> $p->created_at,
                'exams'     => $p->enrollments->map(fn ($e) => $e->exam?->name)->filter()->values(),
            ]);

        return Inertia::render('Student/Payments/Index', ['payments' => $rows]);
    }

    /** Checkout screen for a pending payment (order is created on Pay). */
    public function show(Request $request, Payment $payment): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($payment->user_id === $user->id, 403);

        if ($payment->status === 'paid') {
            return redirect()->route('student.payments')->with('info', 'This payment is already complete.');
        }

        $payment->load('coupon');

        // Auto-apply an earned referral reward (or welcome) when nothing is applied yet.
        if (! $payment->coupon_id) {
            if ($auto = $this->referrals->autoCouponFor($user, (float) $payment->gross_amount)) {
                $this->payments->applyCoupon($payment, $auto->code);
                $payment->load('coupon');
            }
        }

        $examIds = $payment->notes['exam_ids'] ?? [];
        $items = Exam::whereIn('id', $examIds)->get(['id', 'name', 'fee_amount'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'fee_amount' => (float) $e->fee_amount]);

        return Inertia::render('Student/Payments/Pay', [
            'payment' => [
                'id'       => $payment->id,
                'gross'    => (float) $payment->gross_amount,
                'discount' => (float) $payment->discount_amount,
                'amount'   => (float) $payment->amount,
                'currency' => $payment->currency,
                'coupon'   => $payment->coupon ? [
                    'code'   => $payment->coupon->code,
                    'type'   => $payment->coupon->type,
                    'source' => $payment->coupon->source,
                ] : null,
            ],
            'items'    => $items,
            'keyId'    => config('services.razorpay.key_id'),
            'prefill'  => [
                'name'    => $user->name,
                'email'   => $user->email,
                'contact' => $user->phone,
            ],
            // Refer & Earn share container — same state/feed as the exams + registration pages.
            'referral' => $this->referrals->shareState($user),
        ]);
    }

    /** Apply a coupon to a pending payment. */
    public function applyCoupon(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_if($payment->status === 'paid', 400);

        $data = $request->validate(['code' => 'required|string|max:50']);

        $result = $this->payments->applyCoupon($payment, $data['code']);

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    /** Remove an applied coupon. */
    public function removeCoupon(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_if($payment->status === 'paid', 400);

        $this->payments->removeCoupon($payment);

        return back()->with('info', 'Coupon removed.');
    }

    /** Create the Razorpay order for the final amount (or enrol free if a coupon covers it). */
    public function createOrder(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_if($payment->status === 'paid', 400);

        try {
            $result = $this->payments->openOrder($payment);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['status' => 'error', 'message' => 'Could not start the payment. Please try again.'], 500);
        }

        if ($result['status'] === 'coupon_dropped') {
            return response()->json(['status' => 'coupon_dropped', 'message' => $result['message']], 200);
        }

        if ($result['status'] === 'free') {
            $this->payments->enrollFreeByCoupon($payment);

            return response()->json([
                'status'   => 'free',
                'redirect' => route('student.exams'),
            ]);
        }

        return response()->json([
            'status'   => 'ok',
            'order_id' => $result['order_id'],
            'amount'   => $result['amount_paise'],
            'currency' => $result['currency'],
            'key_id'   => $result['key_id'],
            'prefill'  => [
                'name'    => $request->user()->name,
                'email'   => $request->user()->email,
                'contact' => $request->user()->phone,
            ],
        ]);
    }

    /** Verify the signed Razorpay checkout response, then enrol. */
    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id'   => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        abort_unless($data['razorpay_order_id'] === $payment->razorpay_order_id, 400);

        try {
            $this->payments->verifyAndEnroll($payment, $data['razorpay_payment_id'], $data['razorpay_signature']);
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            return redirect()->route('student.payments.show', $payment)
                ->with('error', 'Payment verification failed. Please try again.');
        }

        return redirect()->route('student.exams')
            ->with('success', 'Payment successful — you are now enrolled. 🎉');
    }
}
