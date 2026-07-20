@props(['events' => collect()])

<table class="timeline" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
  @foreach($events as $event)
    <tr>
      <td width="30" style="vertical-align: top; padding: 0; width: 30px;">
        <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
          <tr>
            <td style="text-align: center; font-size: 16px; line-height: 1; padding: 0;">
              @if($loop->last)
                <span class="timeline-circle-current" style="display: inline-block; border: 2px solid #005288; background-color: #e8f4fd; color: #005288; width: 20px; height: 20px; border-radius: 50%; text-align: center; font-size: 11px; font-weight: 700; line-height: 16px; mso-line-height-rule: exactly;">●</span>
              @else
                <span class="timeline-circle-completed" style="display: inline-block; background-color: #16a34a; color: #ffffff; width: 20px; height: 20px; border-radius: 50%; text-align: center; font-size: 11px; font-weight: 600; line-height: 20px; mso-line-height-rule: exactly;">✓</span>
              @endif
            </td>
          </tr>
          @unless($loop->last)
            <tr>
              <td class="timeline-connector" style="text-align: center; width: 1px; height: 30px; background-color: #e4e4e7; margin: 0 auto;"></td>
            </tr>
          @endunless
        </table>
      </td>
      <td style="padding: 0 0 16px 12px; vertical-align: top;">
        <div class="{{ $loop->last ? 'timeline-label-current' : 'timeline-label-completed' }}" style="font-weight: {{ $loop->last ? '700' : '600' }}; color: #18181b; font-size: 14px; line-height: 1.4;">{{ $event->title }}</div>
        @if($event->description)
          <div class="timeline-description" style="color: #52525b; font-size: 13px; line-height: 1.4;">{{ $event->description }}</div>
        @endif
        <div class="timeline-date" style="color: #a1a1aa; font-size: 12px; line-height: 1.4;">{{ $event->occurred_at?->format('M d, Y h:i A') ?? '' }}</div>
      </td>
    </tr>
  @endforeach
</table>
