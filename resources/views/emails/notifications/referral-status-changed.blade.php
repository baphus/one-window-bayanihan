<x-mail::message>
@php
$oldLabel = ucwords(strtolower(str_replace('_', ' ', $oldStatus)));
$newLabel = ucwords(strtolower(str_replace('_', ' ', $newStatus)));
$clientName = trim(($referral->caseFile?->client?->first_name ?? '').' '.($referral->caseFile?->client?->last_name ?? ''));
$categories = $referral->caseFile?->categories?->isNotEmpty()
    ? $referral->caseFile->categories->pluck('name')->implode(', ')
    : ($referral->caseFile?->category?->name ?? 'N/A');
@endphp

<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $referral->caseFile?->case_number ?? 'N/A' }}</p>

# Referral Status Updated

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
{{ $referral->agency?->name ?? 'The assigned agency' }} updated the referral status from <strong>{{ $oldLabel }}</strong> to <strong>{{ $newLabel }}</strong>.
</p>

<x-mail::detail-table :rows="[
['label' => 'Client', 'value' => $clientName !== '' ? $clientName : 'N/A'],
['label' => 'Categories', 'value' => $categories],
['label' => 'Agency', 'value' => $referral->agency?->name ?? 'N/A'],
['label' => 'Services', 'value' => $referral->required_services ?: 'N/A'],
['label' => 'Status', 'value' => $oldLabel . ' → ' . $newLabel],
['label' => 'Referred On', 'value' => $referral->created_at?->format('M d, Y') ?? 'N/A'],
['label' => 'Updated By', 'value' => ($actorName ?? '') !== '' ? $actorName : 'System'],
['label' => 'Updated At', 'value' => ($referral->updated_at ?? now())->format('M d, Y h:i A')],
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

@if(($reason ?? '') !== '')
@php
$reasonLabel = \App\Models\Referral::REJECTION_REASON_LABELS[$reason] ?? ucwords(strtolower(str_replace('_', ' ', $reason)));
@endphp
<p style="font-size: 14px; line-height: 1.7; color: #3f3f46; margin: 24px 0;">Rejection reason: <strong>{{ $reasonLabel }}</strong></p>
@endif

@if($referral->notes)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 28px 0 8px 0;">Referral Notes</p>

<div style="background-color: #f8fafc; border-left: 3px solid #e4e4e7; padding: 16px 20px; margin: 0 0 28px 0;">
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0;">{{ $referral->notes }}</p>
</div>
@endif

<x-mail::action-card url="{{ $url }}" label="View Referral" />

<x-mail::contact-footer />
</x-mail::message>
