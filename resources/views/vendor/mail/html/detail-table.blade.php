@props(['rows' => []])

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
@foreach($rows as $row)
<tr>
<td style="color: #71717a; font-size: 13px; padding: 10px 16px 10px 0; white-space: nowrap; vertical-align: top; text-align: left; line-height: 1.5; border-bottom: 1px solid #f4f4f5;">{{ $row['label'] }}</td>
<td style="color: #18181b; font-size: 15px; font-weight: 600; padding: 10px 0; vertical-align: top; text-align: left; line-height: 1.5; border-bottom: 1px solid #f4f4f5;">{{ $row['value'] }}</td>
</tr>
@endforeach
</table>
