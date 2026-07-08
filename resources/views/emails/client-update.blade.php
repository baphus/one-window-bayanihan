<x-mail::message>
# Update on Your Case

{{ $message }}

@if($trackingNumber)
**Your tracking number:** {{ $trackingNumber }}
@endif

<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ route('track.index') }}" target="_blank" rel="noopener" style="background-color: #0b5384; border-top: 12px solid #0b5384; border-bottom: 12px solid #0b5384; border-left: 28px solid #0b5384; border-right: 28px solid #0b5384; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none; -webkit-text-size-adjust: none; font-weight: bold;">Track Your Case</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

If you have any questions, please contact the DMW Region VII office.

---

Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
</x-mail::message>
