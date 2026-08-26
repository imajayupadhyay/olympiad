<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceiptIndexRequest;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\ReceiptExportService;
use App\Services\ReceiptNumberService;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function index(
        ReceiptIndexRequest $request,
        ReceiptService $receipts,
        ReceiptNumberService $numbers,
    ): Response {
        $filters = $request->filters();
        $baseQuery = $receipts->paidPaymentsQuery($filters);
        $paymentIds = (clone $baseQuery)->pluck('payments.id');
        $issuedReceipts = Receipt::query()->whereIn('payment_id', $paymentIds)->get(['payment_id', 'totals']);
        $receiptSummary = $receipts->summary($issuedReceipts);

        $payments = $baseQuery
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Payment $payment) => $receipts->paymentRow($payment));

        return Inertia::render('Admin/Receipts/Index', [
            'payments' => $payments,
            'filters' => $filters,
            'sources' => Payment::SOURCES,
            'methods' => $this->paymentMethods(),
            'exams' => Exam::query()
                ->orderByDesc('starts_at')
                ->orderBy('name')
                ->get(['id', 'name', 'exam_code']),
            'sequence' => $numbers->preview(),
            'summary' => [
                'paid_count' => $paymentIds->count(),
                'issued_count' => $issuedReceipts->count(),
                'unissued_count' => max(0, $paymentIds->count() - $issuedReceipts->count()),
                'total_collected' => (float) (clone $receipts->paidPaymentsQuery($filters))->sum('amount'),
                'taxable_amount' => $receiptSummary['taxable_amount'],
                'gst_amount' => $receiptSummary['tax_amount'],
            ],
            'exportLimits' => [
                'bulkPdf' => ReceiptExportService::MAX_BULK_RECEIPTS,
                'salesPdf' => ReceiptExportService::MAX_SALES_REPORT_ROWS,
            ],
        ]);
    }

    public function download(
        Payment $payment,
        ReceiptService $receipts,
        ReceiptExportService $exports,
    ): HttpResponse {
        $receipt = $receipts->issueForPayment($payment, request()->user());

        return $exports->receipts(collect([$receipt]));
    }

    public function bulk(
        Request $request,
        ReceiptService $receipts,
        ReceiptExportService $exports,
    ): HttpResponse {
        $ids = $this->selectedPaymentIds($request);
        abort_if($ids->isEmpty(), 422, 'Select at least one completed payment.');
        abort_if($ids->count() > ReceiptExportService::MAX_BULK_RECEIPTS, 422, 'Bulk receipt PDFs are limited to '.ReceiptExportService::MAX_BULK_RECEIPTS.' payments.');

        $payments = Payment::query()
            ->whereIn('id', $ids)
            ->where('status', 'paid')
            ->orderByRaw('COALESCE(paid_at, created_at) asc')
            ->orderBy('id')
            ->get();

        abort_if($payments->count() !== $ids->count(), 422, 'One or more selected payments are not completed.');

        $issued = $receipts->issueForPayments($payments, $request->user());

        return $exports->receipts($issued);
    }

    public function salesReport(
        SalesReportRequest $request,
        ReceiptService $receipts,
        ReceiptExportService $exports,
    ): HttpResponse {
        $filters = $request->filters();
        $query = $receipts->paidPaymentsQuery($filters);
        $count = (clone $query)->count();

        abort_if($count > ReceiptExportService::MAX_SALES_REPORT_ROWS, 422, 'Sales report PDFs are limited to '.ReceiptExportService::MAX_SALES_REPORT_ROWS.' payments. Narrow the date range and try again.');

        $payments = $query
            ->reorder()
            ->orderByRaw('COALESCE(paid_at, created_at) asc')
            ->orderBy('id')
            ->get();

        $issued = $receipts->issueForPayments($payments, $request->user());

        return $exports->salesReport($issued, $receipts->summary($issued), $filters);
    }

    private function selectedPaymentIds(Request $request)
    {
        return collect(explode(',', (string) $request->input('ids')))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }

    private function paymentMethods(): array
    {
        return Payment::query()
            ->where('status', 'paid')
            ->selectRaw('COALESCE(method, gateway) as payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->filter()
            ->values()
            ->all();
    }
}
