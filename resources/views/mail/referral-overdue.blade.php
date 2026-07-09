<x-mail::message>
# Overdue Referral Notice

This referral has been pending for over **{{ $overdueDays }} days** without completion.

**Case Number:** {{ $referral->caseFile?->case_number ?? 'N/A' }}<br>
**Client:** {{ $referral->caseFile?->client?->first_name ?? '' }} {{ $referral->caseFile?->client?->last_name ?? 'N/A' }}<br>
**Agency:** {{ $referral->agency?->name ?? 'N/A' }}<br>
**Service Required:** {{ $referral->required_services }}<br>
**Created:** {{ $referral->created_at->format('M d, Y') }}<br>
**Days Overdue:** {{ $referral->created_at->diffInDays(now()) }}

<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ route('referrals.show', $referral) }}" target="_blank" rel="noopener" style="background-color: #0b5384; border-top: 12px solid #0b5384; border-bottom: 12px solid #0b5384; border-left: 28px solid #0b5384; border-right: 28px solid #0b5384; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none; -webkit-text-size-adjust: none; font-weight: bold;">View Referral</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

Please take the necessary action to process or close this referral at your earliest convenience.

---

Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
</x-mail::message>
