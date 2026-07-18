<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Report</title>
    <style>
        @page { margin: 24px 24px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0A1024; font-family: "DejaVu Sans", sans-serif; font-size: 8px; }
        .header { padding: 14px 16px; color: #fff; background: #0A1024; }
        .header h1 { margin: 0 0 3px; font-size: 18px; }
        .header p { margin: 0; color: #E7D9BE; font-size: 8px; }
        .summary { width: 100%; margin: 10px 0; border-collapse: separate; border-spacing: 6px 0; }
        .summary td { width: 20%; padding: 8px; border: 1px solid #E7D9BE; background: #FBF6EC; }
        .summary small { display: block; color: #5B6373; text-transform: uppercase; }
        .summary strong { display: block; margin-top: 2px; font-size: 13px; }
        .filters { margin: 0 0 10px; padding: 7px 9px; border-left: 3px solid #EE6A2C; background: #F3E9D6; line-height: 1.45; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data th { padding: 6px 5px; color: #fff; background: #131C3D; font-size: 7px; text-align: left; text-transform: uppercase; }
        table.data td { padding: 6px 5px; border-bottom: 1px solid #E7D9BE; vertical-align: top; overflow-wrap: break-word; }
        table.data tbody tr:nth-child(even) { background: #FBF6EC; }
        .name { font-weight: bold; }
        .muted { color: #5B6373; }
        .paid { color: #168A66; font-weight: bold; }
        .unpaid { color: #C9501A; font-weight: bold; }
        .footer { position: fixed; right: 0; bottom: -18px; left: 0; color: #5B6373; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>National Olympiad Hunt - Student Report</h1>
        <p>Generated {{ $generatedAt->format('d M Y, h:i A') }} &middot; {{ number_format($summary['matched']) }} matching students</p>
    </div>

    <table class="summary">
        <tr>
            <td><small>Matched</small><strong>{{ number_format($summary['matched']) }}</strong></td>
            <td><small>Paid</small><strong>{{ number_format($summary['paid']) }}</strong></td>
            <td><small>Unpaid</small><strong>{{ number_format($summary['unpaid']) }}</strong></td>
            <td><small>Enrolled</small><strong>{{ number_format($summary['enrolled']) }}</strong></td>
            <td><small>Collected</small><strong>INR {{ number_format($summary['collected'], 2) }}</strong></td>
        </tr>
    </table>

    <div class="filters"><strong>Filters:</strong> {{ implode(' | ', $filterLabels) }}</div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">ID</th>
                <th style="width:13%">Student</th>
                <th style="width:15%">Contact</th>
                <th style="width:8%">Class</th>
                <th style="width:14%">School / Location</th>
                <th style="width:22%">Active Olympiads</th>
                <th style="width:10%">Payment</th>
                <th style="width:8%">Account</th>
                <th style="width:8%">Joined</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td><span class="name">{{ $row['name'] }}</span></td>
                    <td>{{ $row['email'] }}<br><span class="muted">{{ $row['phone'] ?: '-' }}</span></td>
                    <td>{{ $row['class'] ?: '-' }}</td>
                    <td>{{ $row['school'] ?: '-' }}<br><span class="muted">{{ collect([$row['city'], $row['state']])->filter()->implode(', ') ?: '-' }}</span></td>
                    <td>{{ collect($row['olympiads'])->pluck('name')->implode(', ') ?: 'None' }}</td>
                    <td class="{{ $row['payment_label'] === 'Paid' ? 'paid' : 'unpaid' }}">
                        {{ $row['payment_label'] }}<br>
                        <span class="muted">INR {{ number_format($row['paid_total'], 2) }}</span>
                    </td>
                    <td>{{ $row['is_active'] ? 'Active' : 'Disabled' }}</td>
                    <td>{{ $row['registered_at'] ? date('d M Y', strtotime($row['registered_at'])) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="padding:24px;text-align:center">No students matched these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">National Olympiad Hunt &middot; Confidential administrative report</div>
</body>
</html>
