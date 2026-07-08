<x-mail::message>
# We Value Your Feedback

Dear {{ $invitation->caseFile->client->first_name }} {{ $invitation->caseFile->client->last_name }},

Your referral to **{{ $invitation->agency->name }}** has been completed. The Department of Migrant Workers (DMW) Region VII values your experience and invites you to share your feedback on the service you received.

**Referral Details:**
- **Service:** {{ $invitation->service_name }}
- **Agency:** {{ $invitation->agency->name }}
- **Completed:** {{ $invitation->referral->updated_at->format('F j, Y') }}

<x-mail::button :url="route('feedbacks.submit-page', ['token' => $token])">
Share Your Feedback
</x-mail::button>

This feedback link will expire in 30 days.

Thank you for helping us improve our services.<br>
{{ config('app.name') }} — DMW Region VII
</x-mail::message>
