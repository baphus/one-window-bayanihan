<x-mail::message>
# We Value Your Feedback

Dear {{ $caseFile->client->first_name }} {{ $caseFile->client->last_name }},

Your referral to **{{ $agency->name }}** has been completed. We value your feedback on the service you received.

**Referral Details:**
- **Service:** Referral Services
- **Agency:** {{ $agency->name }}
- **Completed:** {{ $referral->updated_at->format('F j, Y') }}

<x-mail::button :url="route('feedbacks.submit-page', ['tracking_token' => $trackingToken])">
Share Your Feedback
</x-mail::button>

This feedback request will expire in 7 days.

If you prefer, you can also provide feedback by logging into your tracking portal.

Thanks,<br>
{{ config('app.name') }} — DMW Region VII
</x-mail::message>
