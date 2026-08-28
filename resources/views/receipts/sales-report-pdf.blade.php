<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        @page { margin: 22px 22px 28px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 8px; line-height: 1.35; }
        .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 9px; margin-bottom: 10px; }
        .logo { width: 165px; max-height: 42px; object-fit: contain; display: block; margin: 0 auto 5px; }
        h1, h2 { margin: 0; }
        h1 { font-size: 15px; }
        h2 { margin-top: 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; }
        .header p { margin: 2px 0; color: #333; }
        .meta { width: 100%; border-collapse: collapse; margin: 8px 0 10px; }
        .meta td { width: 20%; border: 1px solid #222; padding: 7px 8px; }
        .meta small { display: block; color: #555; text-transform: uppercase; letter-spacing: .06em; }
        .meta strong { display: block; margin-top: 2px; font-size: 11px; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data th { padding: 6px 4px; border: 1px solid #111; background: #eee; text-align: left; text-transform: uppercase; font-size: 7px; }
        table.data td { padding: 5px 4px; border: 1px solid #333; vertical-align: top; overflow-wrap: break-word; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #555; }
        .totals td { font-weight: bold; background: #f3f3f3; }
        .footer { position: fixed; right: 0; bottom: -17px; left: 0; color: #555; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@php
    $first = $receipts->first();
    $company = $company ?? ($first?->company_snapshot ?? []);
    $visible = $company['visible_fields'] ?? [];
    $show = fn (string $field): bool => in_array($field, $visible, true);
    $money = fn ($value): string => 'INR '.number_format((float) $value, 2);
    $companyState = $company['state_display']
        ?? \App\Support\GstStateCodes::format($company['state'] ?? null, $company['state_code'] ?? null);
    $from = date('d/m/Y', strtotime((string) $filters['date_from']));
    $to = date('d/m/Y', strtotime((string) $filters['date_to']));
@endphp

<div class="header">
    @if($show('logo') && ! empty($company['logo_data_uri']))
        <img class="logo" src="{{ $company['logo_data_uri'] }}" alt="{{ $company['name'] ?? 'Company logo' }}">
    @endif
    <h1>{{ $company['name'] ?? 'National Olympiad Hunt' }}</h1>
    @if($show('address') && ! empty($company['address']))<p>{{ $company['address'] }}</p>@endif
    <p>
        @if($show('gstin') && ! empty($company['gstin'])) GSTIN: {{ $company['gstin'] }} @endif
        @if(! empty($companyState)) &nbsp; State: {{ $companyState }} @endif
        @if($show('phone') && ! empty($company['phone'])) &nbsp; Phone: {{ $company['phone'] }} @endif
        @if($show('email') && ! empty($company['email'])) &nbsp; Email: {{ $company['email'] }} @endif
    </p>
    <h2>Sales Report</h2>
    <p>Duration: From {{ $from }} to {{ $to }} &nbsp; Generated: {{ $generatedAt->format('d M Y, h:i A') }}</p>
</div>

<table class="meta">
    <tr>
        <td><small>Tax Invoices</small><strong>{{ number_format($summary['count'] ?? 0) }}</strong></td>
        <td><small>Gross</small><strong>{{ $money($summary['gross_amount'] ?? 0) }}</strong></td>
        <td><small>Discount</small><strong>{{ $money($summary['discount_amount'] ?? 0) }}</strong></td>
        <td><small>GST</small><strong>{{ $money($summary['tax_amount'] ?? 0) }}</strong></td>
        <td><small>Total Paid</small><strong>{{ $money($summary['amount_paid'] ?? 0) }}</strong></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width:7%;">Date</th>
            <th style="width:14%;">Tax Invoice Number</th>
            <th style="width:15%;">Party Name</th>
            <th style="width:18%;">Service / Olympiad</th>
            <th style="width:7%;">HSN/SAC</th>
            <th style="width:8%;" class="num">Taxable</th>
            <th style="width:8%;" class="num">CGST</th>
            <th style="width:8%;" class="num">SGST</th>
            <th style="width:8%;" class="num">IGST</th>
            <th style="width:8%;" class="num">Total</th>
            <th style="width:9%;">Payment</th>
        </tr>
    </thead>
    <tbody>
        @forelse($receipts as $receipt)
            @php
                $customer = $receipt->customer_snapshot ?? [];
                $payment = $receipt->payment_snapshot ?? [];
                $totals = $receipt->totals ?? [];
                $lines = collect($receipt->line_items ?? []);
                $description = $lines->pluck('description')->filter()->implode(', ') ?: ($company['service_description'] ?? 'Online service');
                $hsn = ! empty($company['hsn_sac'])
                    ? $company['hsn_sac']
                    : ($lines->pluck('hsn_sac')->filter()->unique()->implode(', ') ?: '-');
                $receiptNumber = $receipt->displayNumber($numberingSettings ?? null);
            @endphp
            <tr>
                <td>{{ $receipt->issued_at?->format('d/m/Y') }}</td>
                <td>{{ $receiptNumber }}</td>
                <td>{{ $customer['name'] ?? '-' }}<br><span class="muted">{{ $customer['state'] ?? '' }}</span></td>
                <td>{{ $description }}</td>
                <td>{{ $hsn }}</td>
                <td class="num">{{ $money($totals['taxable_amount'] ?? 0) }}</td>
                <td class="num">{{ $money($totals['cgst_amount'] ?? 0) }}</td>
                <td class="num">{{ $money($totals['sgst_amount'] ?? 0) }}</td>
                <td class="num">{{ $money($totals['igst_amount'] ?? 0) }}</td>
                <td class="num">{{ $money($totals['amount_paid'] ?? 0) }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment['method'] ?? '-')) }}<br><span class="muted">{{ $payment['razorpay_payment_id'] ?? $payment['manual_reference'] ?? '' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="11" class="center" style="padding:22px;">No paid payments matched this date range.</td></tr>
        @endforelse
        <tr class="totals">
            <td colspan="5" class="num">Total Sale</td>
            <td class="num">{{ $money($summary['taxable_amount'] ?? 0) }}</td>
            <td class="num">{{ $money($summary['cgst_amount'] ?? 0) }}</td>
            <td class="num">{{ $money($summary['sgst_amount'] ?? 0) }}</td>
            <td class="num">{{ $money($summary['igst_amount'] ?? 0) }}</td>
            <td class="num">{{ $money($summary['amount_paid'] ?? 0) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">{{ $company['name'] ?? 'National Olympiad Hunt' }} - Sales Report</div>
</body>
</html>
