<x-mail::message>
@php
$alertLabel = ucwords($severity);
$borderColor = match($severity) {
'critical' => '#dc2626',
'warning' => '#d97706',
default => '#005288',
};
@endphp

# System Alert &mdash; {{ $alertLabel }}

<x-mail::detail-table :rows="[
['label' => 'Type', 'value' => ucwords(str_replace('_', ' ', $type))],
['label' => 'Severity', 'value' => $alertLabel],
['label' => 'Time', 'value' => now()->format('M d, Y g:i A')],
]" />

<div style="background-color: #f8fafc; border-left: 3px solid {{ $borderColor }}; padding: 16px 20px; margin: 24px 0;">
<p style="font-size: 15px; line-height: 1.7; color: #18181b; margin: 0;">{{ $message }}</p>
</div>

<x-mail::contact-footer />
</x-mail::message>
