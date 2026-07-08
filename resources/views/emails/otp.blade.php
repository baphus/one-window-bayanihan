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
    ">
        {{ $otp }}
    </div>
</div>

<div style="margin: 24px 0; text-align: center; color: #6b7280; font-size: 14px;">
    <strong>This verification code will expire in 5 minutes.</strong>
</div>

---

### For your security

- Never share this code with anyone.
- DMW personnel will **never** ask for your verification code.
@switch($purpose)
    @case('login')
- If you did not request this sign-in attempt, you may safely ignore this email.
        @break
    @case('track')
- If you did not request this verification, you may safely ignore this email.
        @break
    @case('email_change')
- If you did not request this email change, you may safely ignore this email.
        @break
    @default
- If you did not request this, you may safely ignore this email.
@endswitch

<br>

Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
</x-mail::message>