<x-mail::message>
# Email Address Changed

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 16px 0;">
    Dear {{ $userName }},
</p>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 16px 0;">
    Your email address for <strong>{{ config('app.name') }}</strong> has been changed successfully.
</p>

<table class="detail-table" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin: 16px 0 24px 0;">
    <tr>
        <td class="detail-label" style="color: #52525b; font-size: 13px; padding: 4px 8px 4px 0; white-space: nowrap; vertical-align: top;">Previous</td>
        <td class="detail-value" style="color: #18181b; font-size: 15px; font-weight: 600; padding: 4px 0; text-decoration: line-through; color: #a1a1aa;">{{ substr($oldEmail, 0, 2) }}***{{ strchr($oldEmail, '@') }}</td>
    </tr>
    <tr>
        <td class="detail-label" style="color: #52525b; font-size: 13px; padding: 4px 8px 4px 0; white-space: nowrap; vertical-align: top;">New</td>
        <td class="detail-value" style="color: #18181b; font-size: 15px; font-weight: 600; padding: 4px 0; color: #16a34a;">{{ substr($newEmail, 0, 2) }}***{{ strchr($newEmail, '@') }}</td>
    </tr>
</table>

<x-mail::action-card
    url="#"
    label="Secure My Account"
    :urgency="true"
/>

<h3 style="font-size: 16px; font-weight: 700; color: #dc2626; margin: 24px 0 12px 0;">Didn't authorize this change?</h3>

<p style="font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 12px 0;">
    If you did not make this change, your account may be compromised. Take the following steps immediately:
</p>

<x-mail::checklist
    :items="[
        'Contact your system administrator',
        'Call DMW Region VII at (032) 123-4567',
        'Change your password as soon as possible',
        'Review your recent account activity for unauthorized changes',
    ]"
/>

<p style="font-size: 13px; line-height: 1.5; color: #52525b; margin: 16px 0 0 0;">
    Do not ignore this email. Acting quickly helps us protect your account and personal information.
</p>

<x-mail::security-notice />
<x-mail::contact-footer />
</x-mail::message>
