<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Case Summary - {{ $case->case_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 24pt 28pt 30pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.2; color: #000; }
        h1 { font-size: 13pt; margin: 0 0 2pt; text-align:center; text-transform:uppercase; }
        h2 { font-size: 8pt; margin: 5pt 0 2pt; padding: 2pt 4pt; background:#eee; border: .5pt solid #000; text-transform:uppercase; page-break-after: avoid; }
        p { margin: 2pt 0; overflow-wrap:anywhere; }
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        td, th { padding:2pt 3pt; vertical-align:top; text-align:left; overflow-wrap:anywhere; border-bottom:.35pt solid #777; }
        .label { width:18%; font-weight:bold; }
        .small { font-size:7pt; }
        .compact { font-size:6.5pt; line-height:1.1; }
        .long { white-space:pre-wrap; overflow-wrap:anywhere; }
        .masthead { text-align:center; border-bottom:1pt solid #000; margin-bottom:5pt; padding-bottom:4pt; }
        .masthead .agency { font-size:10pt; font-weight:bold; text-transform:uppercase; }
        .masthead .office { font-size:8pt; font-weight:bold; }
        footer { position:fixed; bottom:-18pt; font-size:6pt; border-top:.5pt solid #000; width:100%; padding-top:2pt; }
        .section { page-break-inside:avoid; }
    </style>
</head>
<body>
    @php
        $text = fn ($value) => trim((string) $value) !== '' ? trim((string) $value) : null;
        $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : null;
        $label = fn ($value) => $value ? ucwords(strtolower(str_replace('_', ' ', $value))) : null;
        $clientName = collect([$case->first_name, $case->middle_name, $case->last_name, $case->suffix])->filter()->implode(' ');
        $address = app(\App\Services\PhilippineAddressService::class)->format($case->address_street, $case->address_barangay, $case->address_city_municipality, $case->address_province, $case->address_region);
        $brief = fn ($value, $limit) => \Illuminate\Support\Str::limit($text($value), $limit);
    @endphp
    <div class="masthead">
        <div class="agency">Republic of the Philippines · Department of Migrant Workers</div>
        <div class="office">Regional Office VII — Central Visayas</div>
        <div class="small">One Window Bayanihan · Case Management</div>
    </div>
    <h1>Case Summary — {{ $case->case_number }}</h1>

    <h2>Case and Intake</h2>
    <table>
        <tr><td class="label">Tracking ID</td><td colspan="3">{{ $text($case->tracker_number) }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $label($case->status) }}</td><td class="label">Date opened</td><td>{{ $date($case->created_at) }}</td></tr>
        <tr><td class="label">Intake date</td><td>{{ $date($case->intake_date) }}</td><td class="label">Client type</td><td>{{ $label($case->client_type) }}</td></tr>
        <tr><td class="label">Opened by</td><td>{{ $text($case->user?->name) }}</td><td class="label">Final approver</td><td>{{ $text($case->reviewer?->name) }}</td></tr>
    </table>

    <h2>Client Information</h2>
    <table>
        <tr><td class="label">Full name</td><td colspan="3">{{ $brief($clientName, 100) }}</td></tr>
        <tr><td class="label">Date of birth</td><td>{{ $date($case->date_of_birth) }}</td><td class="label">Age / Sex</td><td>{{ $text($case->age_at_intake) }} / {{ $text($case->sex) }}</td></tr>
        <tr><td class="label">Civil status</td><td>{{ $text($case->civil_status === 'OTHER' ? $case->civil_status_other : $case->civil_status) }}</td><td class="label">Contact no.</td><td>{{ $text($case->contact_number) }}</td></tr>
        <tr><td class="label">Email</td><td colspan="3">{{ $text($case->email) }}</td></tr>
        @if($address)<tr><td class="label">Address</td><td colspan="3">{{ $brief($address, 120) }}</td></tr>@endif
        <tr><td class="label">Deployment mode</td><td>{{ $label($case->deployment_mode) }}</td><td class="label">Employment status</td><td>{{ $label($case->employment_status) }}</td></tr>
        @if($case->return_reason)<tr><td class="label">Return reason</td><td colspan="3">{{ $label($case->return_reason) }}</td></tr>@endif
    </table>

    @if($case->employment_employer_name || $case->employment_employer_address || $case->employment_country || $case->employment_position || $case->employment_last_position || $case->employment_date_of_arrival)
        <h2>Employment</h2>
        <table>
            <tr><td class="label">Employer</td><td>{{ $brief($case->employment_employer_name, 65) }}</td><td class="label">Employer address</td><td>{{ $brief($case->employment_employer_address, 80) }}</td></tr>
            <tr><td class="label">Jobsite</td><td>{{ $brief($case->employment_country, 50) }}</td><td class="label">Position</td><td>{{ $brief($case->employment_position ?: $case->employment_last_position, 65) }}</td></tr>
        @if($case->employment_last_position && $case->employment_position && $case->employment_last_position !== $case->employment_position)<tr><td class="label">Last position</td><td colspan="3">{{ $brief($case->employment_last_position, 65) }}</td></tr>@endif
            <tr><td class="label">Arrival date</td><td colspan="3">{{ $date($case->employment_date_of_arrival) }}</td></tr>
        </table>
    @endif

    @if($case->nok_name || $case->nok_contact_number || $case->nok_email)
        <h2>Next of Kin</h2>
        <p>{{ $text($case->nok_name) }}@if($case->nok_relationship) — {{ $label($case->nok_relationship) }}@endif @if($case->nok_contact_number) · {{ $case->nok_contact_number }}@endif @if($case->nok_email) · {{ $case->nok_email }}@endif</p>
    @endif

    @if(is_array($case->confirmed_assessments) && count($case->confirmed_assessments))
        <h2>Confirmed Assessment</h2><p>{{ implode(', ', array_map($label, $case->confirmed_assessments)) }}@if($case->confirmed_assessment_other) · {{ $brief($case->confirmed_assessment_other, 80) }}@endif</p>
    @elseif(is_array($case->proposed_assessments) && count($case->proposed_assessments))
        <h2>Provisional Assessment</h2><p>{{ implode(', ', array_map($label, $case->proposed_assessments)) }}@if($case->proposed_assessment_other) · {{ $brief($case->proposed_assessment_other, 80) }}@endif</p>
    @endif
    @if($case->categories->isNotEmpty())<p><strong>Case categories:</strong> {{ $case->categories->pluck('name')->implode(', ') }}</p>@endif
    @if($case->vulnerability_indicator || $case->nok_vulnerability_indicator)<p><strong>Vulnerability:</strong> @if($case->vulnerability_indicator)OFW: {{ $brief($case->vulnerability_indicator, 100) }}@endif @if($case->nok_vulnerability_indicator)NOK: {{ $brief($case->nok_vulnerability_indicator, 100) }}@endif</p>@endif

    @if($case->summary)
    <h2>Concern</h2>
    <p class="long">{{ $brief($case->summary, 280) }}</p>
    @endif
    @if(isset($latestEvent) && $latestEvent)
        <p><strong>Latest case event ({{ $date($latestEvent->occurred_at ?: $latestEvent->created_at) }}):</strong> {{ $text($latestEvent->title) }}@if($latestEvent->description) — {{ \Illuminate\Support\Str::limit($text($latestEvent->description), 100) }}@endif</p>
    @endif

    <h2>Agency and Service Summary</h2>
    <table>
        <thead><tr><th style="width:20%">Agency</th><th style="width:38%">Category / services / concern</th><th style="width:17%">Status</th><th style="width:25%">Decision / updated</th></tr></thead>
        <tbody class="compact">
            @forelse($referrals->take(4) as $referral)
                <tr><td>{{ $text($referral->agency?->name) }}</td><td>{{ \Illuminate\Support\Str::limit(collect([$referral->category?->name, $referral->required_services, $referral->concern])->filter()->implode(' · '), 95) }}</td><td>{{ $label($referral->status) }}</td><td>{{ \Illuminate\Support\Str::limit(collect([$referral->decision, $referral->decision_comment])->filter()->implode(' · '), 55) }}@if($referral->updated_at)<br>{{ $date($referral->updated_at) }}@endif</td></tr>
            @empty
                <tr><td colspan="4">No agency referrals recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($referrals->count() > 4)<p class="small">{{ $referrals->count() - 4 }} more referrals in case workspace.</p>@endif

    @if(isset($documentAnnexes) && count($documentAnnexes))
        <h2>Uploaded Files</h2>
        <table><thead><tr><th>Document / filename</th><th style="width:22%">Type</th><th style="width:24%">Included</th></tr></thead><tbody>
        @foreach(array_slice($documentAnnexes, 0, 4) as $document)
            <tr><td>{{ $brief(data_get($document, 'name'), 70) }}<br>{{ $brief(data_get($document, 'file_name'), 70) }}</td><td>{{ $text(data_get($document, 'type')) }}</td><td>{{ data_get($document, 'included') ? 'Appended' : 'Not appended' }}</td></tr>
        @endforeach
        </tbody></table>
        @if(count($documentAnnexes) > 4)<p class="small">{{ count($documentAnnexes) - 4 }} more uploaded files in case workspace; appended supported documents remain labeled.</p>@endif
    @endif

    <p class="small" style="margin-top:6pt"><strong>CONFIDENTIAL</strong> — Authorized case handling only. This is a summary; full narrative, history and remaining referrals/files are available in case workspace.</p>
    <footer>CONFIDENTIAL — authorized case handling only · Exported {{ $exportedAt }} · {{ $case->case_number }}</footer>
</body>
</html>
