<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Case Report - {{ $case->case_number }}</title>
    <style>
        @page { margin: 32px 36px; footer: page-footer; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.5; color: #1e293b; }

        .report-header { border-bottom: 3px solid #005288; padding-bottom: 12px; margin-bottom: 16px; }
        .report-header h1 { margin: 0 0 2px; font-size: 20px; color: #005288; font-weight: 700; letter-spacing: -0.3px; }
        .report-header .sub { font-size: 10px; color: #475569; }
        .report-header .meta { font-size: 8px; color: #64748b; margin-top: 2px; }

        h2 { margin: 20px 0 8px; font-size: 12px; color: #005288; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        h2:first-of-type { margin-top: 0; }

        table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.info-table td { padding: 4px 8px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
        table.info-table td.label { font-weight: 700; color: #475569; width: 30%; font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        table.info-table td.value { color: #1e293b; }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data-table th { background: #005288; color: white; padding: 6px 8px; text-align: left; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        table.data-table td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        table.data-table tr:nth-child(even) td { background: #f8fafc; }

        .status-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: 700; }
        .status-OPEN { background: #dbeafe; color: #1e40af; }
        .status-CLOSED { background: #d1fae5; color: #065f46; }
        .status-DRAFT { background: #f1f5f9; color: #475569; }
        .status-ARCHIVED { background: #e2e8f0; color: #64748b; }

        .referral-status { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: 700; }
        .referral-PENDING { background: #fef3c7; color: #92400e; }
        .referral-IN_PROGRESS { background: #dbeafe; color: #1e40af; }
        .referral-COMPLETED { background: #d1fae5; color: #065f46; }
        .referral-REJECTED { background: #fee2e2; color: #991b1b; }

        .section-note { font-size: 8px; color: #64748b; font-style: italic; margin-bottom: 8px; }

        footer.page-footer { font-size: 7px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>One Window Bayanihan Assistance Program</h1>
        <div class="sub">Case Report &mdash; <strong>{{ $case->case_number }}</strong></div>
        <div class="meta">
            Tracking ID: {{ $case->tracking_id }} &bull;
            Exported: {{ $exportedAt }} &bull;
            DMW Region VII
        </div>
    </div>

    {{-- Case Information --}}
    <h2>Case Information</h2>
    <table class="info-table">
        <tr>
            <td class="label">Case Number</td>
            <td class="value">{{ $case->case_number }}</td>
            <td class="label">Tracking ID</td>
            <td class="value">{{ $case->tracking_id }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td class="value"><span class="status-badge status-{{ $case->status }}">{{ $case->status }}</span></td>
            <td class="label">Client Type</td>
            <td class="value">{{ $case->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin' }}</td>
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
        <tr>
            <td class="label">Last Updated</td>
            <td class="value">{{ $case->updated_at ? \Carbon\Carbon::parse($case->updated_at)->format('M d, Y') : '—' }}</td>
            <td class="label"></td>
            <td class="value"></td>
        </tr>
    </table>

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
            <td class="label">Email</td>
            <td class="value">{{ $client->email ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Contact Number</td>
            <td class="value">{{ $client->contact_number ?? '—' }}</td>
            <td class="label"></td>
            <td class="value"></td>
        </tr>
        @if($address)
        <tr>
            <td class="label">Complete Address</td>
            <td class="value" colspan="3">
                {{ collect([$address->street, $address->barangay, $address->city_municipality, $address->province, $address->region])->filter()->implode(', ') ?: '—' }}
            </td>
        </tr>
        @endif
    </table>

    {{-- Employment History --}}
    @if($employment)
    <h2>Employment History</h2>
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
                @else
                    —
                @endif
            </td>
        </tr>
    </table>
    @endif

    {{-- Next of Kin --}}
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

    {{-- Case Summary --}}
    @if($case->summary)
    <h2>Case Summary</h2>
    <p style="margin-bottom: 12px; font-size: 9px; line-height: 1.6;">{{ $case->summary }}</p>
    @endif

    {{-- Referrals --}}
    <h2>Referrals</h2>
    @if($referrals->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Agency</th>
                <th>Service</th>
                <th>Status</th>
                <th>Date Referred</th>
                <th>Last Updated</th>
            </tr>
        </thead>
        <tbody>
            @foreach($referrals as $referral)
            <tr>
                <td>{{ $referral->agency->name ?? '—' }}</td>
                <td>{{ $referral->required_services ?? '—' }}</td>
                <td><span class="referral-status referral-{{ $referral->status }}">{{ $referral->status }}</span></td>
                <td>{{ $referral->created_at ? \Carbon\Carbon::parse($referral->created_at)->format('M d, Y') : '—' }}</td>
                <td>{{ $referral->updated_at ? \Carbon\Carbon::parse($referral->updated_at)->format('M d, Y') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="section-note">No referrals have been made for this case.</p>
    @endif

    {{-- Timeline --}}
    <h2>Activity Timeline</h2>
    @if($milestones->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Event</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach($milestones as $milestone)
            <tr>
                <td style="white-space: nowrap;">{{ $milestone->occurred_at ? \Carbon\Carbon::parse($milestone->occurred_at)->format('M d, Y h:i A') : ($milestone->created_at ? \Carbon\Carbon::parse($milestone->created_at)->format('M d, Y h:i A') : '—') }}</td>
                <td>{{ $milestone->title ?? ucwords(str_replace('_', ' ', $milestone->type ?? 'update')) }}</td>
                <td>{{ $milestone->description ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="section-note">No activity recorded for this case.</p>
    @endif

    <footer class="page-footer">
        One Window Bayanihan Assistance Program &bull; DMW Region VII &bull; Exported {{ $exportedAt }}
    </footer>
</body>
</html>
