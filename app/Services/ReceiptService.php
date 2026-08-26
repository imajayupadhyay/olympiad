<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function __construct(protected ReceiptNumberService $numbers) {}

    public function issueForPayment(Payment $payment, ?User $admin = null): Receipt
    {
        if ($payment->receipt) {
            return $payment->receipt;
        }

        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => 'A receipt can only be issued for a completed payment.',
            ]);
        }

        return DB::transaction(function () use ($payment, $admin): Receipt {
            /** @var Payment $locked */
            $locked = Payment::query()
                ->with(['user.classLevel', 'enrollments.exam'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->receipt) {
                return $locked->receipt;
            }

            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => 'A receipt can only be issued for a completed payment.',
                ]);
            }

            $issuedAt = $locked->paid_at ?? $locked->created_at ?? now();
            $reservation = $this->numbers->reserve($issuedAt);
            /** @var ReceiptSetting $settings */
            $settings = $reservation['settings'];
            $snapshot = $this->snapshot($locked, $settings);

            return Receipt::create([
                'payment_id' => $locked->id,
                'receipt_number' => $reservation['receipt_number'],
                'series' => ReceiptNumberService::SERIES,
                'financial_year' => $reservation['financial_year'],
                'sequence_number' => $reservation['sequence_number'],
                'issued_at' => $issuedAt,
                'created_by_admin_id' => $admin?->id,
                'company_snapshot' => $snapshot['company'],
                'customer_snapshot' => $snapshot['customer'],
                'payment_snapshot' => $snapshot['payment'],
                'line_items' => $snapshot['line_items'],
                'totals' => $snapshot['totals'],
            ]);
        });
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return Collection<int, Receipt>
     */
    public function issueForPayments(Collection $payments, ?User $admin = null): Collection
    {
        return $payments
            ->sortBy(fn (Payment $payment) => sprintf(
                '%012d-%012d',
                optional($payment->paid_at ?? $payment->created_at)->timestamp ?? 0,
                $payment->id,
            ))
            ->map(fn (Payment $payment) => $this->issueForPayment($payment, $admin))
            ->values();
    }

    public function paidPaymentsQuery(array $filters = []): Builder
    {
        $query = Payment::query()
            ->with([
                'receipt',
                'user:id,name,email,phone,class_level_id,school,school_address,city,pincode,state',
                'user.classLevel:id,label',
                'enrollments.exam:id,name,exam_code',
            ])
            ->where('status', 'paid');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $query) use ($search): void {
                $query->where('razorpay_order_id', 'like', "%{$search}%")
                    ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                    ->orWhere('manual_reference', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('receipt', fn (Builder $receipt) => $receipt->where('receipt_number', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['source'])) {
            $source = $filters['source'];
            $query->where(fn (Builder $q) => $source === 'checkout'
                ? $q->where('source', 'checkout')->orWhereNull('source')
                : $q->where('source', $source));
        }

        if (! empty($filters['method'])) {
            $method = $filters['method'];
            $query->where(fn (Builder $q) => $q->where('method', $method)->orWhere('gateway', $method));
        }

        if (! empty($filters['exam_id'])) {
            $examId = (int) $filters['exam_id'];
            $query->where(function (Builder $query) use ($examId): void {
                $query->whereHas('enrollments', fn (Builder $enrollment) => $enrollment->where('exam_id', $examId))
                    ->orWhereJsonContains('notes->exam_ids', $examId);
            });
        }

        if (! empty($filters['receipt_status'])) {
            $filters['receipt_status'] === 'issued'
                ? $query->whereHas('receipt')
                : $query->whereDoesntHave('receipt');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate(DB::raw('COALESCE(paid_at, created_at)'), '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate(DB::raw('COALESCE(paid_at, created_at)'), '<=', $filters['date_to']);
        }

        return $query
            ->orderByRaw('COALESCE(paid_at, created_at) desc')
            ->orderByDesc('id');
    }

    public function paymentRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'gross_amount' => (float) ($payment->gross_amount ?: $payment->amount),
            'discount_amount' => (float) $payment->discount_amount,
            'currency' => $payment->currency,
            'source' => $payment->source ?: 'checkout',
            'source_label' => $payment->sourceLabel(),
            'method' => $payment->method ?: $payment->gateway,
            'gateway' => $payment->gateway,
            'payment_id' => $payment->razorpay_payment_id,
            'order_id' => $payment->razorpay_order_id,
            'manual_reference' => $payment->manual_reference,
            'paid_at' => $payment->paid_at,
            'created_at' => $payment->created_at,
            'student' => $payment->user ? [
                'name' => $payment->user->name,
                'email' => $payment->user->email,
                'phone' => $payment->user->phone,
            ] : null,
            'class' => $payment->user?->classLevel?->label,
            'exams' => $this->examNames($payment),
            'receipt' => $payment->receipt ? [
                'id' => $payment->receipt->id,
                'receipt_number' => $payment->receipt->receipt_number,
                'issued_at' => $payment->receipt->issued_at,
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, Receipt>  $receipts
     */
    public function summary(Collection $receipts): array
    {
        return [
            'count' => $receipts->count(),
            'gross_amount' => $this->sumReceiptTotals($receipts, 'gross_amount'),
            'discount_amount' => $this->sumReceiptTotals($receipts, 'discount_amount'),
            'taxable_amount' => $this->sumReceiptTotals($receipts, 'taxable_amount'),
            'cgst_amount' => $this->sumReceiptTotals($receipts, 'cgst_amount'),
            'sgst_amount' => $this->sumReceiptTotals($receipts, 'sgst_amount'),
            'igst_amount' => $this->sumReceiptTotals($receipts, 'igst_amount'),
            'tax_amount' => $this->sumReceiptTotals($receipts, 'tax_amount'),
            'invoice_total' => $this->sumReceiptTotals($receipts, 'invoice_total'),
            'amount_paid' => $this->sumReceiptTotals($receipts, 'amount_paid'),
            'balance_amount' => $this->sumReceiptTotals($receipts, 'balance_amount'),
        ];
    }

    /**
     * @return array{company:array<string,mixed>, customer:array<string,mixed>, payment:array<string,mixed>, line_items:array<int,array<string,mixed>>, totals:array<string,mixed>}
     */
    private function snapshot(Payment $payment, ReceiptSetting $settings): array
    {
        $lineItems = $this->lineItems($payment, $settings);

        $totals = [
            'gross_amount' => $this->sum($lineItems, 'gross_amount'),
            'discount_amount' => $this->sum($lineItems, 'discount_amount'),
            'taxable_amount' => $this->sum($lineItems, 'taxable_amount'),
            'cgst_amount' => $this->sum($lineItems, 'cgst_amount'),
            'sgst_amount' => $this->sum($lineItems, 'sgst_amount'),
            'igst_amount' => $this->sum($lineItems, 'igst_amount'),
            'tax_amount' => $this->sum($lineItems, 'tax_amount'),
            'invoice_total' => $this->sum($lineItems, 'line_total'),
            'amount_paid' => round((float) $payment->amount, 2),
        ];
        $totals['balance_amount'] = round($totals['invoice_total'] - $totals['amount_paid'], 2);

        return [
            'company' => $settings->renderCompanyPayload(),
            'customer' => [
                'name' => $payment->user?->name,
                'email' => $payment->user?->email,
                'phone' => $payment->user?->phone,
                'school' => $payment->user?->school,
                'address' => $payment->user?->school_address,
                'city' => $payment->user?->city,
                'pincode' => $payment->user?->pincode,
                'state' => $payment->user?->state,
                'class' => $payment->user?->classLevel?->label,
            ],
            'payment' => [
                'id' => $payment->id,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway,
                'method' => $payment->method ?: $payment->gateway,
                'source' => $payment->source ?: 'checkout',
                'source_label' => $payment->sourceLabel(),
                'razorpay_order_id' => $payment->razorpay_order_id,
                'razorpay_payment_id' => $payment->razorpay_payment_id,
                'manual_reference' => $payment->manual_reference,
                'is_manual' => (bool) $payment->is_manual,
                'paid_at' => optional($payment->paid_at)->toIso8601String(),
                'created_at' => optional($payment->created_at)->toIso8601String(),
            ],
            'line_items' => $lineItems,
            'totals' => $totals,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(Payment $payment, ReceiptSetting $settings): array
    {
        $rawItems = $this->rawLineItems($payment);
        $grossTotal = round((float) ($payment->gross_amount ?: $rawItems->sum('gross_amount') ?: $payment->amount), 2);
        $discountTotal = min(round((float) $payment->discount_amount, 2), $grossTotal);
        $rawTotal = round((float) $rawItems->sum('gross_amount'), 2);

        if ($rawItems->isEmpty()) {
            $rawItems = collect([[
                'description' => $settings->service_description,
                'exam_code' => null,
                'gross_amount' => $grossTotal,
            ]]);
            $rawTotal = $grossTotal;
        }

        $lines = [];
        $remainingGross = $grossTotal;
        $remainingDiscount = $discountTotal;
        $count = $rawItems->count();

        foreach ($rawItems->values() as $index => $item) {
            $basis = $rawTotal > 0 ? ((float) $item['gross_amount'] / $rawTotal) : (1 / max(1, $count));
            $gross = $index === $count - 1 ? $remainingGross : round($grossTotal * $basis, 2);
            $discount = $index === $count - 1 ? $remainingDiscount : round($discountTotal * $basis, 2);
            $gross = max(0, $gross);
            $discount = min(max(0, $discount), $gross);
            $net = round($gross - $discount, 2);
            $tax = $this->taxFor($net, $settings, $payment->user);

            $lines[] = [
                'description' => $item['description'] ?: $settings->service_description,
                'exam_code' => $item['exam_code'] ?? null,
                'hsn_sac' => $settings->hsn_sac,
                'quantity' => 1,
                'unit' => 'Service',
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'taxable_amount' => $tax['taxable_amount'],
                'gst_rate' => (float) $settings->gst_rate,
                'cgst_rate' => $tax['cgst_rate'],
                'sgst_rate' => $tax['sgst_rate'],
                'igst_rate' => $tax['igst_rate'],
                'cgst_amount' => $tax['cgst_amount'],
                'sgst_amount' => $tax['sgst_amount'],
                'igst_amount' => $tax['igst_amount'],
                'tax_amount' => $tax['tax_amount'],
                'line_total' => $tax['line_total'],
                'tax_type' => $tax['tax_type'],
            ];

            $remainingGross = round($remainingGross - $gross, 2);
            $remainingDiscount = round($remainingDiscount - $discount, 2);
        }

        return $lines;
    }

    /**
     * @return Collection<int, array{description:string, exam_code:?string, gross_amount:float}>
     */
    private function rawLineItems(Payment $payment): Collection
    {
        if ($payment->enrollments->isNotEmpty()) {
            return $payment->enrollments
                ->filter(fn ($enrollment) => $enrollment->exam !== null)
                ->map(fn ($enrollment) => [
                    'description' => $enrollment->exam->name ?? 'Olympiad enrolment',
                    'exam_code' => $enrollment->exam->exam_code ?? null,
                    'gross_amount' => (float) $enrollment->amount,
                ])
                ->values();
        }

        $examIds = collect(data_get($payment->notes, 'exam_ids', []))->filter()->unique()->values();
        if ($examIds->isEmpty()) {
            return collect();
        }

        return Exam::query()
            ->whereIn('id', $examIds)
            ->get(['id', 'name', 'exam_code', 'fee_amount'])
            ->map(fn (Exam $exam) => [
                'description' => $exam->name,
                'exam_code' => $exam->exam_code,
                'gross_amount' => (float) $exam->fee_amount,
            ])
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function examNames(Payment $payment): array
    {
        $names = $payment->enrollments
            ->pluck('exam.name')
            ->filter()
            ->values();

        if ($names->isNotEmpty()) {
            return $names->all();
        }

        $examIds = collect(data_get($payment->notes, 'exam_ids', []))->filter()->unique()->values();
        if ($examIds->isEmpty()) {
            return [];
        }

        return Exam::query()
            ->whereIn('id', $examIds)
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array{taxable_amount:float, tax_amount:float, cgst_rate:float, sgst_rate:float, igst_rate:float, cgst_amount:float, sgst_amount:float, igst_amount:float, line_total:float, tax_type:string}
     */
    private function taxFor(float $amountAfterDiscount, ReceiptSetting $settings, ?User $student): array
    {
        $rate = max(0, round((float) $settings->gst_rate, 2));

        if ($rate <= 0) {
            return [
                'taxable_amount' => $amountAfterDiscount,
                'tax_amount' => 0,
                'cgst_rate' => 0,
                'sgst_rate' => 0,
                'igst_rate' => 0,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => 0,
                'line_total' => $amountAfterDiscount,
                'tax_type' => 'none',
            ];
        }

        if ($settings->prices_include_gst) {
            $lineTotal = $amountAfterDiscount;
            $taxable = round($lineTotal / (1 + ($rate / 100)), 2);
            $taxAmount = round($lineTotal - $taxable, 2);
        } else {
            $taxable = $amountAfterDiscount;
            $taxAmount = round($taxable * ($rate / 100), 2);
            $lineTotal = round($taxable + $taxAmount, 2);
        }

        if ($this->isInterstate($settings, $student)) {
            return [
                'taxable_amount' => $taxable,
                'tax_amount' => $taxAmount,
                'cgst_rate' => 0,
                'sgst_rate' => 0,
                'igst_rate' => $rate,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'tax_type' => 'igst',
            ];
        }

        $halfRate = round($rate / 2, 2);
        $cgst = round($taxAmount / 2, 2);
        $sgst = round($taxAmount - $cgst, 2);

        return [
            'taxable_amount' => $taxable,
            'tax_amount' => $taxAmount,
            'cgst_rate' => $halfRate,
            'sgst_rate' => $halfRate,
            'igst_rate' => 0,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => 0,
            'line_total' => $lineTotal,
            'tax_type' => 'cgst_sgst',
        ];
    }

    private function isInterstate(ReceiptSetting $settings, ?User $student): bool
    {
        if (! $settings->state || ! $student?->state) {
            return false;
        }

        return mb_strtolower(trim($settings->state)) !== mb_strtolower(trim($student->state));
    }

    private function sum(array $rows, string $key): float
    {
        return round((float) collect($rows)->sum(fn ($row) => (float) ($row[$key] ?? 0)), 2);
    }

    private function sumReceiptTotals(Collection $receipts, string $key): float
    {
        return round((float) $receipts->sum(fn (Receipt $receipt) => (float) data_get($receipt->totals, $key, 0)), 2);
    }
}
