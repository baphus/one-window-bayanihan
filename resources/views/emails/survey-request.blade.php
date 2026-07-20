<x-mail::message>
# We'd Love Your Feedback

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 16px 0;">
    Dear {{ $invitation->client_name }},
</p>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 16px 0;">
    Thank you for using the services of <strong>{{ $invitation->agency->name ?? 'our agency' }}</strong>. We would appreciate your feedback on the <strong>{{ $invitation->service_name }}</strong> service you received.
</p>

@if($caseNumber)
<p style="font-size: 13px; color: #a1a1aa; margin: 0 0 24px 0;">Case reference: {{ $caseNumber }}</p>
@endif

<x-mail::action-card
    url="{{ $survey_url }}"
    label="Complete Survey"
/>

<p style="font-size: 13px; line-height: 1.5; color: #52525b; margin: 16px 0 0 0;">
    This link will expire in 30 days. If you did not request this service, please ignore this email.
</p>

<x-mail::contact-footer />
</x-mail::message>
