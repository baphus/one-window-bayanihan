@props(['items' => [], 'completed' => []])

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin: 16px 0;">
@foreach($items as $index => $item)
<tr>
@if(in_array($index, $completed ?? []))
<td style="padding: 8px 0; color: #16a34a; text-decoration: line-through; font-size: 15px; line-height: 1.5; vertical-align: top; border-bottom: 1px solid #f4f4f5;">&check; &nbsp;{{ $item }}</td>
@else
<td style="padding: 8px 0; color: #18181b; font-size: 15px; line-height: 1.5; vertical-align: top; border-bottom: 1px solid #f4f4f5;">&ndash; &nbsp;{{ $item }}</td>
@endif
</tr>
@endforeach
</table>
