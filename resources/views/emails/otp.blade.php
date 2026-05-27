<x-mail::message>
# Verification Code

Your one-time verification code is:

<h1 style="text-align: center; font-size: 2rem; letter-spacing: 0.5rem; margin: 2rem 0; color: #1a73e8;">
    {{ $otp }}
</h1>

This code will expire in **5 minutes**.

If you did not request this code, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
