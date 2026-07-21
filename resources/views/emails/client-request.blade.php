<x-mail::message>
<x-mail::status-badge
    status="{{ $clientRequest->type === \App\Models\ReferralClientRequest::TYPE_DOCUMENT_REQUEST ? 'pending' : 'processing' }}"
    label="{{ $requestTypeLabel }}"
/>

<p style="color: #a1a1aa; font-size: 13px; margin: 0 0 16px 0;">
    Case {{ $caseNumber }}
</p>

<p style="font-size: 16px; line-height: 1.6; color: #18181b; margin: 0 0 16px 0;">
    Hi {{ $clientName }},
</p>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">
    <strong>{{ $agencyName }}</strong> needs the following from you regarding your case:
</p>

@if($clientRequest->type === \App\Models\ReferralClientRequest::TYPE_DOCUMENT_REQUEST && count($checklistItems) > 0)
<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 0 0 12px 0;">Required Documents</h3>
<x-mail::checklist :items="$checklistItems" />
@endif

@if($clientRequest->instructions)
<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 24px 0 12px 0;">Instructions</h3>
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">{{ $clientRequest->instructions }}</p>
@endif

<x-mail::action-card
    url="{{ $magicLink }}"
    label="Submit Documents"
    :deadline="$dueDate"
    :urgency="!is_null($dueDate)"
/>

<p style="font-size: 13px; line-height: 1.5; color: #52525b; margin: 16px 0 0 0;">
    Need more time? Reply to this email or contact the agency directly. This secure link expires in seven days.
</p>

<x-mail::contact-footer />
</x-mail::message>
