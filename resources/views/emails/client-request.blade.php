<x-mail::message>
<x-mail::status-badge status="{{ $clientRequest->type === \App\Models\ReferralClientRequest::TYPE_DOCUMENT_REQUEST ? 'pending' : 'processing' }}" label="{{ $requestTypeLabel }}" />

<p style="color: #a1a1aa; font-size: 12px; margin: 12px 0 24px 0;">Case {{ $caseNumber }}</p>

# Action Required

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $clientName }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    {{ $agencyName }} requires the following from you to continue processing your case.
</p>

@if($clientRequest->type === \App\Models\ReferralClientRequest::TYPE_DOCUMENT_REQUEST && count($checklistItems) > 0)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 24px 0 8px 0;">Required Documents</p>

<x-mail::checklist :items="$checklistItems" />
@endif

@if($clientRequest->instructions)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 24px 0 8px 0;">Instructions</p>
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">{{ $clientRequest->instructions }}</p>
@endif

<x-mail::action-card url="{{ $magicLink }}" label="Submit Documents" :deadline="$dueDate" :urgency="!is_null($dueDate)" />

<p style="font-size: 13px; line-height: 1.7; color: #71717a; margin: 0;">
    This secure link expires in seven days. If you need more time or have questions, reply to this email or contact our office directly.
</p>

<x-mail::contact-footer />
</x-mail::message>
