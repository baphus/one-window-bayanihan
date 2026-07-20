<x-mail::message>
<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $referral->caseFile?->case_number ?? 'N/A' }}</p>

# New Referral Assigned

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
A referral has been assigned to your agency. Please review the details and respond within 5 business days.
</p>

<x-mail::detail-table :rows="[
['label' => 'Client', 'value' => ($referral->caseFile?->client?->first_name ?? '') . ' ' . ($referral->caseFile?->client?->last_name ?? '')],
['label' => 'Issue', 'value' => $referral->caseFile?->caseIssue?->name ?? 'N/A'],
['label' => 'From', 'value' => $referral->caseFile?->user?->name ?? 'DMW Region VII'],
['label' => 'Referred', 'value' => $referral->created_at->format('M d, Y')],
['label' => 'Response Due', 'value' => $referral->created_at->addDays(5)->format('M d, Y')],
]" />

<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 28px 0 8px 0;">Required Services</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">{{ $referral->required_services }}</p>

@if($referral->requirements && count($referral->requirements) > 0)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 0 0 8px 0;">Required Documents</p>

<x-mail::checklist :items="$referral->requirements" />
@endif

@if($referral->caseFile?->summary)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 28px 0 8px 0;">Case Summary</p>

<div style="background-color: #f8fafc; border-left: 3px solid #e4e4e7; padding: 16px 20px; margin: 0 0 28px 0;">
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0;">{{ $referral->caseFile->summary }}</p>
</div>
@endif

<x-mail::action-card url="{{ $url }}" label="Review Referral" :urgency="true" />

<p style="font-size: 13px; line-height: 1.7; color: #71717a; margin: 0;">
If your agency cannot provide these services, contact the case manager to discuss.
</p>

<x-mail::contact-footer />
</x-mail::message>
