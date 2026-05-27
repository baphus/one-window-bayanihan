<x-mail::message>
# Referral Status Updated

A referral's status has been updated.

**Required Services**: {{ $referral->required_services }}
**Status**: {{ $oldStatus }} → **{{ $newStatus }}**

<x-mail::button :url="$url">
View Referral
</x-mail::button>

Please review the updated referral details.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
