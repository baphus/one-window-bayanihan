<x-mail::message>
<x-mail::status-badge
    status="{{ strtolower($case->status) }}"
    label="{{ ucwords(strtolower(str_replace('_', ' ', $case->status))) }}"
/>

<p style="color: #a1a1aa; font-size: 13px; margin: 0 0 16px 0;">
    Case {{ $case->case_number }} · Updated {{ $case->updated_at->format('M d, Y') }}
</p>

<p style="font-size: 16px; line-height: 1.6; color: #18181b; margin: 0 0 16px 0;">
    Hi {{ $case->client->first_name ?? 'there' }},
</p>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">
    {{ $message }}
</p>

<x-mail::timeline :events="$case->caseEvents" />

<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 32px 0 12px 0;">What happens next</h3>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">
    You don't need to do anything right now. We'll send you another update when there's progress on your case.
</p>

<x-mail::action-card
    url="{{ route('track.show', $case->tracker_number) }}"
    label="Track Your Case"
/>

<x-mail::security-notice />
<x-mail::contact-footer />
</x-mail::message>
