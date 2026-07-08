<x-mail::message>
<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0; line-height: 1.3;">New Referral Assigned</h1>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 12px 0;">A new referral has been assigned to your agency for processing.</p>

<table cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px 0;">
<tr>
<td style="font-size: 15px; line-height: 1.6; color: #52525b; padding: 0;">
<strong style="color: #18181b;">Required Services:</strong> {{ $referral->required_services }}
</td>
</tr>
<tr>
<td style="font-size: 15px; line-height: 1.6; color: #52525b; padding: 2px 0 0 0;">
<strong style="color: #18181b;">Status:</strong> {{ $referral->status }}
</td>
</tr>
</table>

<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0;">
<tr>
<td align="center">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" target="_blank" rel="noopener" style="background-color: #005288; border-top: 12px solid #005288; border-bottom: 12px solid #005288; border-left: 28px solid #005288; border-right: 28px solid #005288; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none; -webkit-text-size-adjust: none; font-weight: bold;">View Referral</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 4px 0;">Please review and process this referral at your earliest convenience.</p>

<hr style="border: none; border-top: 1px solid #e4e4e7; margin: 24px 0;">

<p style="font-size: 14px; line-height: 1.5; color: #52525b; margin: 0 0 2px 0;">Regards,</p>
<p style="font-size: 14px; line-height: 1.5; color: #18181b; margin: 0; font-weight: 600;">Department of Migrant Workers – Region VII</p>
<p style="font-size: 14px; line-height: 1.5; color: #52525b; margin: 0;">{{ config('app.name') }}</p>
</x-mail::message>
