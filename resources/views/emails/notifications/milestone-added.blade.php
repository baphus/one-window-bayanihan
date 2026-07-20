<x-mail::message>
<p style="color: #a1a1aa; font-size: 12px; margin: 0 0 20px 0;">Case {{ $referral->caseFile?->case_number ?? 'N/A' }}</p>

# New Milestone Added

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
{{ $referral->agency?->name ?? 'The assigned agency' }} recorded a new milestone on this referral.
</p>

<x-mail::detail-table :rows="[
['label' => 'Milestone', 'value' => $milestone->title],
['label' => 'Service', 'value' => $referral->required_services],
['label' => 'Agency', 'value' => $referral->agency?->name ?? 'N/A'],
]" />

@if($milestone->description)
<div style="background-color: #f8fafc; border-left: 3px solid #e4e4e7; padding: 16px 20px; margin: 0 0 28px 0;">
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0;">{{ $milestone->description }}</p>
</div>
@endif

<x-mail::action-card url="{{ $url }}" label="View Referral" />

<x-mail::contact-footer />
</x-mail::message>
