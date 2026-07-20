@props(['rows' => []])

<table class="detail-table" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
  @foreach($rows as $row)
    <tr>
      <td class="detail-label" style="color: #52525b; font-size: 13px; padding: 4px 8px 4px 0; white-space: nowrap; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['label'] }}</td>
      <td class="detail-value" style="color: #18181b; font-size: 15px; font-weight: 600; padding: 4px 0; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['value'] }}</td>
    </tr>
  @endforeach
</table>
