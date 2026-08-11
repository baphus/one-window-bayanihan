@php
    /**
     * System Performance Report — dompdf.
     *
     * Notes for anyone editing this file:
     *  - dompdf, not mPDF. `<htmlpagefooter>`, `{PAGENO}` and `@page { footer: }`
     *    are mPDF syntax and are silently ignored here; the previous version of
     *    this template used them and printed a literal "{PAGENO} of {nbpg}"
     *    inside the body. Running headers/footers are done with
     *    `position: fixed` plus `counter(page)`, which dompdf does support.
     *  - No flexbox or grid. Layout is tables.
     *  - Charts are PNGs from PdfChartRenderer, not SVG — dompdf drops inline
     *    SVG geometry and leaks the <text> nodes into the page.
     */
    $chartRenderer = app(\App\Services\Reports\PdfChartRenderer::class);

    $maxCats = (int) ($chartMaxCategories ?? 15);

    // Long category lists make a one-row-per-item chart unreadable and push
    // the image off the page. Chart the top N, table the whole set.
    $capped = function (array $dist) use ($maxCats) {
        $labels = $dist['labels'] ?? [];
        $data = $dist['data'] ?? [];
        if (count($labels) <= $maxCats) {
            return ['labels' => $labels, 'data' => $data, 'omitted' => 0];
        }
        $pairs = [];
        foreach ($labels as $i => $l) {
            $pairs[] = [$l, (float) ($data[$i] ?? 0)];
        }
        usort($pairs, fn ($a, $b) => $b[1] <=> $a[1]);
        $top = array_slice($pairs, 0, $maxCats);

        return [
            'labels' => array_column($top, 0),
            'data' => array_column($top, 1),
            'omitted' => count($pairs) - count($top),
        ];
    };

    $fmt = fn ($v) => number_format((float) $v);
    $filters = $metadata['filters'] ?? [];
    $suppressionNote = ($suppression['applied'] ?? false)
        ? 'Buckets smaller than '.$suppression['threshold'].' are withheld from demographic sections to prevent re-identification.'
        : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bayanihan System Performance Report</title>
    <style>
        /* ── Page ──────────────────────────────────────────────────────────
           Bottom margin reserves the band the fixed footer sits in. */
        @page { margin: 30px 34px 46px 34px; }

        /* DejaVu is dompdf's bundled Unicode face and the only one guaranteed
           present. It carries the Filipino diacritics the previous font stack
           could not. */
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            line-height: 1.55;
            color: #1e293b;
        }

        /* ── Type scale ─────────────────────────────────────────────────────
           18 / 13 / 11 / 9.5 / 8.5. The previous template bottomed out at 7px,
           which is below comfortable print legibility. Nothing is under 8px. */
        h1 { font-size: 18px; line-height: 1.25; margin: 0 0 6px; color: #005288; font-weight: 700; letter-spacing: -0.2px; }
        h2 { font-size: 13px; line-height: 1.3; margin: 20px 0 9px; color: #005288; font-weight: 700; border-bottom: 2px solid #005288; padding-bottom: 4px; }
        h3 { font-size: 11px; line-height: 1.35; margin: 14px 0 6px; color: #334155; font-weight: 700; }
        .lede { font-size: 11px; color: #475569; }
        .small { font-size: 8.5px; color: #64748b; }

        /* ── Cover ── */
        .cover { padding-top: 150px; text-align: center; }
        .cover .org { font-size: 11px; color: #475569; letter-spacing: 0.16em; text-transform: uppercase; margin-bottom: 26px; }
        .cover h1 { font-size: 30px; line-height: 1.2; margin-bottom: 10px; }
        .cover .sub { font-size: 13px; color: #475569; margin-bottom: 34px; }
        .cover-facts { width: 74%; margin: 0 auto; border-collapse: collapse; text-align: left; }
        .cover-facts th, .cover-facts td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; vertical-align: top; }
        .cover-facts th { width: 38%; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 8.5px; }
        .classification {
            margin: 40px auto 0; width: 74%; padding: 10px 14px;
            border: 1px solid #b45309; background: #fffbeb; color: #92400e;
            font-size: 9px; text-align: left; border-radius: 3px;
        }
        .classification strong { display: block; font-size: 9.5px; margin-bottom: 3px; letter-spacing: 0.05em; text-transform: uppercase; }

        /* ── Running footer ── */
        .page-footer {
            position: fixed; bottom: -32px; left: 0; right: 0; height: 26px;
            border-top: 1px solid #e2e8f0; padding-top: 5px;
            font-size: 8.5px; color: #94a3b8;
        }
        .page-footer table { width: 100%; border-collapse: collapse; }
        .page-footer td { border: none; padding: 0; }
        .page-footer .right { text-align: right; }
        .page-footer .pageno:after { content: counter(page); }

        /* ── KPI cards ── */
        .kpi-grid { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 6px; margin: 12px 0; }
        .kpi-grid td { border: 1px solid #e2e8f0; background: #f8fafc; padding: 11px 9px; text-align: center; vertical-align: middle; }
        .kpi-value { font-size: 21px; font-weight: 700; color: #005288; line-height: 1.15; }
        .kpi-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; margin-top: 4px; }
        .accent-blue { border-left: 4px solid #005288 !important; }
        .accent-green { border-left: 4px solid #3f915f !important; }
        .accent-teal { border-left: 4px solid #0b7a75 !important; }
        .accent-purple { border-left: 4px solid #9b51b0 !important; }
        .accent-amber { border-left: 4px solid #b45309 !important; }
        .accent-red { border-left: 4px solid #dc2626 !important; }

        /* ── Filter context ── */
        .filter-bar { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 7px 11px; margin-bottom: 12px; font-size: 8.5px; color: #475569; }
        .filter-tag { background: #fff; border: 1px solid #cbd5e1; padding: 2px 6px; margin-right: 4px; font-size: 8.5px; font-weight: 700; }

        /* ── Charts + companion tables ── */
        .chart-row { width: 100%; border-collapse: collapse; }
        .chart-row td { vertical-align: top; padding: 0 7px; border: none; }
        .chart-cell { width: 58%; }
        .table-cell { width: 42%; }
        .chart-cell img { max-width: 100%; height: auto; }
        .chart-title { font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 3px; }
        .chart-subtitle { font-size: 8.5px; color: #64748b; margin-bottom: 7px; }
        .chart-container { margin: 6px 0 12px; }

        /* ── Tables ── */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8.5px; }
        table.data th { background: #005288; color: #fff; font-weight: 700; padding: 6px 7px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.04em; }
        table.data td { padding: 5px 7px; border: 1px solid #e2e8f0; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data td.num, table.data th.num { text-align: right; }

        tr.risk-high td { background: #fef2f2 !important; }
        tr.risk-mid td { background: #fffbeb !important; }

        /* ── Notices ── */
        .warn-box { border: 1px solid #f59e0b; background: #fffbeb; padding: 7px 10px; margin: 8px 0; font-size: 8.5px; color: #92400e; }
        .note-box { border: 1px solid #cbd5e1; background: #f8fafc; padding: 7px 10px; margin: 8px 0; font-size: 8.5px; color: #475569; }
        .section-desc { font-size: 8.5px; color: #64748b; margin: -4px 0 9px; }
        .page-break { page-break-before: always; }
        .muted { color: #94a3b8; }
    </style>
</head>
<body>

    {{-- Repeats on every page. dompdf renders position:fixed once per page. --}}
    <div class="page-footer">
        <table>
            <tr>
                <td>Department of Migrant Workers — Region VII &bull; One Window Bayanihan &bull; Internal use only</td>
                <td class="right">Page <span class="pageno"></span></td>
            </tr>
        </table>
    </div>

    {{-- ═══════════════ COVER ═══════════════ --}}
    <div class="cover">
        <div class="org">Department of Migrant Workers &mdash; Region VII</div>
        <h1>System Performance Report</h1>
        <div class="sub">One Window Bayanihan Assistance Program</div>

        <table class="cover-facts">
            <tr><th>Reporting period</th><td>{{ $filters['from'] ?? '' }} &nbsp;to&nbsp; {{ $filters['to'] ?? '' }}</td></tr>
            <tr><th>Date basis</th><td>{{ str_replace('_', ' ', $filters['date_scope'] ?? 'case created at') }}</td></tr>
            <tr><th>Province</th><td>{{ $filters['province'] ?? 'All' }}</td></tr>
            <tr><th>City / municipality</th><td>{{ $filters['city'] ?? 'All' }}</td></tr>
            <tr><th>Agency</th><td>{{ $filters['agency_id'] ?? 'All' }}</td></tr>
            <tr><th>Prepared for</th><td>{{ $metadata['scope'] ?? '' }}</td></tr>
            <tr><th>Generated</th><td>{{ $metadata['generated_at_manila'] ?? '' }} ({{ $metadata['timezone'] ?? '' }})</td></tr>
            <tr><th>Generated by</th><td>{{ $metadata['generated_by'] ?? '' }}</td></tr>
        </table>

        <div class="classification">
            <strong>Confidential &mdash; internal use only</strong>
            Contains aggregated personal data relating to migrant workers and their families.
            Handle under the Data Privacy Act and the programme's data protection policy.
            Do not redistribute outside the Department without approval.
            @if($suppressionNote) {{ $suppressionNote }} @endif
        </div>
    </div>

    {{-- ═══════════════ EXECUTIVE SUMMARY ═══════════════ --}}
    <h2 class="page-break">Executive Summary</h2>

    <div class="filter-bar">
        <strong>Active filters:</strong>
        <span class="filter-tag">{{ $filters['from'] ?? '' }} to {{ $filters['to'] ?? '' }}</span>
        <span class="filter-tag">Basis: {{ $filters['date_scope'] ?? 'case_created_at' }}</span>
        <span class="filter-tag">Province: {{ $filters['province'] ?? 'All' }}</span>
        <span class="filter-tag">City: {{ $filters['city'] ?? 'All' }}</span>
    </div>

    @foreach(($capWarnings ?? []) as $warning)
        <div class="warn-box">{{ $warning }}</div>
    @endforeach

    @if($suppressionNote)
        <div class="note-box">{{ $suppressionNote }}</div>
    @endif

    <table class="kpi-grid">
        <tr>
            <td class="accent-blue"><div class="kpi-value">{{ $fmt($kpis['totalCases'] ?? 0) }}</div><div class="kpi-label">Total Cases</div></td>
            <td class="accent-blue"><div class="kpi-value">{{ $fmt($kpis['openCases'] ?? 0) }}</div><div class="kpi-label">Active Cases</div></td>
            <td class="accent-blue"><div class="kpi-value">{{ $fmt($kpis['totalReferrals'] ?? 0) }}</div><div class="kpi-label">Total Referrals</div></td>
            <td class="accent-green"><div class="kpi-value">{{ $fmt($kpis['completedReferrals'] ?? 0) }}</div><div class="kpi-label">Completed</div></td>
        </tr>
        <tr>
            <td class="accent-teal"><div class="kpi-value">{{ $kpis['completionRate'] ?? 0 }}%</div><div class="kpi-label">Completion Rate</div></td>
            <td class="accent-purple"><div class="kpi-value">{{ $kpis['avgResolutionDays'] ?? 0 }}d</div><div class="kpi-label">Avg Resolution</div></td>
            <td class="accent-amber"><div class="kpi-value">{{ $fmt($kpis['pendingReferrals'] ?? 0) }}</div><div class="kpi-label">Pending</div></td>
            <td class="accent-red"><div class="kpi-value">{{ $fmt($overdueReferrals['count'] ?? 0) }}</div><div class="kpi-label">Overdue &gt;{{ $overdueReferrals['threshold_days'] ?? 14 }}d</div></td>
        </tr>
    </table>

    @if(!empty($mostRequestedService['name']) && $mostRequestedService['name'] !== 'N/A')
        <div class="note-box">
            <strong>Most requested service:</strong> {{ $mostRequestedService['name'] }}
            ({{ $fmt($mostRequestedService['value'] ?? 0) }} requests in range)
        </div>
    @endif

    @if(!empty($referralStatusDistribution['labels']))
    <table class="chart-row">
        <tr>
            <td class="chart-cell">
                <div class="chart-container">
                    <div class="chart-title">Referral Status Distribution</div>
                    <div class="chart-subtitle">All referrals in range by current status</div>
                    {!! $chartRenderer->pieChart($referralStatusDistribution['labels'], $referralStatusDistribution['data'], ['size' => 210]) !!}
                </div>
            </td>
            <td class="table-cell">
                <table class="data">
                    <thead><tr><th>Status</th><th class="num">Referrals</th></tr></thead>
                    <tbody>
                        @foreach($referralStatusDistribution['labels'] as $i => $label)
                            <tr><td>{{ $label }}</td><td class="num">{{ $fmt($referralStatusDistribution['data'][$i] ?? 0) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    @endif

    @if(!empty($referralFunnel['stages']))
    <h3>Referral Funnel</h3>
    <table class="chart-row">
        <tr>
            <td class="chart-cell">
                <div class="chart-container">
                    {!! $chartRenderer->barChart(
                        collect($referralFunnel['stages'])->pluck('stage')->all(),
                        collect($referralFunnel['stages'])->pluck('count')->all(),
                        ['width' => 440, 'height' => 175]
                    ) !!}
                </div>
            </td>
            <td class="table-cell">
                <table class="data">
                    <thead><tr><th>Stage</th><th class="num">Referrals</th><th class="num">Share</th></tr></thead>
                    <tbody>
                        @foreach($referralFunnel['stages'] as $stage)
                            <tr>
                                <td>{{ $stage['stage'] }}</td>
                                <td class="num">{{ $fmt($stage['count']) }}</td>
                                <td class="num">{{ $stage['share'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    @endif

    {{-- ═══════════════ TRENDS ═══════════════ --}}
    <h2 class="page-break">Trends Over Time</h2>

    @php
        $casesSeries = [
            'labels' => $casesOverTime['labels'] ?? [],
            'data' => $casesOverTime['datasets'][0]['data'] ?? [],
        ];
    @endphp

    @foreach([
        ['Monthly Case Volume', 'Cases created per month', $caseTrends ?? [], '#005288', 'Cases'],
        ['Monthly Referral Volume', 'Referrals created per month', $referralTrends ?? [], '#3f915f', 'Referrals'],
        ['Cases Over Time', 'Case intake trend across the selected window', $casesSeries, '#9b51b0', 'Cases'],
    ] as [$title, $desc, $series, $colour, $unit])
        @continue(empty($series['labels']))
        <table class="chart-row">
            <tr>
                <td class="chart-cell">
                    <div class="chart-container">
                        <div class="chart-title">{{ $title }}</div>
                        <div class="chart-subtitle">{{ $desc }}</div>
                        {!! $chartRenderer->lineChart($series['labels'], $series['data'] ?? [], ['width' => 440, 'height' => 155, 'color' => $colour]) !!}
                    </div>
                </td>
                <td class="table-cell">
                    <table class="data">
                        <thead><tr><th>Month</th><th class="num">{{ $unit }}</th></tr></thead>
                        <tbody>
                            @foreach($series['labels'] as $i => $label)
                                <tr><td>{{ $label }}</td><td class="num">{{ $fmt($series['data'][$i] ?? 0) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    {{-- ═══════════════ PERFORMANCE ═══════════════ --}}
    <h2 class="page-break">Performance</h2>

    @foreach([
        ['Referral Aging', 'How long active referrals have been waiting', $referralAging ?? [], 'Age Band'],
        ['Cycle Time Distribution', 'Elapsed time to complete a referral', $cycleTimeDistribution ?? [], 'Duration'],
    ] as [$title, $desc, $dist, $header])
        @continue(empty($dist['labels']))
        <table class="chart-row">
            <tr>
                <td class="chart-cell">
                    <div class="chart-container">
                        <div class="chart-title">{{ $title }}</div>
                        <div class="chart-subtitle">{{ $desc }}</div>
                        {!! $chartRenderer->barChart($dist['labels'], $dist['data'], ['width' => 440, 'height' => 165, 'colors' => $dist['colors'] ?? []]) !!}
                    </div>
                </td>
                <td class="table-cell">
                    <table class="data">
                        <thead><tr><th>{{ $header }}</th><th class="num">Referrals</th></tr></thead>
                        <tbody>
                            @foreach($dist['labels'] as $i => $label)
                                <tr><td>{{ $label }}</td><td class="num">{{ $fmt($dist['data'][$i] ?? 0) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    {{-- ═══════════════ AGENCIES ═══════════════ --}}
    @if(!empty($agencyScorecard) || !empty($agencyWorkload['labels']) || !empty($referralAgencyDistribution['labels']))
    <h2 class="page-break">Agency Performance</h2>

    @if(!empty($agencyScorecard))
        @php $scoreCapped = $capped(['labels' => collect($agencyScorecard)->pluck('agency')->all(), 'data' => collect($agencyScorecard)->pluck('total')->all()]); @endphp
        <div class="chart-container">
            <div class="chart-title">Referrals Handled per Agency</div>
            <div class="chart-subtitle">
                Top {{ count($scoreCapped['labels']) }} by volume
                @if($scoreCapped['omitted'] > 0)
                    &mdash; {{ $scoreCapped['omitted'] }} further {{ Str::plural('agency', $scoreCapped['omitted']) }} listed in the table below
                @endif
            </div>
            {!! $chartRenderer->horizontalBarChart($scoreCapped['labels'], $scoreCapped['data'], ['width' => 470, 'height' => max(140, count($scoreCapped['labels']) * 26)]) !!}
        </div>

        <h3>Agency Scorecard</h3>
        <table class="data">
            <thead><tr><th>Agency</th><th class="num">Total</th><th class="num">Completed</th><th class="num">Pending</th><th class="num">Avg Days</th></tr></thead>
            <tbody>
                @foreach($agencyScorecard as $row)
                    <tr>
                        <td>{{ $row['agency'] ?? '' }}</td>
                        <td class="num">{{ $fmt($row['total'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($row['completed'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($row['pending'] ?? 0) }}</td>
                        <td class="num">{{ $row['avg_days'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach([
        ['Agency Workload', 'Active caseload carried by each agency', $agencyWorkload ?? []],
        ['Referrals by Agency', 'Share of all referrals routed to each agency', $referralAgencyDistribution ?? []],
    ] as [$title, $desc, $dist])
        @continue(empty($dist['labels']))
        @php $c = $capped($dist); @endphp
        <div class="chart-container">
            <div class="chart-title">{{ $title }}</div>
            <div class="chart-subtitle">{{ $desc }}@if($c['omitted'] > 0) &mdash; showing top {{ count($c['labels']) }} of {{ count($dist['labels']) }} @endif</div>
            {!! $chartRenderer->horizontalBarChart($c['labels'], $c['data'], ['width' => 470, 'height' => max(140, count($c['labels']) * 24)]) !!}
        </div>
    @endforeach
    @endif

    {{-- ═══════════════ CASELOAD ═══════════════ --}}
    @if(!empty($caseStatusDistribution['labels']) || !empty($categoryDistribution) || !empty($caseIssueDistribution))
    <h2 class="page-break">Caseload Composition</h2>

    @if(!empty($caseStatusDistribution['labels']))
    <table class="chart-row">
        <tr>
            <td class="chart-cell">
                <div class="chart-container">
                    <div class="chart-title">Case Status</div>
                    <div class="chart-subtitle">Open versus closed cases in range</div>
                    {!! $chartRenderer->pieChart($caseStatusDistribution['labels'], $caseStatusDistribution['data'], ['size' => 200]) !!}
                </div>
            </td>
            <td class="table-cell">
                <table class="data">
                    <thead><tr><th>Status</th><th class="num">Cases</th></tr></thead>
                    <tbody>
                        @foreach($caseStatusDistribution['labels'] as $i => $label)
                            <tr><td>{{ $label }}</td><td class="num">{{ $fmt($caseStatusDistribution['data'][$i] ?? 0) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    @endif

    @foreach([
        ['Case Categories', 'Cases by assigned category', $categoryDistribution ?? [], 'Category'],
        ['Case Issues', 'Most reported issues and concerns', $caseIssueDistribution ?? [], 'Issue'],
    ] as [$title, $desc, $rows, $header])
        @continue(empty($rows))
        @php $c = $capped(['labels' => collect($rows)->pluck('name')->all(), 'data' => collect($rows)->pluck('count')->all()]); @endphp
        <table class="chart-row">
            <tr>
                <td class="chart-cell">
                    <div class="chart-container">
                        <div class="chart-title">{{ $title }}</div>
                        <div class="chart-subtitle">{{ $desc }}@if($c['omitted'] > 0) &mdash; top {{ count($c['labels']) }} charted @endif</div>
                        {!! $chartRenderer->barChart($c['labels'], $c['data'], ['width' => 440, 'height' => 175]) !!}
                    </div>
                </td>
                <td class="table-cell">
                    <table class="data">
                        <thead><tr><th>{{ $header }}</th><th class="num">Cases</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr><td>{{ $row['name'] ?? '' }}</td><td class="num">{{ $fmt($row['count'] ?? 0) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach
    @endif

    {{-- ═══════════════ GEOGRAPHY ═══════════════ --}}
    @if(!empty($geographicDistribution['labels']) || !empty($cityDistribution['labels']))
    <h2 class="page-break">Geography</h2>

    @foreach([
        ['Cases by Province', 'Province of the client\'s registered address', $geographicDistribution ?? [], 'Province', '#005288'],
        ['Cases by City / Municipality', 'City or municipality of the client\'s registered address', $cityDistribution ?? [], 'City / Municipality', '#0b7a75'],
    ] as [$title, $desc, $dist, $header, $colour])
        @continue(empty($dist['labels']))
        @php $c = $capped($dist); @endphp
        <table class="chart-row">
            <tr>
                <td class="chart-cell">
                    <div class="chart-container">
                        <div class="chart-title">{{ $title }}</div>
                        <div class="chart-subtitle">{{ $desc }}@if($c['omitted'] > 0) &mdash; top {{ count($c['labels']) }} of {{ count($dist['labels']) }} charted, all listed opposite @endif</div>
                        {!! $chartRenderer->horizontalBarChart($c['labels'], $c['data'], ['width' => 440, 'height' => max(140, count($c['labels']) * 24), 'color' => $colour]) !!}
                    </div>
                </td>
                <td class="table-cell">
                    <table class="data">
                        <thead><tr><th>{{ $header }}</th><th class="num">Cases</th></tr></thead>
                        <tbody>
                            @foreach($dist['labels'] as $i => $label)
                                <tr><td>{{ $label }}</td><td class="num">{{ $fmt($dist['data'][$i] ?? 0) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach
    @endif

    {{-- ═══════════════ CLIENT PROFILE ═══════════════ --}}
    @if(!empty($genderDistribution['labels']) || !empty($ageGroupDistribution['labels']) || !empty($vulnerabilityDistribution['labels']) || !empty($clientTypeDistribution['labels']) || !empty($employmentDistribution['labels']) || !empty($employmentOccupationBreakdown['labels']))
    <h2 class="page-break">Client Profile</h2>
    <p class="section-desc">
        Aggregated demographic data. Figures are counts of cases, not individuals, and
        small buckets are withheld — see the confidentiality notice on the cover.
    </p>

    @foreach([
        ['Client Type', 'OFW versus next of kin', $clientTypeDistribution ?? [], 'Type', 'pie'],
        ['Gender', 'Recorded client gender', $genderDistribution ?? [], 'Gender', 'pie'],
        ['Age Groups', 'Client age at time of intake', $ageGroupDistribution ?? [], 'Age Band', 'bar'],
        ['Vulnerability Indicators', 'Cases flagged with a vulnerability marker', $vulnerabilityDistribution ?? [], 'Indicator', 'bar'],
    ] as [$title, $desc, $dist, $header, $kind])
        @continue(empty($dist['labels']))
        <table class="chart-row">
            <tr>
                <td class="chart-cell">
                    <div class="chart-container">
                        <div class="chart-title">{{ $title }}</div>
                        <div class="chart-subtitle">{{ $desc }}</div>
                        @if($kind === 'pie')
                            {!! $chartRenderer->pieChart($dist['labels'], $dist['data'], ['size' => 195]) !!}
                        @else
                            {!! $chartRenderer->barChart($dist['labels'], $dist['data'], ['width' => 440, 'height' => 165]) !!}
                        @endif
                    </div>
                </td>
                <td class="table-cell">
                    <table class="data">
                        <thead><tr><th>{{ $header }}</th><th class="num">Cases</th></tr></thead>
                        <tbody>
                            @foreach($dist['labels'] as $i => $label)
                                <tr><td>{{ $label }}</td><td class="num">{{ $fmt($dist['data'][$i] ?? 0) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    @foreach([
        ['Previous Country of Employment', 'Where the client last worked', $employmentDistribution ?? [], 'Country', '#9b51b0'],
        ['Previous Occupation', 'Role the client last held overseas', $employmentOccupationBreakdown ?? [], 'Occupation', '#d9663b'],
    ] as [$title, $desc, $dist, $header, $colour])
        @continue(empty($dist['labels']))
        @php $c = $capped($dist); @endphp
        <div class="chart-container">
            <div class="chart-title">{{ $title }}</div>
            <div class="chart-subtitle">{{ $desc }}@if($c['omitted'] > 0) &mdash; top {{ count($c['labels']) }} of {{ count($dist['labels']) }} @endif</div>
            {!! $chartRenderer->horizontalBarChart($c['labels'], $c['data'], ['width' => 470, 'height' => max(140, count($c['labels']) * 24), 'color' => $colour]) !!}
        </div>
    @endforeach
    @endif

    {{-- ═══════════════ TOP RISKS ═══════════════ --}}
    <h2 class="page-break">Highest-Risk Active Work</h2>
    <p class="section-desc">
        Ranked by status severity plus age. Rows shaded red score above 100; amber above 60.
    </p>

    <h3>Top {{ count($topReferrals ?? []) }} Referrals by Risk</h3>
    <table class="data">
        <thead><tr><th>Case #</th><th>Agency</th><th>Status</th><th>Created</th><th class="num">Age</th><th class="num">Risk</th></tr></thead>
        <tbody>
            @forelse(($topReferrals ?? []) as $row)
                @php $riskClass = ($row->risk_score ?? 0) > 100 ? 'risk-high' : (($row->risk_score ?? 0) > 60 ? 'risk-mid' : ''); @endphp
                <tr class="{{ $riskClass }}">
                    <td>{{ $row->case_number ?? '' }}</td>
                    <td>{{ $row->agency ?? '' }}</td>
                    <td>{{ $row->status ?? '' }}</td>
                    <td>{{ $row->created_at ? e(\Carbon\Carbon::parse($row->created_at)->format('d M Y')) : '' }}</td>
                    <td class="num">{{ $row->age_days ?? '' }}d</td>
                    <td class="num">{{ $row->risk_score ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No active referrals in range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Top {{ count($topCases ?? []) }} Cases by Risk</h3>
    <table class="data">
        <thead><tr><th>Case #</th><th>Category</th><th>Issue</th><th>Status</th><th>Created</th><th class="num">Age</th><th class="num">Risk</th></tr></thead>
        <tbody>
            @forelse(($topCases ?? []) as $row)
                @php $riskClass = ($row->risk_score ?? 0) > 100 ? 'risk-high' : (($row->risk_score ?? 0) > 60 ? 'risk-mid' : ''); @endphp
                <tr class="{{ $riskClass }}">
                    <td>{{ $row->case_number ?? '' }}</td>
                    <td>{{ $row->category ?? '' }}</td>
                    <td>{{ $row->issue ?? '' }}</td>
                    <td>{{ $row->status ?? '' }}</td>
                    <td>{{ $row->created_at ? e(\Carbon\Carbon::parse($row->created_at)->format('d M Y')) : '' }}</td>
                    <td class="num">{{ $row->age_days ?? '' }}d</td>
                    <td class="num">{{ $row->risk_score ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No active cases in range.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ═══════════════ APPENDIX ═══════════════ --}}
    @php
        $apx = $appendix ?? ['limit' => 0, 'referrals' => [], 'cases' => [], 'referralsTotal' => 0, 'casesTotal' => 0];
    @endphp
    @if(!empty($apx['referrals']) || !empty($apx['cases']))
    <h2 class="page-break">Appendix &mdash; Active Work Detail</h2>
    <p class="section-desc">
        The highest-risk active rows, continuing the ranking above. This is a bounded
        extract, not the full record set &mdash; use the Excel export for every row.
    </p>

    @if(!empty($apx['referrals']))
        <h3>Active Referrals</h3>
        <div class="note-box">
            Showing {{ $fmt(count($apx['referrals'])) }} of {{ $fmt($apx['referralsTotal']) }} active referrals,
            ranked by risk. @if($apx['referralsTotal'] > count($apx['referrals'])) The remaining {{ $fmt($apx['referralsTotal'] - count($apx['referrals'])) }} are in the Excel export. @endif
        </div>
        <table class="data">
            <thead><tr><th>Case #</th><th>Agency</th><th>Services</th><th>Status</th><th>Created</th><th class="num">Age</th><th class="num">Risk</th></tr></thead>
            <tbody>
                @foreach($apx['referrals'] as $row)
                    <tr>
                        <td>{{ $row->case_number ?? '' }}</td>
                        <td>{{ $row->agency ?? '' }}</td>
                        <td>{{ Str::limit((string) ($row->required_services ?? ''), 48) }}</td>
                        <td>{{ $row->status ?? '' }}</td>
                        <td>{{ $row->created_at ? e(\Carbon\Carbon::parse($row->created_at)->format('d M Y')) : '' }}</td>
                        <td class="num">{{ $row->age_days ?? '' }}d</td>
                        <td class="num">{{ $row->risk_score ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($apx['cases']))
        <h3 class="page-break">Active Cases</h3>
        <div class="note-box">
            Showing {{ $fmt(count($apx['cases'])) }} of {{ $fmt($apx['casesTotal']) }} active cases,
            ranked by risk. @if($apx['casesTotal'] > count($apx['cases'])) The remaining {{ $fmt($apx['casesTotal'] - count($apx['cases'])) }} are in the Excel export. @endif
        </div>
        <table class="data">
            <thead><tr><th>Case #</th><th>Client Type</th><th>Category</th><th>Issue</th><th>Status</th><th>Created</th><th class="num">Age</th><th class="num">Risk</th></tr></thead>
            <tbody>
                @foreach($apx['cases'] as $row)
                    <tr>
                        <td>{{ $row->case_number ?? '' }}</td>
                        <td>{{ $row->client_type ?? '' }}</td>
                        <td>{{ Str::limit((string) ($row->category ?? ''), 30) }}</td>
                        <td>{{ Str::limit((string) ($row->issue ?? ''), 30) }}</td>
                        <td>{{ $row->status ?? '' }}</td>
                        <td>{{ $row->created_at ? e(\Carbon\Carbon::parse($row->created_at)->format('d M Y')) : '' }}</td>
                        <td class="num">{{ $row->age_days ?? '' }}d</td>
                        <td class="num">{{ $row->risk_score ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @endif

    {{-- ═══════════════ PROVENANCE ═══════════════ --}}
    <h2 class="page-break">Report Provenance</h2>
    <p class="section-desc">Recorded so any figure in this document can be reproduced or audited.</p>
    <table class="data">
        <thead><tr><th style="width:34%">Field</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td>Schema version</td><td>{{ $metadata['schema_version'] ?? '' }}</td></tr>
            <tr><td>Generated (UTC)</td><td>{{ $metadata['generated_at_utc'] ?? '' }}</td></tr>
            <tr><td>Generated ({{ $metadata['timezone'] ?? '' }})</td><td>{{ $metadata['generated_at_manila'] ?? '' }}</td></tr>
            <tr><td>Generated by</td><td>{{ $metadata['generated_by'] ?? '' }}</td></tr>
            <tr><td>Role scope</td><td>{{ $metadata['scope'] ?? '' }}</td></tr>
            <tr><td>Matching referrals</td><td>{{ $fmt($metadata['row_counts']['referral_details_matching'] ?? 0) }}</td></tr>
            <tr><td>Matching cases</td><td>{{ $fmt($metadata['row_counts']['case_details_matching'] ?? 0) }}</td></tr>
            <tr><td>Appendix limit</td><td>{{ $fmt($metadata['row_counts']['pdf_appendix_limit'] ?? 0) }} rows per table</td></tr>
            <tr><td>Small-cell suppression</td><td>Buckets below {{ $suppression['threshold'] ?? 5 }} withheld in demographic sections{{ ($suppression['applied'] ?? false) ? ' (applied)' : ' (none required)' }}</td></tr>
            <tr><td>AI insights included</td><td>{{ ($metadata['ai_insights_included'] ?? false) ? 'Yes' : 'No' }}</td></tr>
        </tbody>
    </table>

</body>
</html>
