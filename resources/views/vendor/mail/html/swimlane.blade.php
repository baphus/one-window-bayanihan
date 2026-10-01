@props(['referrals' => []])

{{-- Email-safe per-referral progress strip. Nested tables with bgcolor cells
     and inline styles only — no flexbox, grid, SVG, or positioned divs.
     Segment colors reuse the status-badge palette (same hex values) keyed by
     the client status label, so mail and portal vocabulary always match. --}}
@if(is_array($referrals) && count($referrals) > 0)
@php
  $swimlanePalette = [
    'Completed' => ['bg' => '#dcfce7', 'text' => '#166534'],
    'Unable to assist' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
    'In process' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    'Needs documents' => ['bg' => '#fef9c3', 'text' => '#854d0e'],
    'Awaiting receipt' => ['bg' => '#e0e7ff', 'text' => '#3730a3'],
  ];
  $swimlaneFallback = ['bg' => '#f4f4f5', 'text' => '#3f3f46'];
  $swimlaneNow = time();
@endphp
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
@foreach($referrals as $lane)
@php
  $laneSegments = is_array($lane['segments'] ?? null) ? $lane['segments'] : [];
  $laneMin = null;
  $laneMax = null;
  foreach ($laneSegments as $seg) {
    $segStart = isset($seg['start']) && is_string($seg['start']) ? strtotime($seg['start']) : false;
    $segEnd = isset($seg['end']) && is_string($seg['end']) && $seg['end'] !== '' ? strtotime($seg['end']) : $swimlaneNow;
    if ($segStart === false || $segEnd === false) {
      continue;
    }
    $laneMin = $laneMin === null ? $segStart : min($laneMin, $segStart);
    $laneMax = $laneMax === null ? $segEnd : max($laneMax, $segEnd);
  }
  $laneSpan = ($laneMin !== null && $laneMax !== null && $laneMax > $laneMin) ? ($laneMax - $laneMin) : 0;
  $laneWidths = [];
  $laneCount = count($laneSegments);
  foreach ($laneSegments as $laneIndex => $seg) {
    if ($laneSpan <= 0) {
      $laneWidths[$laneIndex] = round(100 / max($laneCount, 1), 1);
      continue;
    }
    $segStart = isset($seg['start']) && is_string($seg['start']) ? strtotime($seg['start']) : false;
    $segEnd = isset($seg['end']) && is_string($seg['end']) && $seg['end'] !== '' ? strtotime($seg['end']) : $swimlaneNow;
    if ($segStart === false || $segEnd === false) {
      $laneWidths[$laneIndex] = 0;
      continue;
    }
    $laneWidths[$laneIndex] = round(max($segEnd - $segStart, 0) / $laneSpan * 100, 1);
  }
  if ($laneCount > 0) {
    $laneWidths[$laneCount - 1] = round(100 - array_sum(array_slice($laneWidths, 0, $laneCount - 1)), 1);
  }
@endphp
<tr>
<td style="padding: 0 0 16px 0; vertical-align: top;">
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
<tr>
<td width="38%" style="vertical-align: top; padding: 0 12px 0 0; width: 38%;">
<div style="font-weight: 600; color: #18181b; font-size: 14px; line-height: 1.4;">{{ $lane['agency'] ?? 'Partner office' }}</div>
<div style="font-size: 12px; font-weight: 600; line-height: 1.4; margin-top: 2px; color: {{ ($swimlanePalette[$lane['statusLabel'] ?? ''] ?? $swimlaneFallback)['text'] }};">&#9679; {{ $lane['statusLabel'] ?? '' }}</div>
@if(!empty($lane['service']))
<div style="color: #71717a; font-size: 12px; line-height: 1.4; margin-top: 2px;">{{ $lane['service'] }}</div>
@endif
@if(($lane['milestoneCount'] ?? 0) > 0)
<div style="color: #a1a1aa; font-size: 12px; line-height: 1.4; margin-top: 2px;">{{ $lane['milestoneCount'] }} update{{ ($lane['milestoneCount'] ?? 0) === 1 ? '' : 's' }}</div>
@endif
</td>
<td style="vertical-align: top; padding: 2px 0 0 0;">
@if($laneCount > 0)
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
<tr>
@foreach($laneSegments as $laneIndex => $seg)
@php $segColor = $swimlanePalette[$seg['label'] ?? ''] ?? $swimlaneFallback; @endphp
<td width="{{ $laneWidths[$laneIndex] }}%" bgcolor="{{ $segColor['bg'] }}" title="{{ $seg['label'] ?? '' }}" style="background-color: {{ $segColor['bg'] }}; font-size: 0; line-height: 0; height: 10px; border-radius: 2px; padding: 0;">&nbsp;</td>
@endforeach
</tr>
</table>
<div style="color: #a1a1aa; font-size: 11px; line-height: 1.4; margin-top: 4px;">Sent {{ isset($lane['sentAt']) && is_string($lane['sentAt']) && strtotime($lane['sentAt']) !== false ? date('M d, Y', strtotime($lane['sentAt'])) : '' }}</div>
@else
<div style="color: #71717a; font-size: 13px; line-height: 1.4;">No progress updates yet.</div>
@endif
</td>
</tr>
</table>
</td>
</tr>
@endforeach
</table>
@endif
