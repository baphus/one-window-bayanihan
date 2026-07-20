@props(['url' => '#', 'label' => '', 'deadline' => null, 'urgency' => false])

<table class="action-card{{ $urgency ? ' action-card-urgent' : '' }}" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid #e4e4e7; border-radius: 4px; padding: 24px; background-color: #fafafa; margin: 24px 0; width: 100%; border-collapse: separate;{{ $urgency ? ' border-left: 4px solid #dc2626;' : '' }}">
  <tr>
    <td style="text-align: center; padding: 0;">
      @if($deadline)
        <div style="color: #dc2626; font-size: 13px; font-weight: 600; margin-bottom: 12px; line-height: 1.4;">⏰ Due by {{ $deadline }}</div>
      @endif
      <a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; background-color: #005288; color: #ffffff; padding: 12px 28px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 600; line-height: 1; mso-line-height-rule: exactly;">{{ $label }}</a>
    </td>
  </tr>
</table>
