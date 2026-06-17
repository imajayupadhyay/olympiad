<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
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
                'currency'  => $p->currency,
                'status'    => $p->status,
                'gateway'   => $p->gateway,
                'reference' => $p->razorpay_payment_id ?? $p->razorpay_order_id,
                'created_at'=> $p->created_at,
                'exams'     => $p->enrollments->map(fn ($e) => $e->exam?->name)->filter()->values(),
            ]);

        return Inertia::render('Student/Payments/Index', ['payments' => $rows]);
    }

    /** Razorpay checkout screen for a pending payment. */
    public function show(Request $request, Payment $payment): Response|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        abort_unless($payment->user_id === $user->id, 403);

        if ($payment->status === 'paid') {
            return redirect()->route('student.payments')->with('info', 'This payment is already complete.');
        }

        $examIds = $payment->notes['exam_ids'] ?? [];
        $items = Exam::whereIn('id', $examIds)->get(['id', 'name', 'fee_amount'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'fee_amount' => (float) $e->fee_amount]);

        return Inertia::render('Student/Payments/Pay', [
            'payment'  => ['id' => $payment->id, 'amount' => (float) $payment->amount, 'currency' => $payment->currency],
            'items'    => $items,
            'razorpay' => [
                'key_id'       => config('services.razorpay.key_id'),
                'order_id'     => $payment->razorpay_order_id,
                'amount_paise' => (int) round($payment->amount * 100),
                'currency'     => $payment->currency,
            ],
            'prefill'  => [
                'name'    => $user->name,
                'email'   => $user->email,
                'contact' => $user->phone,
            ],
        ]);
    }

    /** Verify the signed Razorpay checkout response, then enrol. */
    public function verify(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
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
