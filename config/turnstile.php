<?php

return [
    'site_key' => env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    'enabled' => env('TURNSTILE_ENABLED', false),
    // How long a passed session-level check stays valid (seconds).
    'session_ttl' => (int) env('TURNSTILE_SESSION_TTL', 1800),
];
