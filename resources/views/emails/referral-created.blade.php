<x-mail::message>
# New Referral Assigned

A new referral has been assigned to your agency.

**Required Services**: {{ $referral->required_services }}
**Status**: {{ $referral->status }}

<x-mail::button :url="$url">
View Referral
</x-mail::button>

Please review and process this referral at your earliest convenience.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
