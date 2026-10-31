<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleMapsLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $url = filter_var(trim((string) $value), FILTER_VALIDATE_URL);
        $parts = $url ? parse_url($url) : false;
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        $allowedGoogleHost = in_array($host, [
            'google.com', 'www.google.com', 'maps.google.com',
            'google.com.ph', 'www.google.com.ph', 'maps.google.com.ph',
        ], true) && ($path === '/maps' || str_starts_with($path, '/maps/'));
        $allowedShortHost = ($host === 'maps.app.goo.gl' && $path !== '' && $path !== '/')
            || ($host === 'goo.gl' && str_starts_with($path, '/maps/'));

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || (! $allowedGoogleHost && ! $allowedShortHost)) {
            $fail('The map link must be a valid Google Maps link.');
        }
    }
}
