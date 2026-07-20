<x-mail::message>
# Survey Request

Dear {{ $invitation->client_name }},

Thank you for using the services of **{{ $invitation->agency->name }}**. We would appreciate your feedback on the **{{ $invitation->service_name }}** service you received.

<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $survey_url }}" target="_blank" rel="noopener" style="background-color: #0b5384; border-top: 12px solid #0b5384; border-bottom: 12px solid #0b5384; border-left: 28px solid #0b5384; border-right: 28px solid #0b5384; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none; -webkit-text-size-adjust: none; font-weight: bold;">Complete Survey</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

This link will expire in 30 days.

If you did not request this service, please ignore this email.

{{ config('app.name') }}
</x-mail::message>
