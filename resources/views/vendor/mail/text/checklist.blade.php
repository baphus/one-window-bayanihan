@props(['items' => [], 'completed' => []])
@foreach($items as $index => $item)
  {{ in_array($index, $completed ?? []) ? '[✓]' : '[☐]' }} {{ $item }}
@endforeach