<x-mail::message>
# Email Address Changed

Dear {{ $userName }},

Your email address for {{ config('app.name') }} has been changed successfully.

New email: {{ substr($newEmail, 0, 2) }}***{{ strchr($newEmail, '@') }}

If you did not authorize this change, please contact your system administrator immediately.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
