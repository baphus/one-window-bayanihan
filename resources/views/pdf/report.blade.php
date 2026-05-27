<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bayanihan Report</title>
    <style>
        body { font-family: sans-serif; font-size: 11pt; color: #333; }
        h1 { font-size: 18pt; margin-bottom: 4pt; }
        h2 { font-size: 14pt; margin-top: 20pt; margin-bottom: 8pt; border-bottom: 1px solid #ccc; padding-bottom: 4pt; }
        table { width: 100%; border-collapse: collapse; margin: 8pt 0; }
        th, td { text-align: left; padding: 4pt 8pt; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .kpi-grid { display: flex; flex-wrap: wrap; gap: 12pt; margin: 8pt 0; }
        .kpi-card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4pt; padding: 10pt; flex: 1; min-width: 140pt; }
        .kpi-value { font-size: 16pt; font-weight: bold; }
        .kpi-label { font-size: 8pt; color: #666; text-transform: uppercase; }
        .meta { font-size: 9pt; color: #666; margin-bottom: 16pt; }
    </style>
</head>
<body>
    <h1>System Performance Report</h1>
    <div class="meta">
        Generated: {{ $generatedAt ?? now() }} | By: {{ $generatedBy ?? 'System' }}
        @if(!empty($role)) | Role: {{ $role }} @endif
    </div>

    @if(!empty($kpis))
    <h2>Key Performance Indicators</h2>
    <table>
        <tr>
            <th>Metric</th>
            <th>Value</th>
        </tr>
        <tr><td>Total Referrals</td><td>{{ $kpis['totalReferrals'] ?? 0 }}</td></tr>
        <tr><td>Completed Referrals</td><td>{{ $kpis['completedReferrals'] ?? 0 }}</td></tr>
        <tr><td>Pending Referrals</td><td>{{ $kpis['pendingReferrals'] ?? 0 }}</td></tr>
        <tr><td>Processing Referrals</td><td>{{ $kpis['processingReferrals'] ?? 0 }}</td></tr>
        <tr><td>Rejected Referrals</td><td>{{ $kpis['rejectedReferrals'] ?? 0 }}</td></tr>
        <tr><td>Completion Rate</td><td>{{ $kpis['completionRate'] ?? 0 }}%</td></tr>
        <tr><td>Avg Completion Days</td><td>{{ $kpis['avgCompletionDays'] ?? 0 }}</td></tr>
    </table>
    @endif

    @if(!empty($overview))
    <h2>System Overview</h2>
    <table>
        <tr><td>Total Cases</td><td>{{ $overview['totalCases'] ?? 0 }}</td></tr>
        <tr><td>Open Cases</td><td>{{ $overview['openCases'] ?? 0 }}</td></tr>
        <tr><td>Closed Cases</td><td>{{ $overview['closedCases'] ?? 0 }}</td></tr>
        <tr><td>Total Referrals</td><td>{{ $overview['totalReferrals'] ?? 0 }}</td></tr>
        <tr><td>Pending Referrals</td><td>{{ $overview['pendingReferrals'] ?? 0 }}</td></tr>
        <tr><td>Active Agencies</td><td>{{ $overview['activeAgencies'] ?? 0 }}</td></tr>
    </table>
    @endif

    @if(!empty($referralStatusDistribution))
    <h2>Referral Status Distribution</h2>
    <table>
        <tr><th>Status</th><th>Count</th></tr>
        @foreach($referralStatusDistribution['labels'] ?? [] as $i => $label)
        <tr>
            <td>{{ $label }}</td>
            <td>{{ $referralStatusDistribution['data'][$i] ?? 0 }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    @if(!empty($agencyWorkload))
    <h2>Agency Workload</h2>
    <table>
        <tr><th>Agency</th><th>Referrals</th></tr>
        @foreach($agencyWorkload['labels'] ?? [] as $i => $label)
        <tr>
            <td>{{ $label }}</td>
            <td>{{ $agencyWorkload['data'][$i] ?? 0 }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    @if(!empty($agencyScorecard) && is_array($agencyScorecard))
    <h2>Agency Scorecard</h2>
    <table>
        <tr><th>Agency</th><th>Total</th><th>Completed</th><th>Pending</th><th>Rate</th><th>Avg Days</th></tr>
        @foreach($agencyScorecard as $score)
        <tr>
            <td>{{ $score['agency'] ?? 'N/A' }}</td>
            <td>{{ $score['total'] ?? 0 }}</td>
            <td>{{ $score['completed'] ?? 0 }}</td>
            <td>{{ $score['pending'] ?? 0 }}</td>
            <td>{{ $score['completionRate'] ?? 0 }}%</td>
            <td>{{ $score['avgDays'] ?? 0 }}</td>
        </tr>
        @endforeach
    </table>
    @endif
</body>
</html>
