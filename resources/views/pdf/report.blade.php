@php
    $chartRenderer = app(\App\Services\Reports\PdfChartRenderer::class);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bayanihan System Performance Report</title>
    <style>
        @page { margin: 28px 32px; footer: page-footer; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.5; color: #1e293b; }

        /* ── Header ── */
        .report-header { border-bottom: 3px solid #005288; padding-bottom: 12px; margin-bottom: 14px; }
        .report-header h1 { margin: 0 0 2px; font-size: 18px; color: #005288; font-weight: 700; letter-spacing: -0.3px; }
        .report-header .subtitle { font-size: 11px; color: #475569; margin-bottom: 2px; }
        .report-header .meta { font-size: 7.5px; color: #64748b; }

        /* ── Section headings ── */
        h2 { margin: 18px 0 8px; font-size: 11px; color: #005288; font-weight: 700; border-bottom: 2px solid #005288; padding-bottom: 3px; }
        h3 { margin: 12px 0 6px; font-size: 10px; color: #334155; font-weight: 700; }

        /* ── KPI Dashboard ── */
        .kpi-grid { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 5px; margin: 10px 0; }
        .kpi-grid td { border: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); padding: 10px 10px; text-align: center; vertical-align: middle; border-radius: 4px; }
        .kpi-grid .kpi-value { font-size: 22px; font-weight: 800; color: #005288; line-height: 1.1; }
        .kpi-grid .kpi-label { font-size: 7px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-top: 3px; }
        .kpi-grid td.accent-blue { border-left: 4px solid #005288; }
        .kpi-grid td.accent-green { border-left: 4px solid #3f915f; }
        .kpi-grid td.accent-teal { border-left: 4px solid #0b7a75; }
        .kpi-grid td.accent-purple { border-left: 4px solid #9b51b0; }
        .kpi-grid td.accent-amber { border-left: 4px solid #b45309; }
        .kpi-grid td.accent-orange { border-left: 4px solid #d9663b; }
        .kpi-grid td.accent-red { border-left: 4px solid #dc2626; }

        /* ── Filter context ── */
        .filter-bar { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 10px; margin-bottom: 12px; font-size: 8px; color: #475569; border-radius: 4px; }
        .filter-bar strong { color: #1e293b; }
        .filter-tag { display: inline; background: #fff; border: 1px solid #cbd5e1; padding: 1px 5px; margin: 0 2px; font-size: 7px; font-weight: 600; border-radius: 2px; }

        /* ── Chart containers ── */
        .chart-container { margin: 10px 0; text-align: center; }
        .chart-title { font-size: 9px; font-weight: 700; color: #334155; margin-bottom: 4px; text-align: left; }
        .chart-subtitle { font-size: 7.5px; color: #64748b; margin-bottom: 6px; text-align: left; }
        .chart-row { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .chart-row td { vertical-align: top; padding: 0 6px; }

        /* ── Tables ── */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8px; }
        table.data th { background: #005288; color: #ffffff; font-weight: 700; padding: 5px 6px; text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.04em; }
        table.data td { padding: 4px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #f8fafc; }

        /* ── Legend ── */
        .legend { font-size: 7.5px; color: #64748b; margin-top: 4px; }
        .legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 3px; vertical-align: middle; }

        /* ── Risk rows ── */
        tr.risk-high td { background: #fef2f2 !important; }
        tr.risk-mid td { background: #fffbeb !important; }

        /* ── Misc ── */
        .warn-box { border: 1px solid #f59e0b; background: #fffbeb; padding: 5px 8px; margin: 6px 0; font-size: 7.5px; color: #92400e; border-radius: 3px; }
        .page-break { page-break-before: always; }
        .muted { color: #94a3b8; }
        .section-desc { font-size: 7.5px; color: #64748b; margin: -4px 0 8px; }
    </style>
</head>
<body>

    <!-- ═══════════════════ PAGE 1: EXECUTIVE DASHBOARD ═══════════════════ -->
    <div class="report-header">
        <h1>System Performance Report</h1>
        <div class="subtitle">
            Department of Migrant Workers &mdash; Region VII &bull;
            One Window Bayanihan Assistance Program
        </div>
        <div class="meta">
            <strong>{{ e($metadata['scope'] ?? '') }}</strong> scope &middot;
            {{ e($metadata['filters']['from'] ?? '') }} &ndash; {{ e($metadata['filters']['to'] ?? '') }}
            ({{ e($metadata['timezone'] ?? '') }}) &middot;
            Generated {{ e($metadata['generated_at_manila'] ?? '') }} by {{ e($metadata['generated_by'] ?? '') }}
        </div>
    </div>

    <div class="filter-bar">
        <strong>Active filters:</strong>
        <span class="filter-tag">Date scope: {{ e($metadata['filters']['date_scope'] ?? 'case_created_at') }}</span>
        <span class="filter-tag">Province: {{ e($metadata['filters']['province'] ?? 'All') }}</span>
        <span class="filter-tag">City: {{ e($metadata['filters']['city'] ?? 'All') }}</span>
    </div>

    @foreach(($capWarnings ?? []) as $warning)
        <div class="warn-box">⚠ {{ e($warning) }}</div>
    @endforeach

    <h2>Executive Summary</h2>
    <table class="kpi-grid">
        <tr>
            <td class="accent-blue"><div class="kpi-value">{{ number_format($kpis['totalCases'] ?? 0) }}</div><div class="kpi-label">Total Cases</div></td>
            <td class="accent-blue"><div class="kpi-value">{{ number_format($kpis['openCases'] ?? 0) }}</div><div class="kpi-label">Active Cases</div></td>
            <td class="accent-blue"><div class="kpi-value">{{ number_format($kpis['totalReferrals'] ?? 0) }}</div><div class="kpi-label">Total Referrals</div></td>
            <td class="accent-green"><div class="kpi-value">{{ number_format($kpis['completedReferrals'] ?? 0) }}</div><div class="kpi-label">Completed</div></td>
        </tr>
        <tr>
            <td class="accent-teal"><div class="kpi-value">{{ $kpis['completionRate'] ?? 0 }}%</div><div class="kpi-label">Completion Rate</div></td>
            <td class="accent-purple"><div class="kpi-value">{{ $kpis['avgResolutionDays'] ?? 0 }}d</div><div class="kpi-label">Avg Resolution</div></td>
            <td class="accent-amber"><div class="kpi-value">{{ number_format($kpis['pendingReferrals'] ?? 0) }}</div><div class="kpi-label">Pending</div></td>
            <td class="accent-red"><div class="kpi-value">{{ number_format($kpis['forComplianceReferrals'] ?? 0) }}</div><div class="kpi-label">For Compliance</div></td>
        </tr>
    </table>

    <!-- Referral Status Pie Chart -->
    @if(!empty($referralStatusDistribution['labels']))
    <div class="chart-container">
        <div class="chart-title">Referral Status Distribution</div>
        <div class="chart-subtitle">Breakdown of all referrals by current status</div>
        {!! $chartRenderer->pieChart(
            $referralStatusDistribution['labels'],
            $referralStatusDistribution['data'],
            ['size' => 220, 'colors' => ['#3f915f', '#2563eb', '#005288', '#d9663b', '#dc2626', '#9b51b0', '#0b7a75', '#b45309']]
        ) !!}
    </div>
    @endif

    <!-- ═══════════════════ PAGE 2: TRENDS & DISTRIBUTION ═══════════════════ -->
    <h2 class="page-break">Trends &amp; Distribution</h2>

    <!-- Monthly Trends Line Charts -->
    @if(!empty($caseTrends['labels']))
    <div class="chart-container">
        <div class="chart-title">Monthly Case Trend</div>
        <div class="chart-subtitle">Case creation volume over time</div>
        {!! $chartRenderer->lineChart($caseTrends['labels'], $caseTrends['data'], ['width' => 460, 'height' => 160, 'color' => '#005288']) !!}
    </div>
    @endif

    @if(!empty($referralTrends['labels']))
    <div class="chart-container">
        <div class="chart-title">Monthly Referral Trend</div>
        <div class="chart-subtitle">Referral creation volume over time</div>
        {!! $chartRenderer->lineChart($referralTrends['labels'], $referralTrends['data'], ['width' => 460, 'height' => 160, 'color' => '#3f915f']) !!}
    </div>
    @endif

    <!-- Category Distribution Bar Chart -->
    @if(!empty($categoryDistribution))
    <div class="chart-container">
        <div class="chart-title">Case Categories</div>
        <div class="chart-subtitle">Distribution of cases by category</div>
        {!! $chartRenderer->barChart(
            collect($categoryDistribution)->pluck('name')->toArray(),
            collect($categoryDistribution)->pluck('count')->toArray(),
            ['width' => 460, 'height' => 180]
        ) !!}
    </div>
    @endif

    <!-- ═══════════════════ PAGE 3: AGENCY PERFORMANCE ═══════════════════ -->
    <h2 class="page-break">Agency Performance</h2>

    @if(!empty($agencyScorecard))
    <div class="chart-container">
        <div class="chart-title">Agency Scorecard</div>
        <div class="chart-subtitle">Total referrals handled per agency</div>
        {!! $chartRenderer->horizontalBarChart(
            collect($agencyScorecard)->pluck('agency')->toArray(),
            collect($agencyScorecard)->pluck('total')->toArray(),
            ['width' => 460, 'height' => max(150, count($agencyScorecard) * 30)]
        ) !!}
    </div>

    <h3>Detailed Agency Metrics</h3>
    <table class="data">
        <thead><tr><th>Agency</th><th>Total</th><th>Completed</th><th>Pending</th><th>Avg Days</th></tr></thead>
        <tbody>
            @foreach($agencyScorecard as $row)
                <tr>
                    <td>{{ e($row['agency'] ?? '') }}</td>
                    <td>{{ e($row['total'] ?? 0) }}</td>
                    <td>{{ e($row['completed'] ?? 0) }}</td>
                    <td>{{ e($row['pending'] ?? 0) }}</td>
                    <td>{{ e($row['avg_days'] ?? '—') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Referral Aging -->
    @if(!empty($referralAging['labels']))
    <div class="chart-container">
        <div class="chart-title">Referral Aging</div>
        <div class="chart-subtitle">How long active referrals have been waiting</div>
        {!! $chartRenderer->barChart(
            $referralAging['labels'],
            $referralAging['data'],
            ['width' => 460, 'height' => 160, 'colors' => ['#3f915f', '#0b7a75', '#2563eb', '#d9663b', '#dc2626', '#b45309', '#9b51b0', '#dc2626']]
        ) !!}
    </div>
    @endif

    <!-- Cycle Time -->
    @if(!empty($cycleTimeDistribution['labels']))
    <div class="chart-container">
        <div class="chart-title">Cycle Time Distribution</div>
        <div class="chart-subtitle">Time taken to complete referrals</div>
        {!! $chartRenderer->barChart(
            $cycleTimeDistribution['labels'],
            $cycleTimeDistribution['data'],
            ['width' => 460, 'height' => 160, 'color' => '#0b7a75']
        ) !!}
    </div>
    @endif

    <!-- ═══════════════════ PAGE 4: GEOGRAPHY & EMPLOYMENT ═══════════════════ -->
    <h2 class="page-break">Geography &amp; Employment</h2>

    @if(!empty($geographicDistribution['labels']))
    <div class="chart-container">
        <div class="chart-title">Geographic Distribution</div>
        <div class="chart-subtitle">Cases by province of origin</div>
        {!! $chartRenderer->horizontalBarChart(
            $geographicDistribution['labels'],
            $geographicDistribution['data'],
            ['width' => 460, 'height' => max(150, count($geographicDistribution['labels']) * 28), 'color' => '#005288']
        ) !!}
    </div>
    @endif

    @if(!empty($employmentDistribution['labels']))
    <div class="chart-container">
        <div class="chart-title">Employment Country Distribution</div>
        <div class="chart-subtitle">Previous country of employment</div>
        {!! $chartRenderer->horizontalBarChart(
            $employmentDistribution['labels'],
            $employmentDistribution['data'],
            ['width' => 460, 'height' => max(150, count($employmentDistribution['labels']) * 28), 'color' => '#9b51b0']
        ) !!}
    </div>
    @endif

    <!-- Case Issues -->
    @if(!empty($caseIssueDistribution))
    <div class="chart-container">
        <div class="chart-title">Case Issues</div>
        <div class="chart-subtitle">Most reported issues/concerns</div>
        {!! $chartRenderer->barChart(
            collect($caseIssueDistribution)->pluck('name')->toArray(),
            collect($caseIssueDistribution)->pluck('count')->toArray(),
            ['width' => 460, 'height' => 160, 'color' => '#d9663b']
        ) !!}
    </div>
    @endif

    <!-- ═══════════════════ PAGE 5+: TOP RISKS ═══════════════════ -->
    <h2 class="page-break">Top 10 Referrals by Business Risk</h2>
    <p class="section-desc">Highest-risk active referrals ranked by status severity and age.</p>
    <table class="data">
        <thead><tr><th>Case #</th><th>Agency</th><th>Status</th><th>Created</th><th>Age</th><th>Risk</th></tr></thead>
        <tbody>
            @foreach(($topReferrals ?? []) as $row)
                @php $riskClass = ($row->risk_score ?? 0) > 100 ? 'risk-high' : (($row->risk_score ?? 0) > 60 ? 'risk-mid' : ''); @endphp
                <tr class="{{ $riskClass }}">
                    <td>{{ e($row->case_number ?? '') }}</td>
                    <td>{{ e($row->agency ?? '') }}</td>
                    <td><strong>{{ e($row->status ?? '') }}</strong></td>
                    <td>{{ e(\Carbon\Carbon::parse($row->created_at)->format('M d, Y')) }}</td>
                    <td>{{ e($row->age_days ?? '') }}d</td>
                    <td><strong>{{ e($row->risk_score ?? '') }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Top 10 Cases by Business Risk</h2>
    <p class="section-desc">Highest-risk active cases ranked by status severity and age.</p>
    <table class="data">
        <thead><tr><th>Case #</th><th>Category</th><th>Issue</th><th>Status</th><th>Created</th><th>Age</th><th>Risk</th></tr></thead>
        <tbody>
            @foreach(($topCases ?? []) as $row)
                @php $riskClass = ($row->risk_score ?? 0) > 100 ? 'risk-high' : (($row->risk_score ?? 0) > 60 ? 'risk-mid' : ''); @endphp
                <tr class="{{ $riskClass }}">
                    <td>{{ e($row->case_number ?? '') }}</td>
                    <td>{{ e($row->category ?? '') }}</td>
                    <td>{{ e($row->issue ?? '') }}</td>
                    <td><strong>{{ e($row->status ?? '') }}</strong></td>
                    <td>{{ e(\Carbon\Carbon::parse($row->created_at)->format('M d, Y')) }}</td>
                    <td>{{ e($row->age_days ?? '') }}d</td>
                    <td><strong>{{ e($row->risk_score ?? '') }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════ FOOTER ═══════════════════ -->
    <htmlpagefooter name="page-footer">
        <table style="width:100%; border-top:1px solid #e2e8f0; font-size:7px; color:#94a3b8; padding-top:4px;">
            <tr>
                <td style="text-align:left;">Department of Migrant Workers &mdash; Region VII &bull; One Window Bayanihan</td>
                <td style="text-align:right;">Page {PAGENO} of {nbpg}</td>
            </tr>
        </table>
    </htmlpagefooter>

</body>
</html>
