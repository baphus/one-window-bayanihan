<x-mail::message>
<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $case->case_number }}</p>

# Case Details Modified

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
{{ $updatedBy }} made the following changes:
</p>

@if(count($changes) > 0)
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin: 0 0 28px 0;">
<tr>
<td style="background-color: #f8fafc; color: #71717a; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 8px 12px; border-bottom: 1px solid #e4e4e7;">Field</td>
<td style="background-color: #f8fafc; color: #71717a; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 8px 12px; border-bottom: 1px solid #e4e4e7;">Previous</td>
<td style="background-color: #f8fafc; color: #71717a; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 8px 12px; border-bottom: 1px solid #e4e4e7;">New</td>
</tr>
@foreach($changes as $field => $change)
<tr>
<td style="color: #18181b; font-size: 14px; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #f4f4f5;">{{ ucwords(str_replace('_', ' ', $field)) }}</td>
<td style="color: #a1a1aa; font-size: 14px; padding: 10px 12px; border-bottom: 1px solid #f4f4f5; text-decoration: line-through;">{{ $change['old'] ?? '—' }}</td>
<td style="color: #005288; font-size: 14px; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #f4f4f5;">{{ $change['new'] ?? '—' }}</td>
</tr>
@endforeach
</table>
@endif

<x-mail::action-card url="{{ $url }}" label="View Case" />

<x-mail::contact-footer />
</x-mail::message>
