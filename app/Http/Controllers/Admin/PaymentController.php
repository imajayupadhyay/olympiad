<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\ReceiptSetting;
use App\Services\PaymentService;
use App\Services\ReceiptExportService;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Payment::with([
            'user:id,name,email,phone,class_level_id',
            'user.classLevel:id,label',
            'enrollments.exam:id,name',
            'receipt:id,payment_id,receipt_number,financial_year,sequence_number,issued_at',
            'recordedByAdmin:id,name',
        ])->latest();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('razorpay_order_id', 'like', "%{$s}%")
                    ->orWhere('razorpay_payment_id', 'like', "%{$s}%")
                    ->orWhere('manual_reference', 'like', "%{$s}%")
                    ->orWhere('id', $s)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Campaign attribution — 'checkout' also covers pre-attribution payments.
        if ($request->filled('source')) {
            $source = $request->source;
            $query->where(fn ($q) => $source === 'checkout'
                ? $q->where('source', 'checkout')->orWhereNull('source')
                : $q->where('source', $source));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(20)->withQueryString();

        // Unpaid "created" orders have no enrollments yet — the chosen exams live in
        // the payment's notes ({"exam_ids":[...]}). Resolve those names in one query.
        $noteExamIds = $payments->getCollection()
            ->flatMap(fn (Payment $p) => data_get($p->notes, 'exam_ids', []))
            ->filter()->unique()->values();

        $examNames = $noteExamIds->isNotEmpty()
            ? Exam::whereIn('id', $noteExamIds)->pluck('name', 'id')
            : collect();

        $settings = ReceiptSetting::current();

        $rows = $payments->through(fn (Payment $p) => [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'gateway' => $p->gateway,
            'source' => $p->source ?: 'checkout',
            'source_label' => $p->sourceLabel(),
            'method' => $p->method,
            'is_manual' => (bool) $p->is_manual,
            'manual_reference' => $p->manual_reference,
            'manual_note' => $p->manual_note,
            'recorded_by' => $p->recordedByAdmin?->only(['id', 'name']),
            'order_id' => $p->razorpay_order_id,
            'payment_id' => $p->razorpay_payment_id,
            'receipt_number' => $p->receipt?->displayNumber($settings),
            'created_at' => $p->created_at,
            'paid_at' => $p->paid_at,
            'student' => $p->user ? ['name' => $p->user->name, 'email' => $p->user->email] : null,
            'class' => $p->user?->classLevel?->label,
            'exams' => $p->enrollments->isNotEmpty()
                ? $p->enrollments->map(fn ($e) => $e->exam?->name)->filter()->values()
                : collect(data_get($p->notes, 'exam_ids', []))
                    ->map(fn ($id) => $examNames[$id] ?? null)->filter()->values(),
        ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $rows,
            'sources' => Payment::SOURCES,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to', 'source']),
            'totals' => [
                'collected' => (float) Payment::where('status', 'paid')->sum('amount'),
                'month' => (float) Payment::where('status', 'paid')
                    ->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
                'paid' => Payment::where('status', 'paid')->count(),
                'pending' => Payment::where('status', 'created')->count(),
                'failed' => Payment::where('status', 'failed')->count(),
                'marketing' => (float) Payment::where('status', 'paid')->where('source', 'marketing')->sum('amount'),
            ],
        ]);
    }

    /** Printable receipt for a completed payment. */
    public function receipt(
        Payment $payment,
        ReceiptService $receipts,
        ReceiptExportService $exports,
    ): HttpResponse|RedirectResponse {
        if ($payment->status !== 'paid') {
            return redirect()->route('admin.payments')->with('info', 'A receipt is only available for completed payments.');
        }

        $receipt = $receipts->issueForPayment($payment, request()->user());

        return $exports->receipts(collect([$receipt]));
    }

    public function reconcile(Request $request, Payment $payment, PaymentService $payments)
    {
        $data = $request->validate([
            'manual_reference' => ['nullable', 'string', 'max:100'],
            'manual_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($payment->status !== 'created') {
            return back()->with('info', 'Only pending payments can be manually reconciled.');
        }

        if (empty($payment->notes['exam_ids'] ?? [])) {
            return back()->with('error', 'This pending payment has no olympiads attached to enroll.');
        }

        $payments->reconcileManually(
            $payment,
            $request->user(),
            $data['manual_reference'] ?? null,
            $data['manual_note'] ?? null,
        );

        return back()->with('success', 'Payment marked paid and olympiad access granted.');
    }

    public function refund(Request $request, Payment $payment, PaymentService $payments)
    {
        $data = $request->validate([
            'manual_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! in_array($payment->status, ['created', 'paid'], true)) {
            return back()->with('info', 'Only pending or paid payments can be downgraded.');
        }

        $downgraded = $payments->downgrade(
            $payment,
            $request->user(),
            $data['manual_note'] ?? null,
        );

        $message = $downgraded->status === 'refunded'
            ? 'Payment downgraded to refunded and linked olympiad access removed.'
            : 'Pending payment downgraded and removed from pending verification.';

        return back()->with('success', $message);
    }
}
