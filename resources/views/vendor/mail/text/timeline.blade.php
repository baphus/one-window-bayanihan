@props(['events' => collect()])
@foreach($events as $event)
  @if($loop->last)► @else ✓ @endif {{ $event->title }}{{ $event->description ? ' - '.$event->description : '' }} ({{ $event->occurred_at?->format('M d, Y h:i A') ?? '' }})
@endforeach