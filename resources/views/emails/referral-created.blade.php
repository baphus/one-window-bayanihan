<x-mail::message>
# New Referral Assigned

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    A new referral has been assigned to your agency for processing.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'Case', 'value' => $referral->caseFile->case_number ?? '—'],
    ['label' => 'Agency', 'value' => $referral->agency->name ?? '—'],
    ['label' => 'Services', 'value' => $referral->required_services],
    ['label' => 'Status', 'value' => ucwords(strtolower(str_replace('_', ' ', $referral->status)))],
    ['label' => 'Date', 'value' => $referral->created_at->format('M d, Y')],
]" />

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 28px 0 24px 0;">
    Please review the referral details and begin processing at your earliest convenience.
</p>

<x-mail::action-card url="{{ $url }}" label="View Referral" />

<x-mail::contact-footer />
</x-mail::message>
