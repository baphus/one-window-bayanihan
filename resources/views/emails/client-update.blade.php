<x-mail::message>
# Update on Your Case

{{ $message }}

<x-mail::button :url="route('track.index')">
Track Your Case
</x-mail::button>

Your tracking number is: **{{ $trackingNumber }}**

If you have any questions, please contact the DMW Region VII office.

Thanks,<br>
{{ config('app.name') }} — DMW Region VII
</x-mail::message>
