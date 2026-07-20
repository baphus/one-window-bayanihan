<x-mail::message>
# Verification Code

@switch($purpose)
    @case('login')
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    A sign-in attempt was made on your {{ config('app.name') }} account. Use the code below to complete verification.
</p>
        @break
    @case('track')
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    To view the status of your case, enter the verification code below.
</p>
        @break
    @case('email_change')
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    A request was made to change the email address on your {{ config('app.name') }} account. Enter the code below to confirm.
</p>
        @break
    @default
<p style="font-size: 15px; line-height: 1.7; color: #3f3f46; margin: 0 0 24px 0;">
    Enter the code below to verify your identity.
</p>
@endswitch

<div style="margin: 32px 0; text-align: center;">
    <div style="display: inline-block; font-size: 32px; font-weight: 700; letter-spacing: 10px; color: #005288; font-family: 'Courier New', monospace; background-color: #f4f6f9; padding: 16px 28px; border-radius: 6px; border: 1px solid #e4e4e7;">{{ $otp }}</div>
</div>

<p style="font-size: 13px; text-align: center; color: #71717a; margin: 0 0 32px 0;">
    This code expires in 5 minutes. Do not share it with anyone.
</p>

<x-mail::security-notice />

<p style="font-size: 14px; line-height: 1.7; color: #71717a; margin: 32px 0 0 0;">
    If you did not make this request, no action is needed. Your account has not been accessed.
</p>

<x-mail::contact-footer />
</x-mail::message>
