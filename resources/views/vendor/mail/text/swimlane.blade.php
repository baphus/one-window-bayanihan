@props(['referrals' => []])
@foreach($referrals as $lane)
- {{ $lane['agency'] ?? 'Partner office' }}: {{ $lane['statusLabel'] ?? '' }}@if(!empty($lane['service'])) ({{ $lane['service'] }})@endif{{ ($lane['milestoneCount'] ?? 0) > 0 ? ', '.($lane['milestoneCount']).' update'.(($lane['milestoneCount'] ?? 0) === 1 ? '' : 's') : '' }}
@foreach(($lane['segments'] ?? []) as $seg)
  * {{ $seg['label'] ?? '' }}: {{ isset($seg['start']) && is_string($seg['start']) ? $seg['start'] : '' }} → {{ isset($seg['end']) && is_string($seg['end']) && $seg['end'] !== '' ? $seg['end'] : 'ongoing' }}
@endforeach
@endforeach
