@props(['items' => [], 'completed' => []])

<table class="checklist" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
  @foreach($items as $index => $item)
    <tr>
      @if(in_array($index, $completed ?? []))
        <td class="checklist-item checklist-done" style="padding: 6px 0; color: #16a34a; text-decoration: line-through; font-size: 15px; line-height: 1.4; vertical-align: top;">
          ✓ {{ $item }}
        </td>
      @else
        <td class="checklist-item" style="padding: 6px 0; color: #18181b; font-size: 15px; line-height: 1.4; vertical-align: top;">
          ☐ {{ $item }}
        </td>
      @endif
    </tr>
  @endforeach
</table>
