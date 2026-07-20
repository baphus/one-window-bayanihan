<x-mail::message>
# Email Address Changed

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 20px 0;">
    Dear {{ $userName }},
</p>

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    The email address associated with your {{ config('app.name') }} account was changed on {{ now()->format('M d, Y') }}.
</p>

<x-mail::detail-table :rows="[
    ['label' => 'Previous', 'value' => substr($oldEmail, 0, 2) . '***' . strchr($oldEmail, '@')],
    ['label' => 'New', 'value' => substr($newEmail, 0, 2) . '***' . strchr($newEmail, '@')],
]" />

<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 28px 0 20px 0;">
    If you made this change, no further action is needed.
</p>

<p style="font-size: 14px; font-weight: 600; color: #dc2626; margin: 0 0 8px 0;">If you did not authorize this change:</p>

<x-mail::checklist
    :items="[
        'Contact your system administrator immediately',
        'Call DMW Region VII at (032) 231-1811',
        'Change your password',
        'Review your recent account activity',
    ]"
/>

<x-mail::action-card url="#" label="Secure My Account" :urgency="true" />

<x-mail::security-notice />
<x-mail::contact-footer />
</x-mail::message>
