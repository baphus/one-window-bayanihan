<?php

return [
    'site_key' => env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    'enabled' => env('TURNSTILE_ENABLED', false),
    // How long a passed check stays valid for the session alias (seconds).
    // 0 = verify every request.
    'session_ttl' => (int) env('TURNSTILE_SESSION_TTL', 0),
];
