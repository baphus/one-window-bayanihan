<x-mail::message>
<x-mail::status-badge status="rejected" label="Overdue" />

<p style="color: #a1a1aa; font-size: 12px; margin: 12px 0 24px 0;">Case {{ $caseNumber }}</p>

# Overdue Referral — {{ $overdueDays }} Days

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    A referral assigned to {{ $agencyName }} has exceeded the expected response window by {{ $overdueDays }} days.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'Client', 'value' => $clientName],
    ['label' => 'Agency', 'value' => $agencyName],
    ['label' => 'Service', 'value' => $requiredServices],
    ['label' => 'Referred', 'value' => $createdAt->format('M d, Y')],
    ['label' => 'Last Activity', 'value' => $lastUpdate->format('M d, Y')],
    ['label' => 'Days Overdue', 'value' => $overdueDays . ' days'],
]" />

@if($milestones->isNotEmpty())
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 28px 0 8px 0;">Milestones Recorded</p>
<x-mail::checklist :items="$milestones->pluck('title')->toArray()" :completed="[]" />
@endif

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 28px 0 24px 0;">
    Follow up with the assigned agency and escalate if no response is received.
</p>

<x-mail::action-card url="{{ route('referrals.show', $referral) }}" label="View Referral" :urgency="true" />

<x-mail::contact-footer />
</x-mail::message>
