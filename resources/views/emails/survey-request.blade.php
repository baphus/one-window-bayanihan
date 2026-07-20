<x-mail::message>
# Service Feedback Request

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $invitation->client_name }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Thank you for using the {{ $invitation->service_name }} services provided by {{ $invitation->agency->name ?? 'our partner agency' }}. We would appreciate a few minutes of your time to share your experience.
</p>

@if($caseNumber)
<p style="font-size: 13px; color: #a1a1aa; margin: 0 0 24px 0;">Case reference: {{ $caseNumber }}</p>
@endif

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 28px 0;">
    Your responses are confidential and will be used to improve our services. The survey takes approximately 3 to 5 minutes.
</p>

<x-mail::action-card url="{{ $survey_url }}" label="Complete Survey" />

<p style="font-size: 13px; line-height: 1.7; color: #71717a; margin: 0;">
    This link is valid for 30 days. Participation is voluntary and will not affect your case in any way.
</p>

<x-mail::contact-footer />
</x-mail::message>
