@props(['url' => '#', 'label' => '', 'deadline' => null, 'urgency' => false]){{ $deadline ? 'Due by: '.$deadline : '' }}
{{ $label }}: {{ $url }}