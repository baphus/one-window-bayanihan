@php
    $statusLabel = ucwords(strtolower(str_replace('_', ' ', $newStatus)));
@endphp
<span style="display: inline-block; border-radius: 4px; padding: 4px 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1; mso-line-height-rule: exactly;">{{ $statusLabel }}</span>

<p style="color: #a1a1aa; font-size: 13px; margin: 0 0 16px 0;">
    Case {{ $referral->caseFile?->case_number ?? 'N/A' }}
</p>

<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0;">Referral Status Updated</h1>

@php
    $changeRows = [
        ['label' => 'Status Change', 'value' => ucwords(strtolower(str_replace('_', ' ', $oldStatus))) . ' → ' . $statusLabel],
        ['label' => 'Required Services', 'value' => $referral->required_services],
        ['label' => 'Agency', 'value' => $referral->agency?->name ?? 'N/A'],
    ];
@endphp
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
@foreach($changeRows as $row)
    <tr>
        <td style="color: #52525b; font-size: 13px; padding: 4px 8px 4px 0; white-space: nowrap; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['label'] }}</td>
        <td style="color: #18181b; font-size: 15px; font-weight: 600; padding: 4px 0; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['value'] }}</td>
    </tr>
@endforeach
</table>

@if($newStatus === 'PROCESSING')
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 24px 0;">The referral has been accepted and is now being processed.</p>
@elseif($newStatus === 'COMPLETED')
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 24px 0;">The referral has been completed. No further action is needed.</p>
@elseif($newStatus === 'FOR_COMPLIANCE')
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 24px 0;">Additional documents or information are required. Please review the referral details.</p>
@elseif($newStatus === 'REJECTED')
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 24px 0;">The referral was not accepted. @if($referral->decision_comment) Reason: {{ $referral->decision_comment }} @endif</p>
@endif

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e4e4e7; border-radius: 4px; padding: 24px; background-color: #fafafa; margin: 24px 0; width: 100%; border-collapse: separate;">
    <tr>
        <td style="text-align: center; padding: 0;">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; background-color: #005288; color: #ffffff; padding: 12px 28px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 600; line-height: 1; mso-line-height-rule: exactly;">View Referral</a>
        </td>
    </tr>
</table>

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
