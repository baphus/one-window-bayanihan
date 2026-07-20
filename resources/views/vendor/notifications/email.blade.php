<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $bgColor = match ($level) {
        'success' => '#16a34a',
        'error' => '#dc2626',
        default => '#0b5384',
    };
?>
<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="background-color: {{ $bgColor }}; border-top: 12px solid {{ $bgColor }}; border-bottom: 12px solid {{ $bgColor }}; border-left: 28px solid {{ $bgColor }}; border-right: 28px solid {{ $bgColor }}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; text-decoration: none; -webkit-text-size-adjust: none; font-weight: bold;">{{ $actionText }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Regards,<br>
**Department of Migrant Workers – Region VII**<br>
**{{ config('app.name') }}**
@endif

</x-mail::message>
