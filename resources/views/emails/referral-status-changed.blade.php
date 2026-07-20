<x-mail::message>
# Referral Status Updated

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    A referral assigned to your agency has a status change.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'Case', 'value' => $referral->caseFile->case_number ?? '—'],
    ['label' => 'Agency', 'value' => $referral->agency->name ?? '—'],
    ['label' => 'Services', 'value' => $referral->required_services],
    ['label' => 'Previous', 'value' => ucwords(strtolower(str_replace('_', ' ', $oldStatus)))],
    ['label' => 'Current', 'value' => ucwords(strtolower(str_replace('_', ' ', $newStatus)))],
]" />

<x-mail::action-card url="{{ $url }}" label="View Referral" />

<x-mail::contact-footer />
</x-mail::message>
