<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }} — National Olympiad Hunt</title>
    <style>
        :root {
            --ink: #0A1024; --ink-2: #131C3D; --paper: #FBF6EC; --paper-2: #F3E9D6;
            --line: #E7D9BE; --saffron: #EE6A2C; --emerald: #168A66; --muted: #5B6373;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--paper); color: var(--ink);
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, sans-serif;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .toolbar {
            max-width: 720px; margin: 1.5rem auto 0; display: flex; justify-content: flex-end; gap: .6rem; padding: 0 1.2rem;
        }
        .btn {
            border: 0; cursor: pointer; font-weight: 700; font-size: .85rem; padding: .6rem 1.2rem;
            border-radius: 10px; color: #fff; background: var(--ink); text-decoration: none; display: inline-block;
        }
        .btn.print { background: var(--saffron); }
        .sheet {
            max-width: 720px; margin: 1rem auto 3rem; background: #fff; border: 1px solid var(--line);
            border-radius: 16px; overflow: hidden; box-shadow: 0 30px 60px -30px rgba(10,16,36,.3);
        }
        .head {
            background: linear-gradient(135deg, var(--ink), var(--ink-2)); color: #fff;
            padding: 1.8rem 2rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
        }
        .brand { font-family: "Fraunces", Georgia, serif; font-weight: 700; font-size: 1.45rem; line-height: 1.1; }
        .brand small { display: block; font-family: "Plus Jakarta Sans", sans-serif; font-weight: 500; font-size: .72rem; opacity: .7; margin-top: .35rem; letter-spacing: .04em; }
        .rcpt-tag { text-align: right; font-size: .75rem; opacity: .85; }
        .rcpt-tag strong { display: block; font-family: "Space Grotesk", monospace; font-size: 1.05rem; letter-spacing: .04em; margin-top: .15rem; }
        .status {
            display: inline-block; margin-top: .5rem; font-size: .7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; padding: .25rem .6rem; border-radius: 999px; background: var(--emerald); color: #fff;
        }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 1.4rem 2rem; padding: 1.8rem 2rem; border-bottom: 1px dashed var(--line); }
        .meta h4 { margin: 0 0 .5rem; font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .meta p { margin: 0; font-size: .9rem; line-height: 1.5; }
        .meta .mono { font-family: "Space Grotesk", monospace; font-size: .82rem; color: var(--muted); word-break: break-all; }
        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); padding: 1rem 2rem .6rem; background: var(--paper-2); }
        thead th.r, tbody td.r { text-align: right; }
        tbody td { padding: .85rem 2rem; font-size: .92rem; border-bottom: 1px solid #F1EAD9; }
        tbody td.amt { font-family: "Space Grotesk", monospace; }
        .totals { padding: 1.2rem 2rem 2rem; }
        .totals .row { display: flex; justify-content: space-between; align-items: center; padding: .35rem 0; font-size: .92rem; color: var(--muted); }
        .totals .row.grand { border-top: 2px solid var(--ink); margin-top: .6rem; padding-top: .9rem; }
        .totals .row.grand span { font-weight: 700; color: var(--ink); font-size: 1rem; }
        .totals .row.grand strong { font-family: "Space Grotesk", monospace; font-size: 1.55rem; color: var(--ink); }
        .foot { padding: 1.4rem 2rem 2rem; border-top: 1px dashed var(--line); font-size: .76rem; color: var(--muted); line-height: 1.6; }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .sheet { margin: 0; border: 0; box-shadow: none; border-radius: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.payments') }}" class="btn">← Back</a>
        <button class="btn print" onclick="window.print()">Print receipt</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="brand">
                National Olympiad Hunt
                <small>Payment Receipt</small>
            </div>
            <div class="rcpt-tag">
                Receipt No.
                <strong>NEO-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</strong>
                <span class="status">{{ strtoupper($payment->status) }}</span>
            </div>
        </div>

        <div class="meta">
            <div>
                <h4>Billed To</h4>
                <p><strong>{{ $payment->user?->name ?? '—' }}</strong></p>
                <p class="mono">{{ $payment->user?->email }}</p>
                @if($payment->user?->phone)<p class="mono">{{ $payment->user->phone }}</p>@endif
            </div>
            <div>
                <h4>Payment Details</h4>
                <p>Date: {{ optional($payment->paid_at ?? $payment->created_at)->format('d M Y, h:i A') }}</p>
                <p>Method: {{ ucfirst($payment->method ?? $payment->gateway) }}</p>
                @if($payment->is_manual)
                    <p>Recorded: Manual entry</p>
                    @if($payment->manual_reference)<p class="mono">Reference: {{ $payment->manual_reference }}</p>@endif
                @endif
                <p class="mono">Order: {{ $payment->razorpay_order_id ?? '—' }}</p>
                <p class="mono">Payment: {{ $payment->razorpay_payment_id ?? '—' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="r">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payment->enrollments as $enrollment)
                    <tr>
                        <td>{{ $enrollment->exam?->name ?? 'Olympiad enrolment' }}</td>
                        <td class="r amt">₹{{ number_format((float) $enrollment->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>Olympiad exam enrolment</td>
                        <td class="r amt">₹{{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div class="row grand">
                <span>Total Paid</span>
                <strong>₹{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</strong>
            </div>
        </div>

        <div class="foot">
            This is a computer-generated receipt and does not require a signature.
            For any queries regarding this payment, contact support quoting Receipt No.
            <strong>NEO-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</strong>.
        </div>
    </div>
</body>
</html>
