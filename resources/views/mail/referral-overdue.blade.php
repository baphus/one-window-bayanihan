<x-mail::message>
<x-mail::status-badge status="rejected" label="Overdue" />

<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0;">Overdue Referral</h1>

<x-mail::detail-table :rows="[
    ['label' => 'Case Number', 'value' => $caseNumber],
    ['label' => 'Client', 'value' => $clientName],
    ['label' => 'Agency', 'value' => $agencyName],
    ['label' => 'Service Required', 'value' => $requiredServices],
    ['label' => 'Created', 'value' => $createdAt->format('M d, Y')],
    ['label' => 'Last Update', 'value' => $lastUpdate->format('M d, Y')],
    ['label' => 'Days Overdue', 'value' => $overdueDays],
]" />

@if($milestones->isNotEmpty())
<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 24px 0 12px 0;">Milestones</h3>
<x-mail::checklist :items="$milestones->pluck('title')->toArray()" :completed="[]" />
@endif

<x-mail::action-card url="{{ route('referrals.show', $referral) }}" label="View Referral" />

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">
    This referral has been pending for over {{ $overdueDays }} days. Please take the necessary action to process or close this referral at your earliest convenience.
</p>

<x-mail::contact-footer />
</x-mail::message>
