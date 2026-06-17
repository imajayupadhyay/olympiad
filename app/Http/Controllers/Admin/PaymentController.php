<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Payment::with(['user:id,name,email,phone', 'enrollments.exam:id,name'])->latest();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('razorpay_order_id', 'like', "%{$s}%")
                  ->orWhere('razorpay_payment_id', 'like', "%{$s}%")
                  ->orWhere('id', $s)
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rows = $query->paginate(20)->withQueryString()->through(fn (Payment $p) => [
            'id'          => $p->id,
            'amount'      => (float) $p->amount,
            'currency'    => $p->currency,
            'status'      => $p->status,
            'gateway'     => $p->gateway,
            'method'      => $p->method,
            'order_id'    => $p->razorpay_order_id,
            'payment_id'  => $p->razorpay_payment_id,
            'created_at'  => $p->created_at,
            'paid_at'     => $p->paid_at,
            'student'     => $p->user ? ['name' => $p->user->name, 'email' => $p->user->email] : null,
            'exams'       => $p->enrollments->map(fn ($e) => $e->exam?->name)->filter()->values(),
        ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $rows,
            'filters'  => $request->only(['search', 'status', 'date_from', 'date_to']),
            'totals'   => [
                'collected' => (float) Payment::where('status', 'paid')->sum('amount'),
                'month'     => (float) Payment::where('status', 'paid')
                    ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
                'paid'      => Payment::where('status', 'paid')->count(),
                'pending'   => Payment::where('status', 'created')->count(),
                'failed'    => Payment::where('status', 'failed')->count(),
            ],
        ]);
    }

    /** Printable receipt for a completed payment. */
    public function receipt(Payment $payment): View|\Illuminate\Http\RedirectResponse
    {
        if ($payment->status !== 'paid') {
            return redirect()->route('admin.payments')->with('info', 'A receipt is only available for completed payments.');
        }

        $payment->load(['user', 'enrollments.exam:id,name']);

        return view('receipts.payment', ['payment' => $payment]);
    }

    public function refund($payment)
    {
        return back()->with('info', 'Refund processing coming soon.');
    }
}
