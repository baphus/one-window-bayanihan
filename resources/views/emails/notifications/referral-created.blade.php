<span style="display: inline-block; border-radius: 4px; padding: 4px 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; line-height: 1; mso-line-height-rule: exactly;">Action Required</span>

<p style="color: #a1a1aa; font-size: 13px; margin: 0 0 16px 0;">
    Case {{ $referral->caseFile?->case_number ?? 'N/A' }}
</p>

<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0;">New Referral Assigned</h1>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">
    A new referral has been assigned to your agency for processing.
</p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
@php
    $detailRows = [
        ['label' => 'Client', 'value' => ($referral->caseFile?->client?->first_name ?? '') . ' ' . ($referral->caseFile?->client?->last_name ?? '')],
        ['label' => 'Issue', 'value' => $referral->caseFile?->caseIssue?->name ?? 'N/A'],
        ['label' => 'From', 'value' => $referral->caseFile?->user?->name ?? 'DMW Region VII'],
        ['label' => 'Referred', 'value' => $referral->created_at->format('M d, Y')],
        ['label' => 'Response Due', 'value' => $referral->created_at->addDays(5)->format('M d, Y')],
    ];
@endphp
@foreach($detailRows as $row)
    <tr>
        <td style="color: #52525b; font-size: 13px; padding: 4px 8px 4px 0; white-space: nowrap; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['label'] }}</td>
        <td style="color: #18181b; font-size: 15px; font-weight: 600; padding: 4px 0; vertical-align: top; text-align: left; line-height: 1.4;">{{ $row['value'] }}</td>
    </tr>
@endforeach
</table>

<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 24px 0 12px 0;">Required Services</h3>
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">{{ $referral->required_services }}</p>

@if($referral->requirements && count($referral->requirements) > 0)
<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 0 0 12px 0;">Required Documents</h3>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
@foreach($referral->requirements as $item)
    <tr>
        <td style="padding: 6px 0; color: #18181b; font-size: 15px; line-height: 1.4; vertical-align: top;">
            ☐ {{ $item }}
        </td>
    </tr>
@endforeach
</table>
@endif

@if($referral->caseFile?->summary)
<h3 style="font-size: 16px; font-weight: 700; color: #18181b; margin: 24px 0 12px 0;">Case Summary</h3>
<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">{{ $referral->caseFile->summary }}</p>
@endif

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e4e4e7; border-radius: 4px; padding: 24px; background-color: #fafafa; margin: 24px 0; width: 100%; border-collapse: separate;">
    <tr>
        <td style="text-align: center; padding: 0;">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; background-color: #005288; color: #ffffff; padding: 12px 28px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 600; line-height: 1; mso-line-height-rule: exactly;">Review &amp; Accept Referral</a>
        </td>
    </tr>
</table>

<p style="font-size: 13px; line-height: 1.5; color: #52525b; margin: 16px 0 0 0;">
    If your agency cannot provide these services, please contact the case manager to discuss.
</p>

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
