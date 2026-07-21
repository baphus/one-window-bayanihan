<x-mail::message>
@php
$statusLabel = ucwords(strtolower(str_replace('_', ' ', $newStatus)));
$oldLabel = ucwords(strtolower(str_replace('_', ' ', $oldStatus)));
@endphp

<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $case->case_number }}</p>

# Case Status Updated

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
The status of this case has changed from <strong>{{ $oldLabel }}</strong> to <strong>{{ $statusLabel }}</strong>.
</p>

<x-mail::detail-table :rows="[
['label' => 'Client', 'value' => ($case->client?->first_name ?? '') . ' ' . ($case->client?->last_name ?? '')],
['label' => 'Category', 'value' => $case->category?->name ?? 'N/A'],
['label' => 'Status', 'value' => $oldLabel . ' → ' . $statusLabel],
]" />

<x-mail::action-card url="{{ $url }}" label="View Case" />

<x-mail::contact-footer />
</x-mail::message>
