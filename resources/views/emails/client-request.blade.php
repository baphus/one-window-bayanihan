<x-mail::message>
# Action needed

**{{ $agencyName }}** has a request that needs your attention.

@if ($dueDate)
Please respond by **{{ $dueDate }}**.
@endif

<x-mail::button :url="$magicLink">
View request
</x-mail::button>

This secure link expires in seven days. If you were not expecting this request, you can ignore this email.

{{ config('app.name') }}
</x-mail::message>
