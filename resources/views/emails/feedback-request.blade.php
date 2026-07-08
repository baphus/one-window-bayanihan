<x-mail::message>
# We Value Your Feedback

Dear {{ $caseFile->client->first_name }},

Your referral to **{{ $agency->name }}** has been completed. We would greatly appreciate your feedback on the service you received.

**Referral Details:**
- **Service:** {{ $referral->required_services }}
- **Agency:** {{ $agency->name }}
- **Completed:** {{ $referral->updated_at->format('F j, Y') }}

<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ route('feedbacks.submit-page', ['tracking_token' => $trackingToken]) }}" target="_blank" rel="noopener" style="background-color: #0b5384; border-top: 12px solid #0b5384; border-bottom: 12px solid #0b5384; border-left: 28px solid #0b5384; border-right: 28px solid #0b5384; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none; -webkit-text-size-adjust: none; font-weight: bold;">Share Your Feedback</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

This feedback request will expire in **7 days**.

If you prefer, you can also provide feedback by logging into your tracking portal using the case number or tracking ID provided to you.

---

### Your voice matters

Your feedback helps us improve the quality of services provided to our migrant workers and their families. All responses are kept confidential and used solely for service improvement purposes.

<br>

Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
</x-mail::message>
