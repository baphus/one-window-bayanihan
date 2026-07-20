@php
    $alertLabel = ucwords($severity) . ' Alert';
@endphp
<span style="display: inline-block; border-radius: 4px; padding: 4px 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1; mso-line-height-rule: exactly;">{{ $alertLabel }}</span>

<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0;">System Alert</h1>

@php
    $detailRows = [
        ['label' => 'Type', 'value' => ucwords(str_replace('_', ' ', $type))],
        ['label' => 'Severity', 'value' => ucwords($severity)],
    ];
@endphp
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
@foreach($detailRows as $row)
    <tr>
        <td style="color: #52525b; font-size: 13px; padding: 4px 8px 4px 0; white-space: nowrap; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['label'] }}</td>
        <td style="color: #18181b; font-size: 15px; font-weight: 600; padding: 4px 0; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['value'] }}</td>
    </tr>
@endforeach
</table>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 24px 0;">{{ $message }}</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-top: 1px solid #e4e4e7; margin-top: 24px; padding-top: 16px; font-size: 13px; color: #52525b; width: 100%; border-collapse: collapse;">
    <tr>
        <td style="text-align: center; padding-top: 16px;">
            <strong style="color: #18181b;">Department of Migrant Workers – Region VII</strong><br>
            {{ config('app.name') }}<br>
            📞 (032) 123-4567 · ✉️ dmwregion7@bayanihan.gov.ph<br>
            🕐 Monday – Friday, 8:00 AM – 5:00 PM
        </td>
    </tr>
</table>
