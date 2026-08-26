<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipts</title>
    <style>
        @page { margin: 26px 30px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.45; }
        .receipt { page-break-after: always; }
        .receipt:last-child { page-break-after: auto; }
        .top { width: 100%; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 14px; }
        .logo { width: 190px; max-height: 54px; object-fit: contain; display: block; margin-bottom: 8px; }
        .company h1 { margin: 0 0 4px; font-size: 18px; letter-spacing: .02em; }
        .company p { margin: 1px 0; color: #333; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0 0 7px; font-size: 17px; text-transform: uppercase; letter-spacing: .06em; }
        .doc-title p { margin: 2px 0; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .grid td { width: 50%; vertical-align: top; border: 1px solid #222; padding: 9px 10px; }
        .label { display: block; margin-bottom: 4px; font-size: 8px; color: #555; text-transform: uppercase; letter-spacing: .07em; }
        .strong { font-weight: bold; color: #111; }
        .muted { color: #555; }
        .lines { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6px; }
        .lines th { padding: 7px 6px; border: 1px solid #111; color: #111; background: #eee; font-size: 8px; text-align: left; text-transform: uppercase; }
        .lines td { padding: 7px 6px; border: 1px solid #333; vertical-align: top; overflow-wrap: break-word; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .totals { width: 45%; margin: 14px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 6px 8px; border: 1px solid #222; }
        .totals td:last-child { text-align: right; font-weight: bold; white-space: nowrap; }
        .totals .grand td { border-top: 2px solid #111; font-size: 12px; }
        .foot { margin-top: 18px; padding-top: 9px; border-top: 1px solid #444; color: #444; font-size: 8.5px; }
        .signature { margin-top: 22px; text-align: right; color: #222; }
    </style>
</head>
<body>
@php
    $renderCompany = $company ?? null;
@endphp
@foreach ($receipts as $receipt)
    @php
        $company = $renderCompany ?: ($receipt->company_snapshot ?? []);
        $customer = $receipt->customer_snapshot ?? [];
        $payment = $receipt->payment_snapshot ?? [];
        $lines = $receipt->line_items ?? [];
        $totals = $receipt->totals ?? [];
        $visible = $company['visible_fields'] ?? [];
        $show = fn (string $field): bool => in_array($field, $visible, true);
        $money = fn ($value): string => 'INR '.number_format((float) $value, 2);
        $issuedAt = $receipt->issued_at?->timezone(config('app.timezone'));
        $lineColumnCount = 7 + ($show('hsn_sac') ? 1 : 0) + ($show('tax_breakup') ? 3 : 0);
    @endphp

    <section class="receipt">
        <table class="top">
            <tr>
                <td class="company" style="width:58%; vertical-align:top;">
                    @if($show('logo') && ! empty($company['logo_data_uri']))
                        <img class="logo" src="{{ $company['logo_data_uri'] }}" alt="{{ $company['name'] ?? 'Company logo' }}">
                    @endif
                    <h1>{{ $company['name'] ?? 'National Olympiad Hunt' }}</h1>
                    @if($show('address') && ! empty($company['address']))<p>{{ $company['address'] }}</p>@endif
                    @if(! empty($company['state']) || ! empty($company['state_code']))
                        <p>State: {{ $company['state'] ?? '-' }}@if(! empty($company['state_code'])) ({{ $company['state_code'] }})@endif</p>
                    @endif
                    @if($show('gstin') && ! empty($company['gstin']))<p><span class="strong">GSTIN:</span> {{ $company['gstin'] }}</p>@endif
                    @if($show('email') && ! empty($company['email']))<p>Email: {{ $company['email'] }}</p>@endif
                    @if($show('phone') && ! empty($company['phone']))<p>Phone: {{ $company['phone'] }}</p>@endif
                    @if($show('website') && ! empty($company['website']))<p>Website: {{ $company['website'] }}</p>@endif
                </td>
                <td class="doc-title" style="width:42%; vertical-align:top;">
                    <h2>{{ ! empty($company['gstin']) ? 'Tax Invoice / Receipt' : 'Payment Receipt' }}</h2>
                    <p><span class="label">Receipt No.</span><span class="strong">{{ $receipt->receipt_number }}</span></p>
                    <p><span class="label">Date</span>{{ $issuedAt?->format('d M Y, h:i A') }}</p>
                    <p><span class="label">Financial Year</span>{{ $receipt->financial_year }}</p>
                </td>
            </tr>
        </table>

        <table class="grid">
            <tr>
                <td>
                    <span class="label">Billed To</span>
                    <div class="strong">{{ $customer['name'] ?? '-' }}</div>
                    @if(! empty($customer['class']))<div>Class: {{ $customer['class'] }}</div>@endif
                    @if(! empty($customer['school']))<div>{{ $customer['school'] }}</div>@endif
                    @if(! empty($customer['city']) || ! empty($customer['state']))
                        <div>{{ collect([$customer['city'] ?? null, $customer['state'] ?? null, $customer['pincode'] ?? null])->filter()->implode(', ') }}</div>
                    @endif
                    @if($show('student_email') && ! empty($customer['email']))<div>Email: {{ $customer['email'] }}</div>@endif
                    @if($show('student_phone') && ! empty($customer['phone']))<div>Phone: {{ $customer['phone'] }}</div>@endif
                </td>
                <td>
                    <span class="label">Payment Details</span>
                    <div>Method: {{ ucfirst(str_replace('_', ' ', $payment['method'] ?? '-')) }}</div>
                    <div>Source: {{ $payment['source_label'] ?? '-' }}</div>
                    @if($show('payment_ids'))
                        @if(! empty($payment['razorpay_order_id']))<div>Order ID: {{ $payment['razorpay_order_id'] }}</div>@endif
                        @if(! empty($payment['razorpay_payment_id']))<div>Payment ID: {{ $payment['razorpay_payment_id'] }}</div>@endif
                        @if(! empty($payment['manual_reference']))<div>Reference: {{ $payment['manual_reference'] }}</div>@endif
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width:4%;" class="center">#</th>
                    <th style="width:25%;">Description</th>
                    @if($show('hsn_sac'))<th style="width:9%;">HSN/SAC</th>@endif
                    <th style="width:6%;" class="center">Qty</th>
                    <th style="width:11%;" class="num">Gross</th>
                    <th style="width:10%;" class="num">Discount</th>
                    <th style="width:12%;" class="num">Taxable</th>
                    @if($show('tax_breakup'))
                        <th style="width:10%;" class="num">CGST</th>
                        <th style="width:10%;" class="num">SGST</th>
                        <th style="width:10%;" class="num">IGST</th>
                    @endif
                    <th style="width:12%;" class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lines as $line)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>
                            <span class="strong">{{ $line['description'] ?? ($company['service_description'] ?? 'Online service') }}</span>
                        </td>
                        @if($show('hsn_sac'))<td>{{ $company['hsn_sac'] ?? ($line['hsn_sac'] ?? '-') ?: '-' }}</td>@endif
                        <td class="center">{{ $line['quantity'] ?? 1 }}</td>
                        <td class="num">{{ $money($line['gross_amount'] ?? 0) }}</td>
                        <td class="num">{{ $money($line['discount_amount'] ?? 0) }}</td>
                        <td class="num">{{ $money($line['taxable_amount'] ?? 0) }}</td>
                        @if($show('tax_breakup'))
                            <td class="num">{{ $money($line['cgst_amount'] ?? 0) }}<br><span class="muted">{{ number_format((float) ($line['cgst_rate'] ?? 0), 2) }}%</span></td>
                            <td class="num">{{ $money($line['sgst_amount'] ?? 0) }}<br><span class="muted">{{ number_format((float) ($line['sgst_rate'] ?? 0), 2) }}%</span></td>
                            <td class="num">{{ $money($line['igst_amount'] ?? 0) }}<br><span class="muted">{{ number_format((float) ($line['igst_rate'] ?? 0), 2) }}%</span></td>
                        @endif
                        <td class="num">{{ $money($line['line_total'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $lineColumnCount }}" class="center">No line items available.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr><td>Gross Amount</td><td>{{ $money($totals['gross_amount'] ?? 0) }}</td></tr>
            <tr><td>Discount</td><td>{{ $money($totals['discount_amount'] ?? 0) }}</td></tr>
            <tr><td>Taxable Value</td><td>{{ $money($totals['taxable_amount'] ?? 0) }}</td></tr>
            @if($show('tax_breakup'))
                <tr><td>CGST</td><td>{{ $money($totals['cgst_amount'] ?? 0) }}</td></tr>
                <tr><td>SGST</td><td>{{ $money($totals['sgst_amount'] ?? 0) }}</td></tr>
                <tr><td>IGST</td><td>{{ $money($totals['igst_amount'] ?? 0) }}</td></tr>
            @endif
            <tr class="grand"><td>Total Paid</td><td>{{ $money($totals['amount_paid'] ?? 0) }}</td></tr>
            @if(abs((float) ($totals['balance_amount'] ?? 0)) > 0.009)
                <tr><td>Balance</td><td>{{ $money($totals['balance_amount'] ?? 0) }}</td></tr>
            @endif
        </table>

        @if($show('footer_note') && ! empty($company['footer_note']))
            <div class="foot">{{ $company['footer_note'] }}</div>
        @endif
        <div class="signature">For {{ $company['name'] ?? 'National Olympiad Hunt' }}</div>
    </section>
@endforeach
</body>
</html>
