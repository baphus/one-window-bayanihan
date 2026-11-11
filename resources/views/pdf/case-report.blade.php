<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Case Summary - {{ $case->case_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 28pt 32pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.2; color: #000; }
        h1 { font-size: 14pt; margin: 0 0 3pt; }
        h2 { font-size: 10pt; margin: 9pt 0 3pt; padding-bottom: 2pt; border-bottom: 0.5pt solid #000; }
        p { margin: 3pt 0; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { padding: 2pt 4pt; vertical-align: top; text-align: left; overflow-wrap: anywhere; }
        .label { width: 18%; font-weight: bold; }
        .value { width: 32%; }
        .agencies td, .agencies th { border-bottom: 0.5pt solid #000; }
        .small { font-size: 8pt; }
        footer { position: fixed; bottom: -12pt; font-size: 7pt; }
        .compact { font-size: 7pt; line-height: 1; }
    </style>
</head>
<body>
    @php
        $text = fn ($value) => trim(preg_replace('/\s+/u', ' ', (string) $value)) ?: '—';
        $short = fn ($value, $limit) => \Illuminate\Support\Str::limit($text($value), $limit, '…');
        $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '—';
        $label = fn ($value) => ucwords(strtolower(str_replace('_', ' ', $value ?? '—')));
    @endphp
    @if(!empty($avatar))
        <img src="{{ $avatar }}" alt="Intake photo" style="position: absolute; top: 0; right: 0; width: 42pt; height: 42pt; object-fit: contain;">
    @endif
    <h1>Case Summary — {{ $case->case_number }}</h1>
    <p class="small">One Window Bayanihan · Department of Migrant Workers, Region VII</p>

    <h2>Case Details</h2>
    <table>
        <tr><td class="label">Status</td><td class="value">{{ $label($case->status) }}</td><td class="label">Tracking ID</td><td class="value">{{ $text($case->tracker_number) }}</td></tr>
        <tr><td class="label">Opened</td><td>{{ $date($case->created_at) }}</td><td class="label">Closed</td><td>{{ $date($case->closed_at) }}</td></tr>
        <tr><td class="label">Case Manager</td><td colspan="3">{{ $text($case->user?->name) }}</td></tr>
        <tr><td class="label">Vulnerability</td><td colspan="3">{{ $short($case->vulnerability_indicator, $textLimit) }}</td></tr>
    </table>

    <h2>Case Intake Record</h2>
    <table>
        <tr><td class="label">Full Name</td><td colspan="3">{{ $text(collect([$case->first_name, $case->middle_name, $case->last_name, $case->suffix])->filter()->implode(' ')) }}</td></tr>
        <tr><td class="label">Intake Type</td><td class="value">{{ $label($case->client_type) }}</td><td class="label">Date of Birth</td><td class="value">{{ $date($case->date_of_birth) }}</td></tr>
        <tr><td class="label">Sex</td><td>{{ $text($case->sex) }}</td><td class="label">Contact</td><td>{{ $text($case->contact_number) }}</td></tr>
        <tr><td class="label">Email</td><td colspan="3">{{ $text($case->email) }}</td></tr>
        <tr><td class="label">Address</td><td colspan="3">{{ $short(app(\App\Services\PhilippineAddressService::class)->format($case->address_street, $case->address_barangay, $case->address_city_municipality, $case->address_province, $case->address_region), $textLimit) }}</td></tr>
    </table>

    @if($case->employment_employer_name || $case->employment_country)
        <h2>Employment</h2>
        <table>
            <tr><td class="label">Employer</td><td colspan="3">{{ $short($case->employment_employer_name, $textLimit) }}</td></tr>
            <tr><td class="label">Position</td><td class="value">{{ $short($case->employment_position ?: $case->employment_last_position, 100) }}</td><td class="label">Jobsite</td><td class="value">{{ $short($case->employment_country, 80) }}</td></tr>
            <tr><td class="label">Arrival</td><td colspan="3">{{ $date($case->employment_date_of_arrival) }}</td></tr>
        </table>
    @endif

    <h2>Concern and Current Progress</h2>
    <p><strong>Summary:</strong> {{ $short($case->summary, $textLimit) }}</p>
    <p><strong>Latest progress:</strong> @if($latestEvent){{ $date($latestEvent->occurred_at ?: $latestEvent->created_at) }} — {{ $short($latestEvent->title, 120) }}. {{ $short($latestEvent->description, $textLimit) }}@else No activity recorded.@endif</p>

    <h2>Agency Involvement / Pending Services</h2>
    <table class="agencies">
        <thead><tr><th style="width:30%">Agency</th><th style="width:35%">Category / Concern / Services</th><th style="width:20%">Status</th><th style="width:15%">Updated</th></tr></thead>
        <tbody class="compact">
            @forelse($referrals as $referral)
                <tr><td>{{ $text($referral->agency?->name) }}</td><td>{{ $short($referral->category?->name, 70) }} — {{ $short($referral->concern, $textLimit) }}. {{ $short($referral->required_services, 70) }}</td><td>{{ $label($referral->status) }}</td><td>{{ $date($referral->updated_at) }}</td></tr>
            @empty
                <tr><td colspan="4">No agency referrals recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    <footer>Confidential — authorized case handling only. Exported {{ $exportedAt }}. See full case record for shortened text, history and documents.</footer>
</body>
</html>
