<x-mail::message>
# You've been invited!

You have been invited to join **One Window Bayanihan** as a **{{ $role === 'ADMIN' ? 'System Admin' : ($role === 'CASE_MANAGER' ? 'Case Manager' : 'Agency Focal') }}**@if($agencyName) for **{{ $agencyName }}**@endif.

Click the button below to complete your registration.

<x-mail::button :url="$inviteUrl">
Complete Registration
</x-mail::button>

This invitation expires on **{{ $expiresAt }}**.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
