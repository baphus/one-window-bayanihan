<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bayanihan Reports Export</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { margin: 0; font-size: 22px; color: #005288; }
        h2 { margin: 18px 0 8px; font-size: 13px; color: #005288; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #dbe3ea; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #eaf4fb; color: #0f3f5f; font-weight: bold; }
        .muted { color: #64748b; }
        .cards { width: 100%; margin: 12px 0; }
        .card { width: 24%; display: inline-block; border: 1px solid #dbe3ea; background: #f8fafc; padding: 8px; margin-right: 4px; }
        .value { font-size: 18px; font-weight: bold; color: #005288; }
        .label { text-transform: uppercase; font-size: 8px; color: #64748b; }
        .warning { border: 1px solid #f59e0b; background: #fffbeb; padding: 7px; margin: 8px 0; }
        .header { border-bottom: 3px solid #005288; padding-bottom: 8px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Performance Report</h1>
        <div class="muted">Schema {{ e($metadata['schema_version'] ?? '') }} | Scope {{ e($metadata['scope'] ?? '') }} | {{ e($metadata['filters']['from'] ?? '') }} to {{ e($metadata['filters']['to'] ?? '') }} ({{ e($metadata['timezone'] ?? '') }})</div>
        <div class="muted">Filters — Date basis: {{ e($metadata['filters']['date_scope'] ?? 'case_created_at') }} | Province: {{ e($metadata['filters']['province'] ?? 'All') }} | City: {{ e($metadata['filters']['city'] ?? 'All') }}</div>
        <div class="muted">Generated {{ e($metadata['generated_at_manila'] ?? '') }} Manila / {{ e($metadata['generated_at_utc'] ?? '') }} UTC by {{ e($metadata['generated_by'] ?? '') }}</div>
    </div>

    @foreach(($capWarnings ?? []) as $warning)
        <div class="warning">{{ e($warning) }}</div>
    @endforeach

    <div class="cards">
        <div class="card"><div class="value">{{ e($kpis['openCases'] ?? 0) }}</div><div class="label">Active Cases</div></div>
        <div class="card"><div class="value">{{ e($kpis['totalReferrals'] ?? 0) }}</div><div class="label">Referrals</div></div>
        <div class="card"><div class="value">{{ e($kpis['completionRate'] ?? 0) }}%</div><div class="label">Completion Rate</div></div>
        <div class="card"><div class="value">{{ e($kpis['avgResolutionDays'] ?? 0) }}</div><div class="label">Avg Resolution (days)</div></div>
    </div>
    <div class="cards">
        <div class="card"><div class="value">{{ e($kpis['totalCases'] ?? 0) }}</div><div class="label">Total Cases</div></div>
        <div class="card"><div class="value">{{ e($kpis['completedReferrals'] ?? 0) }}</div><div class="label">Completed</div></div>
        <div class="card"><div class="value">{{ e($kpis['pendingReferrals'] ?? 0) }}</div><div class="label">Pending</div></div>
        <div class="card"><div class="value">{{ e($kpis['forComplianceReferrals'] ?? 0) }}</div><div class="label">For Compliance</div></div>
    </div>

    <h2>Export Metadata</h2>
    <table><tbody>
        @foreach(($metadata['row_counts'] ?? []) as $key => $value)
            <tr><th>{{ e(ucwords(str_replace('_', ' ', $key))) }}</th><td>{{ e($value) }}</td></tr>
        @endforeach
        <tr><th>Detail Row Cap</th><td>{{ e($metadata['row_cap'] ?? '') }}</td></tr>
    </tbody></table>

    <h2>Referral Status</h2>
    <table><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
        @foreach(($referralStatusDistribution['labels'] ?? []) as $i => $label)
            <tr><td>{{ e($label) }}</td><td>{{ e($referralStatusDistribution['data'][$i] ?? 0) }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Case Status</h2>
    <table><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
        @foreach(($caseStatusDistribution['labels'] ?? []) as $i => $label)
            <tr><td>{{ e($label) }}</td><td>{{ e($caseStatusDistribution['data'][$i] ?? 0) }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Agency Scorecard</h2>
    <table><thead><tr><th>Agency</th><th>Total</th><th>Completed</th><th>Pending</th><th>Avg Days</th></tr></thead><tbody>
        @foreach(($agencyScorecard ?? []) as $row)
            <tr><td>{{ e($row['agency'] ?? '') }}</td><td>{{ e($row['total'] ?? 0) }}</td><td>{{ e($row['completed'] ?? 0) }}</td><td>{{ e($row['pending'] ?? 0) }}</td><td>{{ e($row['avg_days'] ?? '') }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Referral Aging</h2>
    <table><thead><tr><th>Age Range</th><th>Count</th></tr></thead><tbody>
        @foreach(($referralAging['labels'] ?? []) as $i => $label)
            <tr><td>{{ $label }}</td><td>{{ $referralAging['data'][$i] ?? 0 }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Cycle Time</h2>
    <table><thead><tr><th>Duration Range</th><th>Count</th></tr></thead><tbody>
        @foreach(($cycleTimeDistribution['labels'] ?? []) as $i => $label)
            <tr><td>{{ $label }}</td><td>{{ $cycleTimeDistribution['data'][$i] ?? 0 }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Categories and Issues</h2>
    <table><thead><tr><th>Type</th><th>Name</th><th>Count</th></tr></thead><tbody>
        @foreach(($categoryDistribution ?? []) as $row)
            <tr><td>Category</td><td>{{ $row['name'] ?? '' }}</td><td>{{ $row['count'] ?? 0 }}</td></tr>
        @endforeach
        @foreach(($caseIssueDistribution ?? []) as $row)
            <tr><td>Issue</td><td>{{ $row['name'] ?? '' }}</td><td>{{ $row['count'] ?? 0 }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Geography and Employment</h2>
    <table><thead><tr><th>Type</th><th>Label</th><th>Count</th></tr></thead><tbody>
        @foreach(($geographicDistribution['labels'] ?? []) as $i => $label)
            <tr><td>Province</td><td>{{ $label }}</td><td>{{ $geographicDistribution['data'][$i] ?? 0 }}</td></tr>
        @endforeach
        @foreach(($employmentDistribution['labels'] ?? []) as $i => $label)
            <tr><td>Employment Country</td><td>{{ $label }}</td><td>{{ $employmentDistribution['data'][$i] ?? 0 }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Monthly Trends</h2>
    <table><thead><tr><th>Period</th><th>Cases</th><th>Referrals</th></tr></thead><tbody>
        @php
            $caseTrendMap = collect($caseTrends['labels'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => $caseTrends['data'][$i] ?? 0]);
            $refTrendMap = collect($referralTrends['labels'] ?? [])->mapWithKeys(fn ($label, $i) => [$label => $referralTrends['data'][$i] ?? 0]);
            $periods = $caseTrendMap->keys()->merge($refTrendMap->keys())->unique()->sort()->values();
        @endphp
        @foreach($periods as $period)
            <tr><td>{{ $period }}</td><td>{{ $caseTrendMap[$period] ?? 0 }}</td><td>{{ $refTrendMap[$period] ?? 0 }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Top 10 Active Referrals by Business Risk</h2>
    <table><thead><tr><th>Case #</th><th>Agency</th><th>Status</th><th>Created</th><th>Age Days</th><th>Risk</th></tr></thead><tbody>
        @foreach(($topReferrals ?? []) as $row)
            <tr><td>{{ e($row->case_number ?? '') }}</td><td>{{ e($row->agency ?? '') }}</td><td>{{ e($row->status ?? '') }}</td><td>{{ e($row->created_at ?? '') }}</td><td>{{ e($row->age_days ?? '') }}</td><td>{{ e($row->risk_score ?? '') }}</td></tr>
        @endforeach
    </tbody></table>

    <h2>Top 10 Active Cases by Business Risk</h2>
    <table><thead><tr><th>Case #</th><th>Category</th><th>Issue</th><th>Status</th><th>Created</th><th>Age Days</th><th>Risk</th></tr></thead><tbody>
        @foreach(($topCases ?? []) as $row)
            <tr><td>{{ e($row->case_number ?? '') }}</td><td>{{ e($row->category ?? '') }}</td><td>{{ e($row->issue ?? '') }}</td><td>{{ e($row->status ?? '') }}</td><td>{{ e($row->created_at ?? '') }}</td><td>{{ e($row->age_days ?? '') }}</td><td>{{ e($row->risk_score ?? '') }}</td></tr>
        @endforeach
    </tbody></table>
</body>
</html>
