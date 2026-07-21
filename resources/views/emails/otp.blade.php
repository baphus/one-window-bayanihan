<x-mail::message>
# Verify Your Identity

@switch($purpose)
    @case('login')
We received a request to sign in to your **{{ config('app.name') }}** account.

To continue, enter the verification code below:
        @break
    @case('track')
A verification is required to view the progress of your tracked case.

To continue, enter the verification code below:
        @break
    @case('email_change')
We received a request to change the email address associated with your **{{ config('app.name') }}** account.

To continue, enter the verification code below:
        @break
    @default
We received a request to verify your identity for **{{ config('app.name') }}**.

To continue, enter the verification code below:
@endswitch

<div style="margin: 36px 0; text-align: center;">
    <div style="
        display: inline-block;
        font-size: 36px;
        font-weight: 700;
        letter-spacing: 12px;
        color: #005288;
        font-family: Arial, Helvetica, sans-serif;
        background-color: #e8f4fd;
        padding: 16px 28px;
        border-radius: 4px;
        border: 1px solid #005288;
    ">
        {{ $otp }}
    </div>
</div>

<p style="font-size: 14px; text-align: center; color: #52525b; margin: 24px 0;">
    <strong>This verification code will expire in 5 minutes.</strong>
</p>

<x-mail::security-notice />

<p style="font-size: 14px; line-height: 1.6; color: #52525b; margin: 24px 0 0 0;">
    @switch($purpose)
        @case('login')
    If you did not request this sign-in attempt, you may safely ignore this email. Your account remains secure.
            @break
        @case('track')
    If you did not request this verification, you may safely ignore this email.
            @break
        @case('email_change')
    If you did not request this email change, you may safely ignore this email. Your email address has not been altered.
            @break
        @default
    If you did not request this, you may safely ignore this email.
    @endswitch
</p>

<x-mail::contact-footer />
</x-mail::message>
