@props(['events' => collect()])
@foreach($events as $event)
  @if($loop->last)► @else ✓ @endif {{ $event->title }}{{ $event->description ? ' - '.$event->description : '' }} ({{ $event->occurred_at?->copy()->setTimezone(config('app.operating_timezone', 'Asia/Manila'))->format('M d, Y h:i A') ?? '' }})
@endforeach