<?php

return [
    'site_key' => env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    'enabled' => env('TURNSTILE_ENABLED', false),
    // Reuse window for routes on the `turnstile.session` alias only (seconds).
    // 0 = verify every request everywhere; a positive value lets one solved
    // challenge cover that many seconds of chatbot messages and nothing else.
    'session_ttl' => (int) env('TURNSTILE_SESSION_TTL', 0),
];
