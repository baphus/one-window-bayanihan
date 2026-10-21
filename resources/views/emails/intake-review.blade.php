<x-mail::message>
# Case Submitted for Review

A case has been received and is being reviewed by a case manager.

**Case Number:** {{ $case->case_number }}

Please keep your tracker number safe: **{{ $case->tracker_number }}**.

<x-mail::button :url="route('track.index', ['tracker_number' => $case->tracker_number])">
Track Your Case
</x-mail::button>

<x-mail::contact-footer />
</x-mail::message>
