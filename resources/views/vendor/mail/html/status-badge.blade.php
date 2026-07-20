@props(['status' => '', 'label' => ''])

@php
  $colors = [
    'processing' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    'completed'  => ['bg' => '#dcfce7', 'text' => '#166534'],
    'rejected'   => ['bg' => '#fee2e2', 'text' => '#991b1b'],
    'pending'    => ['bg' => '#fef9c3', 'text' => '#854d0e'],
    'referred'   => ['bg' => '#e0e7ff', 'text' => '#3730a3'],
  ];
  $c = $colors[$status] ?? ['bg' => '#f4f4f5', 'text' => '#3f3f46'];
@endphp

<span style="display: inline-block; border-radius: 3px; padding: 4px 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.4; background-color: {{ $c['bg'] }}; color: {{ $c['text'] }};">{{ $label }}</span>
