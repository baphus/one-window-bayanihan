<x-mail::message>
# Survey Request

Dear {{ $invitation->client_name }},

Thank you for using the services of **{{ $invitation->agency->name }}**. We would appreciate your feedback on the **{{ $invitation->service_name }}** service you received.

<x-mail::button :url="$survey_url">
Complete Survey
</x-mail::button>

This link will expire in 30 days.

If you did not request this service, please ignore this email.

{{ config('app.name') }}
</x-mail::message>
