<x-mail::message>
# System Access Invitation

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    You have been invited to join the One Window Bayanihan case management system
    @if($agencyName)
    as a member of <strong>{{ $agencyName }}</strong>
    @endif.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'Role', 'value' => ucwords(strtolower(str_replace('_', ' ', $role)))],
    ['label' => 'Agency', 'value' => $agencyName ?? 'DMW Region VII'],
    ['label' => 'Expires', 'value' => $expiresAt],
]" />

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 28px 0 24px 0;">
    Use the link below to create your account. You will be asked to set a password and configure multi-factor authentication.
</p>

<x-mail::action-card url="{{ $inviteUrl }}" label="Accept Invitation" :deadline="$expiresAt" :urgency="true" />

<p style="font-size: 13px; line-height: 1.7; color: #71717a; margin: 0;">
    If you were not expecting this invitation, you may disregard this email.
</p>

<x-mail::contact-footer />
</x-mail::message>
