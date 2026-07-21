@props(['events' => collect()])

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
@foreach($events as $event)
<tr>
<td width="36" style="vertical-align: top; padding: 0; width: 36px;">
<table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
<tr>
<td style="text-align: center; font-size: 14px; line-height: 1; padding: 0;">
@if($loop->last)
<span style="display: inline-block; border: 2px solid #005288; background-color: #eff6ff; color: #005288; width: 22px; height: 22px; border-radius: 50%; text-align: center; font-size: 11px; font-weight: 700; line-height: 18px;">&#9679;</span>
@else
<span style="display: inline-block; background-color: #16a34a; color: #ffffff; width: 22px; height: 22px; border-radius: 50%; text-align: center; font-size: 11px; font-weight: 700; line-height: 22px;">&#10003;</span>
@endif
</td>
</tr>
@unless($loop->last)
<tr>
<td style="text-align: center; padding: 0;"><div style="width: 2px; height: 28px; background-color: #e4e4e7; margin: 0 auto;"></div></td>
</tr>
@endunless
</table>
</td>
<td style="padding: 0 0 20px 12px; vertical-align: top;">
<div style="font-weight: {{ $loop->last ? '700' : '600' }}; color: {{ $loop->last ? '#005288' : '#18181b' }}; font-size: 14px; line-height: 1.4;">{{ $event->title }}</div>
@if($event->description)
<div style="color: #71717a; font-size: 13px; line-height: 1.4; margin-top: 2px;">{{ $event->description }}</div>
@endif
<div style="color: #a1a1aa; font-size: 12px; line-height: 1.4; margin-top: 2px;">{{ $event->occurred_at?->format('M d, Y g:i A') ?? '' }}</div>
</td>
</tr>
@endforeach
</table>
