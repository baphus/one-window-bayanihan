<x-mail::message>
<x-mail::status-badge status="pending" label="Invitation" />

<h1 style="font-size: 20px; font-weight: 800; color: #18181b; margin: 0 0 16px 0;">You're Invited to One Window Bayanihan</h1>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 24px 0;">
    You have been invited to join the One Window Bayanihan case management system
    @if($agencyName) as a member of <strong>{{ $agencyName }}</strong> @endif
    with the role of <strong>{{ ucwords(strtolower(str_replace('_', ' ', $role))) }}</strong>.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'Role', 'value' => ucwords(strtolower(str_replace('_', ' ', $role)))],
    ['label' => 'Agency', 'value' => $agencyName ?? 'DMW Region VII'],
    ['label' => 'Expires', 'value' => $expiresAt],
]" />

<x-mail::action-card url="{{ $inviteUrl }}" label="Accept Invitation" :urgency="true" />

<p style="font-size: 13px; line-height: 1.5; color: #52525b; margin: 16px 0 0 0;">
    This invitation link will expire on {{ $expiresAt }}. If you did not expect this invitation, you can safely ignore this email.
</p>

<x-mail::contact-footer />
</x-mail::message>
