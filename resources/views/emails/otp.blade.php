<x-mail::message>
<div style="text-align: center; margin-bottom: 24px;">
    <img
        src="{{ asset('logo.png') }}"
        alt="{{ config('app.name') }}"
        style="height: 72px;"
    >
</div>

# Verify Your Identity

We received a request to sign in to your **{{ config('app.name') }}** account.

To continue, enter the verification code below:

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
- If you did not request this sign-in attempt, you may safely ignore this email.

<br>

Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
</x-mail::message>