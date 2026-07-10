<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bayanihan System Performance Report</title>
    <style>
        @page {
            margin: 32px 36px;
            footer: page-footer;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.5;
            color: #1e293b;
        }
        /* ── Header ── */
        .report-header {
            border-bottom: 3px solid #005288;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .report-header h1 {
            margin: 0 0 2px;
            font-size: 20px;
            color: #005288;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .report-header .sub {
            font-size: 10px;
            color: #475569;
        }
        .report-header .sub strong {
            color: #1e293b;
        }
        .report-header .meta {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }
        /* ── Section headings ── */
        h2 {
            margin: 20px 0 8px;
            font-size: 12px;
            color: #005288;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        h2:first-of-type {
            margin-top: 0;
        }
        /* ── KPI cards ── */
        .kpi-row {
            width: 100%;
            margin: 12px 0;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .kpi-row td {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 10px 12px;
            text-align: center;
            vertical-align: middle;
            border-radius: 4px;
        }
        .kpi-row .kpi-value {
            font-size: 20px;
            font-weight: 800;
            color: #005288;
            line-height: 1.2;
        }
        .kpi-row .kpi-label {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-top: 2px;
        }
        .kpi-row td.accent-blue  { border-left: 4px solid #0b5a8c; }
        .kpi-row td.accent-green { border-left: 4px solid #3f915f; }
        .kpi-row td.accent-teal  { border-left: 4px solid #0b7a75; }
        .kpi-row td.accent-purple{ border-left: 4px solid #9b51b0; }
        .kpi-row td.accent-amber { border-left: 4px solid #9a5b1a; }
        .kpi-row td.accent-orange{ border-left: 4px solid #d9663b; }
        /* ── Tables ── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8.5px;
        }
        table.data th {
            background: #eaf4fb;
            color: #0f3f5f;
            font-weight: 700;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1px solid #cbd5e1;
        }
        table.data td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td {
            background: #f8fafc;
        }
        /* ── Filter context badge row ── */
        .filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 8px;
            color: #475569;
        }
        .filter-bar strong {
            color: #1e293b;
        }
        .filter-tag {
            display: inline;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 1px 6px;
            margin: 0 2px;
            font-size: 7.5px;
            font-weight: 600;
        }
        /* ── Misc ── */
        .warn-box {
            border: 1px solid #f59e0b;
            background: #fffbeb;
            padding: 6px 10px;
            margin: 8px 0;
            font-size: 8px;
            color: #92400e;
        }
        .page-break { page-break-before: always; }
        .section-desc {
            font-size: 8px;
            color: #64748b;
            margin: -4px 0 8px;
        }
        .muted { color: #94a3b8; }
        tr.risk-high td { background: #fef2f2 !important; }
        tr.risk-mid td  { background: #fffbeb !important; }
    </style>
</head>
<body>

    <!-- ═══════════════════════ HEADER ═══════════════════════ -->
    <div class="report-header">
        <h1>System Performance Report</h1>
        <div class="sub">
            <strong>{{ e($metadata['scope'] ?? '') }}</strong> scope &middot;
            {{ e($metadata['filters']['from'] ?? '') }} &ndash; {{ e($metadata['filters']['to'] ?? '') }}
            <span class="muted">({{ e($metadata['timezone'] ?? '') }})</span>
        </div>
        <div class="meta">
            Generated {{ e($metadata['generated_at_manila'] ?? '') }} Manila
            &middot; Schema {{ e($metadata['schema_version'] ?? '') }}
            &middot; by {{ e($metadata['generated_by'] ?? '') }}
        </div>
    </div>

    <!-- ═══════════════════════ FILTER CONTEXT ═══════════════════════ -->
    <div class="filter-bar">
        <strong>Filters applied:</strong>
        <span class="filter-tag">Date basis: {{ e($metadata['filters']['date_scope'] ?? 'case_created_at') }}</span>
        <span class="filter-tag">Province: {{ e($metadata['filters']['province'] ?? 'All') }}</span>
        <span class="filter-tag">City: {{ e($metadata['filters']['city'] ?? 'All') }}</span>
    </div>

    @foreach(($capWarnings ?? []) as $warning)
        <div class="warn-box">⚠ {{ e($warning) }}</div>
    @endforeach

    <!-- ═══════════════════════ EXECUTIVE SUMMARY ═══════════════════════ -->
    <h2>Executive Summary</h2>
    <table class="kpi-row">
        <tr>
            <td class="accent-blue"><div class="kpi-value">{{ e($kpis['openCases'] ?? 0) }}</div><div class="kpi-label">Active Cases</div></td>
            <td class="accent-blue"><div class="kpi-value">{{ e($kpis['totalCases'] ?? 0) }}</div><div class="kpi-label">Total Cases</div></td>
            <td class="accent-blue"><div class="kpi-value">{{ e($kpis['totalReferrals'] ?? 0) }}</div><div class="kpi-label">Total Referrals</div></td>
            <td class="accent-green"><div class="kpi-value">{{ e($kpis['completedReferrals'] ?? 0) }}</div><div class="kpi-label">Completed</div></td>
        </tr>
        <tr>
            <td class="accent-teal"><div class="kpi-value">{{ e($kpis['completionRate'] ?? 0) }}%</div><div class="kpi-label">Completion Rate</div></td>
            <td class="accent-purple"><div class="kpi-value">{{ e($kpis['avgResolutionDays'] ?? 0) }}d</div><div class="kpi-label">Avg Resolution</div></td>
            <td class="accent-amber"><div class="kpi-value">{{ e($kpis['pendingReferrals'] ?? 0) }}</div><div class="kpi-label">Pending</div></td>
            <td class="accent-orange"><div class="kpi-value">{{ e($kpis['forComplianceReferrals'] ?? 0) }}</div><div class="kpi-label">For Compliance</div></td>
        </tr>
    </table>

    <!-- ═══════════════════════ OVERVIEW ═══════════════════════ -->
    <h2>Referral Status</h2>
    <p class="section-desc">Breakdown of referrals by current status.</p>
    <table class="data">
        <thead><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
            @php $refTotal = array_sum($referralStatusDistribution['data'] ?? []); @endphp
            @foreach(($referralStatusDistribution['labels'] ?? []) as $i => $label)
                <tr>
                    <td>{{ e($label) }}</td>
                    <td>{{ e($referralStatusDistribution['data'][$i] ?? 0) }}
                        @if($refTotal > 0)
                            <span class="muted">({{ round((($referralStatusDistribution['data'][$i] ?? 0) / $refTotal) * 100) }}%)</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($refTotal > 0)
                <tr style="font-weight:700; background:#f1f5f9 !important;">
                    <td>Total</td><td>{{ $refTotal }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>Case Status</h2>
    <p class="section-desc">Distribution of cases by current status (excludes Draft &amp; Archived).</p>
    <table class="data">
        <thead><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
            @php $caseTotal = array_sum($caseStatusDistribution['data'] ?? []); @endphp
            @foreach(($caseStatusDistribution['labels'] ?? []) as $i => $label)
                <tr>
                    <td>{{ e($label) }}</td>
                    <td>{{ e($caseStatusDistribution['data'][$i] ?? 0) }}
                        @if($caseTotal > 0)
                            <span class="muted">({{ round((($caseStatusDistribution['data'][$i] ?? 0) / $caseTotal) * 100) }}%)</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($caseTotal > 0)
                <tr style="font-weight:700; background:#f1f5f9 !important;">
                    <td>Total</td><td>{{ $caseTotal }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- ═══════════════════════ AGENCY PERFORMANCE ═══════════════════════ -->
    <h2 class="page-break">Agency Scorecard</h2>
    <p class="section-desc">Performance metrics across all agencies.</p>
    <table class="data">
        <thead><tr><th>Agency</th><th>Total</th><th>Completed</th><th>Pending</th><th>Avg Days</th></tr></thead>
        <tbody>
            @foreach(($agencyScorecard ?? []) as $row)
                <tr>
                    <td>{{ e($row['agency'] ?? '') }}</td>
                    <td>{{ e($row['total'] ?? 0) }}</td>
                    <td>{{ e($row['completed'] ?? 0) }}</td>
                    <td>{{ e($row['pending'] ?? 0) }}</td>
                    <td>{{ e($row['avg_days'] ?? '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Referral Aging</h2>
    <p class="section-desc">How long active referrals have been waiting.</p>
    <table class="data">
        <thead><tr><th>Age Range</th><th>Count</th></tr></thead>
        <tbody>
            @foreach(($referralAging['labels'] ?? []) as $i => $label)
                <tr><td>{{ e($label) }}</td><td>{{ e($referralAging['data'][$i] ?? 0) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Cycle Time</h2>
    <p class="section-desc">Time taken to complete referrals.</p>
    <table class="data">
        <thead><tr><th>Duration Range</th><th>Count</th></tr></thead>
        <tbody>
            @foreach(($cycleTimeDistribution['labels'] ?? []) as $i => $label)
                <tr><td>{{ e($label) }}</td><td>{{ e($cycleTimeDistribution['data'][$i] ?? 0) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════════ CATEGORIES & ISSUES ═══════════════════════ -->
    <h2 class="page-break">Categories &amp; Issues</h2>
    <p class="section-desc">Case categories and issues reported.</p>
    <table class="data">
        <thead><tr><th>Type</th><th>Name</th><th>Count</th></tr></thead>
        <tbody>
            @foreach(($categoryDistribution ?? []) as $row)
                <tr><td>Category</td><td>{{ e($row['name'] ?? '') }}</td><td>{{ e($row['count'] ?? 0) }}</td></tr>
            @endforeach
            @foreach(($caseIssueDistribution ?? []) as $row)
                <tr><td>Issue</td><td>{{ e($row['name'] ?? '') }}</td><td>{{ e($row['count'] ?? 0) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════════ GEOGRAPHY & EMPLOYMENT ═══════════════════════ -->
    <h2>Geography &amp; Employment</h2>
    <table class="data">
        <thead><tr><th>Type</th><th>Label</th><th>Count</th></tr></thead>
        <tbody>
            @foreach(($geographicDistribution['labels'] ?? []) as $i => $label)
                <tr><td>Province</td><td>{{ e($label) }}</td><td>{{ e($geographicDistribution['data'][$i] ?? 0) }}</td></tr>
            @endforeach
            @foreach(($employmentDistribution['labels'] ?? []) as $i => $label)
                <tr><td>Country</td><td>{{ e($label) }}</td><td>{{ e($employmentDistribution['data'][$i] ?? 0) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════════ MONTHLY TRENDS ═══════════════════════ -->
    <h2 class="page-break">Monthly Trends</h2>
    <p class="section-desc">Case and referral creation over time.</p>
    <table class="data">
        <thead><tr><th>Period</th><th>Cases</th><th>Referrals</th></tr></thead>
        <tbody>
            @php
                $caseTrendMap = collect($caseTrends['labels'] ?? [])->mapWithKeys(fn ($l, $i) => [$l => $caseTrends['data'][$i] ?? 0]);
                $refTrendMap = collect($referralTrends['labels'] ?? [])->mapWithKeys(fn ($l, $i) => [$l => $referralTrends['data'][$i] ?? 0]);
                $periods = $caseTrendMap->keys()->merge($refTrendMap->keys())->unique()->sort()->values();
            @endphp
            @foreach($periods as $period)
                <tr><td>{{ e($period) }}</td><td>{{ e($caseTrendMap[$period] ?? 0) }}</td><td>{{ e($refTrendMap[$period] ?? 0) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════════ TOP RISKS ═══════════════════════ -->
    <h2 class="page-break">Top 10 Referrals by Business Risk</h2>
    <p class="section-desc">Highest-risk active referrals based on status and age.</p>
    <table class="data">
        <thead><tr><th>Case #</th><th>Agency</th><th>Status</th><th>Created</th><th>Age (days)</th><th>Risk Score</th></tr></thead>
        <tbody>
            @foreach(($topReferrals ?? []) as $row)
                @php $riskClass = ($row->risk_score ?? 0) > 100 ? 'risk-high' : (($row->risk_score ?? 0) > 60 ? 'risk-mid' : ''); @endphp
                <tr class="{{ $riskClass }}">
                    <td>{{ e($row->case_number ?? '') }}</td>
                    <td>{{ e($row->agency ?? '') }}</td>
                    <td>{{ e($row->status ?? '') }}</td>
                    <td>{{ e(\Carbon\Carbon::parse($row->created_at)->format('M d, Y')) }}</td>
                    <td>{{ e($row->age_days ?? '') }}</td>
                    <td>{{ e($row->risk_score ?? '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Top 10 Cases by Business Risk</h2>
    <p class="section-desc">Highest-risk active cases based on status and age.</p>
    <table class="data">
        <thead><tr><th>Case #</th><th>Category</th><th>Issue</th><th>Status</th><th>Created</th><th>Age (days)</th><th>Risk Score</th></tr></thead>
        <tbody>
            @foreach(($topCases ?? []) as $row)
                @php $riskClass = ($row->risk_score ?? 0) > 100 ? 'risk-high' : (($row->risk_score ?? 0) > 60 ? 'risk-mid' : ''); @endphp
                <tr class="{{ $riskClass }}">
                    <td>{{ e($row->case_number ?? '') }}</td>
                    <td>{{ e($row->category ?? '') }}</td>
                    <td>{{ e($row->issue ?? '') }}</td>
                    <td>{{ e($row->status ?? '') }}</td>
                    <td>{{ e(\Carbon\Carbon::parse($row->created_at)->format('M d, Y')) }}</td>
                    <td>{{ e($row->age_days ?? '') }}</td>
                    <td>{{ e($row->risk_score ?? '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══════════════════════ FOOTER ═══════════════════════ -->
    <htmlpagefooter name="page-footer">
        <table style="width:100%; border-top:1px solid #e2e8f0; font-size:7px; color:#94a3b8; padding-top:4px;">
            <tr>
                <td style="text-align:left;">Bayanihan Report &middot; DMW Region VII</td>
                <td style="text-align:right;">Page {PAGE_NUM} of {PAGE_COUNT}</td>
            </tr>
        </table>
    </htmlpagefooter>

</body>
</html>
