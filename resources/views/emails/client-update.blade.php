<x-mail::message>
<x-mail::status-badge status="{{ strtolower($case->status) }}" label="{{ ucwords(strtolower(str_replace('_', ' ', $case->status))) }}" />

<p style="color: #a1a1aa; font-size: 12px; margin: 12px 0 24px 0;">Case {{ $case->case_number }}</p>

# Case Update

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $case->client->first_name ?? 'Sir/Madam' }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    {{ $message }}
</p>

@if($case->caseEvents && $case->caseEvents->count() > 0)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Timeline</p>
<x-mail::timeline :events="$case->caseEvents" />
@endif

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 32px 0 8px 0;">Next Steps</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    No action is required on your part at this time. You will receive another notification when there is further progress.
</p>

<x-mail::action-card url="{{ route('track.show', $case->tracker_number) }}" label="Track Your Case" />

<x-mail::contact-footer />
</x-mail::message>
