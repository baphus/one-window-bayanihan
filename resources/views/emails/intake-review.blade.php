<x-mail::message>
# Request Submitted for Review

A request has been received and is being reviewed by a case manager.

**Request Reference:** {{ $case->case_number }}

Please keep your tracker number safe: **{{ $case->tracker_number }}**.

<x-mail::button :url="route('track.index', ['tracker_number' => $case->tracker_number])">
Track Your Request
</x-mail::button>

<x-mail::contact-footer />
</x-mail::message>
