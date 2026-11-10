<x-mail::message>
# Your Request Is Being Reviewed

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    We received your request. It is currently being reviewed by a Case Manager from the Department of Migrant Workers (DMW) Region VII.
</p>

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Request Details</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 8px 0;">
    <strong>Request Reference:</strong> {{ $caseNumber }}<br>
    <strong>Tracker Number:</strong> {{ $trackerNumber }}
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 20px 0 28px 0;">
    Keep your tracker number safe — you will need it to check your request status. You will receive another email once a Case Manager completes the review.
</p>

<x-mail::action-card url="{{ route('track.index', ['tracker_number' => $trackerNumber]) }}" label="Track Your Request" />

@if (! $hasAccount)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 32px 0 8px 0;">Track faster with an account</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    Create an account using the same email you provided ({{ $case->client->email ?? $case->draft_client_data['email'] ?? '' }}) so your request links automatically. Verify with a one-time code on the tracking portal and register in seconds.
</p>

<x-mail::action-card url="{{ route('track.index', ['tracker_number' => $trackerNumber]) }}" label="Create Account / Track Faster" />
@endif

<x-mail::contact-footer />
</x-mail::message>
