<x-mail::message>
# Your Case Is Being Evaluated

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    We received your request. It is currently being evaluated by a Case Manager from the Department of Migrant Workers (DMW) Region VII.
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Case Details</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 8px 0;">
    <strong>Case Number:</strong> {{ $caseNumber }}<br>
    <strong>Tracker Number:</strong> {{ $trackerNumber }}
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 20px 0 28px 0;">
    Keep your tracker number safe — you will need it to check the status of your case. You will receive another email once a Case Manager accepts your case.
</p>

<x-mail::action-card url="{{ route('track.index', ['tracker_number' => $trackerNumber]) }}" label="Track Your Case" />

@if (! $hasAccount)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 32px 0 8px 0;">Track faster with an account</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    Create an account using the same email you filed with ({{ $case->client->email ?? $case->draft_client_data['email'] ?? '' }}) so your case links automatically. Verify with a one-time code on the tracking portal and register in seconds.
</p>

<x-mail::action-card url="{{ route('track.index', ['tracker_number' => $trackerNumber]) }}" label="Create Account / Track Faster" />
@endif

<x-mail::contact-footer />
</x-mail::message>
