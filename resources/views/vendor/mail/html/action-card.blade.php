@props(['url' => '#', 'label' => '', 'deadline' => null, 'urgency' => false])

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 32px 0; width: 100%; border-collapse: collapse;">
@if($deadline)
<tr>
<td style="text-align: center; padding-bottom: 12px;">
<span style="font-size: 13px; font-weight: 600; color: {{ $urgency ? '#dc2626' : '#52525b' }}; line-height: 1.4;">Due by {{ $deadline }}</span>
</td>
</tr>
@endif
<tr>
<td style="text-align: center; padding: 0;">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; background-color: {{ $urgency ? '#dc2626' : '#005288' }}; color: #ffffff; padding: 14px 40px; border-radius: 6px; text-decoration: none; font-size: 15px; font-weight: 600; line-height: 1.2; mso-line-height-rule: exactly;">{{ $label }}</a>
</td>
</tr>
<tr>
<td style="text-align: center; padding-top: 10px;">
<span style="font-size: 12px; color: #a1a1aa;">Or copy this link: <a href="{{ $url }}" style="color: #005288; word-break: break-all; text-decoration: underline;">{{ Str::limit($url, 60) }}</a></span>
</td>
</tr>
</table>
