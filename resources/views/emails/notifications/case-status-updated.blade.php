<x-mail::message>
@php
$statusLabel = ucwords(strtolower(str_replace('_', ' ', $newStatus)));
$oldLabel = ucwords(strtolower(str_replace('_', ' ', $oldStatus)));
$clientName = trim(($case->first_name ?? '').' '.($case->last_name ?? ''));
$categories = $case->referrals
    ->pluck('category.name')
    ->filter()
    ->unique()
    ->implode(', ') ?: 'N/A';
@endphp

<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $case->case_number }}</p>

# Case Status Updated

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
The status of this case has changed from <strong>{{ $oldLabel }}</strong> to <strong>{{ $statusLabel }}</strong>.
</p>

<x-mail::detail-table :rows="[
['label' => 'Client', 'value' => $clientName !== '' ? $clientName : 'N/A'],
['label' => 'Categories', 'value' => $categories],
['label' => 'Status', 'value' => $oldLabel . ' → ' . $statusLabel],
['label' => 'Case Manager', 'value' => $case->user?->name ?? 'Unassigned'],
['label' => 'Updated By', 'value' => ($actorName ?? '') !== '' ? $actorName : 'System'],
['label' => 'Updated At', 'value' => ($case->updated_at ?? now())->format('M d, Y h:i A')],
]" />

@if($case->summary)
<p style="font-size: 14px; font-weight: 600; color: #18181b; margin: 28px 0 8px 0;">Case Summary</p>

<div style="background-color: #f8fafc; border-left: 3px solid #e4e4e7; padding: 16px 20px; margin: 0 0 28px 0;">
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0;">{{ $case->summary }}</p>
</div>
@endif

<x-mail::action-card url="{{ $url }}" label="View Case" />

<x-mail::contact-footer />
</x-mail::message>
