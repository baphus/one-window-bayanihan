<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Case Report - {{ $case->case_number }}</title>
    <style>
        @page { margin: 32px 36px; footer: page-footer; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.5; color: #1e293b; }

        .report-header { border-bottom: 3px solid #005288; padding-bottom: 12px; margin-bottom: 16px; }
        .report-header h1 { margin: 0 0 2px; font-size: 18px; color: #005288; font-weight: 700; letter-spacing: -0.3px; }
        .report-header .sub { font-size: 10px; color: #475569; }
        .report-header .meta { font-size: 7.5px; color: #64748b; margin-top: 2px; }

        h2 { margin: 18px 0 8px; font-size: 11px; color: #005288; font-weight: 700; border-bottom: 2px solid #005288; padding-bottom: 3px; }

        /* ── Info table ── */
        table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.info-table td { padding: 4px 8px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
        table.info-table td.label { font-weight: 700; color: #475569; width: 28%; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.05em; }
        table.info-table td.value { color: #1e293b; }

        /* ── Status badges ── */
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 8px; font-weight: 700; }
        .status-OPEN { background: #dbeafe; color: #1e40af; }
        .status-CLOSED { background: #d1fae5; color: #065f46; }
        .status-DRAFT { background: #f1f5f9; color: #475569; }
        .status-ARCHIVED { background: #e2e8f0; color: #64748b; }

        /* ── Case highlight cards ── */
        .highlight-row { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 5px; margin: 8px 0; }
        .highlight-row td { border: 1px solid #e2e8f0; background: #f8fafc; padding: 8px 10px; text-align: center; border-radius: 4px; }
        .highlight-row .hl-value { font-size: 14px; font-weight: 800; color: #005288; }
        .highlight-row .hl-label { font-size: 7px; text-transform: uppercase; color: #64748b; margin-top: 2px; }

        /* ── Referral status summary ── */
        .referral-summary { margin: 8px 0 12px; }
        .referral-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
        .referral-dot-label { font-size: 8px; margin-right: 12px; vertical-align: middle; }
        .dot-PENDING { background: #f59e0b; }
        .dot-PROCESSING { background: #3b82f6; }
        .dot-FOR_COMPLIANCE { background: #f97316; }
        .dot-COMPLETED { background: #22c55e; }
        .dot-REJECTED { background: #ef4444; }
        .dot-IN_PROGRESS { background: #3b82f6; }

        /* ── Data table ── */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data-table th { background: #005288; color: white; padding: 5px 8px; text-align: left; font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        table.data-table td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.data-table tr:nth-child(even) td { background: #f8fafc; }

        /* ── Visual timeline ── */
        .timeline { margin: 8px 0; }
        .timeline-item { position: relative; padding-left: 24px; padding-bottom: 12px; border-left: 2px solid #e2e8f0; margin-left: 6px; }
        .timeline-item:last-child { border-left: 2px solid transparent; }
        .timeline-dot { position: absolute; left: -7px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #005288; border: 2px solid #fff; }
        .timeline-date { font-size: 7.5px; color: #64748b; font-weight: 600; }
        .timeline-title { font-size: 9px; font-weight: 700; color: #1e293b; margin-top: 1px; }
        .timeline-desc { font-size: 8px; color: #64748b; margin-top: 1px; }

        /* ── Dividers ── */
        .section-divider { border: none; border-top: 1px solid #e2e8f0; margin: 16px 0; }

        .section-note { font-size: 8px; color: #64748b; font-style: italic; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>One Window Bayanihan Assistance Program</h1>
        <div class="sub">Case Report &mdash; <strong>{{ $case->case_number }}</strong></div>
        <div class="meta">
            Tracking ID: {{ $case->tracking_id ?? '—' }} &bull;
            Exported: {{ $exportedAt }} &bull;
            Department of Migrant Workers &mdash; Region VII
        </div>
    </div>

    {{-- Case Highlights --}}
    <table class="highlight-row">
        <tr>
            <td>
                <div class="hl-value"><span class="status-badge status-{{ $case->status }}">{{ $case->status }}</span></div>
                <div class="hl-label">Status</div>
            </td>
            <td>
                <div class="hl-value">{{ $case->client_type === 'OFW' ? 'OFW' : 'NOK' }}</div>
                <div class="hl-label">Client Type</div>
            </td>
            <td>
                <div class="hl-value">{{ $referrals->count() }}</div>
                <div class="hl-label">Referrals</div>
            </td>
            <td>
                <div class="hl-value">{{ $case->created_at ? \Carbon\Carbon::parse($case->created_at)->diffInDays(now()) : '—' }}d</div>
                <div class="hl-label">Case Age</div>
            </td>
        </tr>
    </table>

    {{-- Case Information --}}
    <h2>Case Information</h2>
    <table class="info-table">
        <tr>
            <td class="label">Case Number</td>
            <td class="value">{{ $case->case_number }}</td>
            <td class="label">Tracking ID</td>
            <td class="value">{{ $case->tracking_id ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Categories</td>
            <td class="value">{{ ($case->categories ?? collect())->pluck('name')->filter()->unique()->sort()->implode(', ') ?: ($case->category->name ?? '—') }}</td>
            <td class="label">Case Issue</td>
            <td class="value">{{ $case->caseIssue->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Created By</td>
            <td class="value">{{ $case->user->name ?? '—' }}</td>
            <td class="label">Date Opened</td>
            <td class="value">{{ $case->created_at ? \Carbon\Carbon::parse($case->created_at)->format('M d, Y') : '—' }}</td>
        </tr>
    </table>

    <hr class="section-divider">

    {{-- Client Profile --}}
    <h2>Client Profile</h2>
    <table class="info-table">
        <tr>
            <td class="label">Full Name</td>
            <td class="value">
                {{ trim($client->last_name . ', ' . $client->first_name . ' ' . $client->middle_initial) }}
                @if($client->suffix) {{ $client->suffix }} @endif
            </td>
            <td class="label">Date of Birth</td>
            <td class="value">{{ $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('M d, Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Sex</td>
            <td class="value">{{ $client->sex ?? '—' }}</td>
            <td class="label">Contact</td>
            <td class="value">{{ $client->contact_number ?? '—' }}</td>
        </tr>
        @if($address)
        <tr>
            <td class="label">Address</td>
            <td class="value" colspan="3">
                {{ collect([$address->street, $address->barangay, $address->city_municipality, $address->province, $address->region])->filter()->implode(', ') ?: '—' }}
            </td>
        </tr>
        @endif
    </table>

    @if($employment)
    <h2>Employment</h2>
    <table class="info-table">
        <tr>
            <td class="label">Employer</td>
            <td class="value">{{ $employment->employer_name ?? '—' }}</td>
            <td class="label">Country</td>
            <td class="value">{{ $employment->last_country ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Position</td>
            <td class="value">{{ $employment->last_position ?? '—' }}</td>
            <td class="label">Period</td>
            <td class="value">
                @if($employment->start_date)
                    {{ \Carbon\Carbon::parse($employment->start_date)->format('M Y') }}
                    – {{ $employment->end_date ? \Carbon\Carbon::parse($employment->end_date)->format('M Y') : 'Present' }}
                @else — @endif
            </td>
        </tr>
    </table>
    @endif

    @if($nok)
    <h2>Next of Kin</h2>
    <table class="info-table">
        <tr>
            <td class="label">Name</td>
            <td class="value">{{ trim($nok->last_name . ', ' . $nok->first_name . ' ' . $nok->middle_initial) ?: '—' }}</td>
            <td class="label">Relationship</td>
            <td class="value">{{ $nok->relationship ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Contact</td>
            <td class="value">{{ $nok->phone_number ?? '—' }}</td>
            <td class="label">Email</td>
            <td class="value">{{ $nok->email ?? '—' }}</td>
        </tr>
    </table>
    @endif

    @if($case->summary)
    <h2>Case Summary</h2>
    <p style="margin-bottom: 12px; font-size: 9px; line-height: 1.6;">{{ $case->summary }}</p>
    @endif

    <hr class="section-divider">

    {{-- Referrals with status visualization --}}
    <h2>Referrals</h2>
    @if($referrals->count() > 0)
    <div class="referral-summary">
        @php
            $statusCounts = $referrals->groupBy('status')->map->count();
        @endphp
        @foreach($statusCounts as $status => $count)
            <span class="referral-dot dot-{{ $status }}"></span>
            <span class="referral-dot-label">{{ ucwords(strtolower(str_replace('_', ' ', $status))) }} ({{ $count }})</span>
        @endforeach
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Agency</th><th>Service</th><th>Status</th><th>Referred</th><th>Updated</th></tr>
        </thead>
        <tbody>
            @foreach($referrals as $referral)
            <tr>
                <td>{{ $referral->agency->name ?? '—' }}</td>
                <td>{{ $referral->required_services ?? '—' }}</td>
                <td><strong>{{ $referral->status }}</strong></td>
                <td>{{ $referral->created_at ? \Carbon\Carbon::parse($referral->created_at)->format('M d, Y') : '—' }}</td>
                <td>{{ $referral->updated_at ? \Carbon\Carbon::parse($referral->updated_at)->format('M d, Y') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="section-note">No referrals have been made for this case.</p>
    @endif

    <hr class="section-divider">

    {{-- Visual Timeline --}}
    <h2>Activity Timeline</h2>
    @if($milestones->count() > 0)
    <div class="timeline">
        @foreach($milestones as $milestone)
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-date">
                {{ $milestone->occurred_at ? \Carbon\Carbon::parse($milestone->occurred_at)->format('M d, Y h:i A') : ($milestone->created_at ? \Carbon\Carbon::parse($milestone->created_at)->format('M d, Y h:i A') : '—') }}
            </div>
            <div class="timeline-title">
                {{ $milestone->title ?? ucwords(str_replace('_', ' ', $milestone->type ?? 'update')) }}
            </div>
            @if($milestone->description)
            <div class="timeline-desc">{{ $milestone->description }}</div>
            @endif
        </div>
        @endforeach
    </div>
    @else
    <p class="section-note">No activity recorded for this case.</p>
    @endif

    <htmlpagefooter name="page-footer">
        <table style="width:100%; border-top:1px solid #e2e8f0; font-size:7px; color:#94a3b8; padding-top:4px;">
            <tr>
                <td style="text-align:left;">One Window Bayanihan &bull; DMW Region VII</td>
                <td style="text-align:center;">Case {{ $case->case_number }}</td>
                <td style="text-align:right;">Exported {{ $exportedAt }}</td>
            </tr>
        </table>
    </htmlpagefooter>
</body>
</html>
