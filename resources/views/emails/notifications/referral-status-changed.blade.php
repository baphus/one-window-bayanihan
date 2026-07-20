<x-mail::message>
@php
$oldLabel = ucwords(strtolower(str_replace('_', ' ', $oldStatus)));
$newLabel = ucwords(strtolower(str_replace('_', ' ', $newStatus)));
@endphp

<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $referral->caseFile?->case_number ?? 'N/A' }}</p>

# Referral Status Updated

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
{{ $referral->agency?->name ?? 'The assigned agency' }} updated the referral status from <strong>{{ $oldLabel }}</strong> to <strong>{{ $newLabel }}</strong>.
</p>

<x-mail::detail-table :rows="[
['label' => 'Services', 'value' => $referral->required_services],
['label' => 'Agency', 'value' => $referral->agency?->name ?? 'N/A'],
['label' => 'Status', 'value' => $oldLabel . ' → ' . $newLabel],
]" />

@if($newStatus === 'PROCESSING')
<p style="font-size: 14px; line-height: 1.7; color: #3f3f46; margin: 24px 0;">The referral has been accepted and is now being processed.</p>
@elseif($newStatus === 'COMPLETED')
<p style="font-size: 14px; line-height: 1.7; color: #3f3f46; margin: 24px 0;">The referral has been completed. Review the outcome and update the case accordingly.</p>
@elseif($newStatus === 'FOR_COMPLIANCE')
<p style="font-size: 14px; line-height: 1.7; color: #3f3f46; margin: 24px 0;">Additional documents or information are required before the agency can proceed.</p>
@elseif($newStatus === 'REJECTED')
<p style="font-size: 14px; line-height: 1.7; color: #3f3f46; margin: 24px 0;">The referral was declined.@if($referral->decision_comment) Reason: {{ $referral->decision_comment }}@endif</p>
@endif

<x-mail::action-card url="{{ $url }}" label="View Referral" />

<x-mail::contact-footer />
</x-mail::message>
