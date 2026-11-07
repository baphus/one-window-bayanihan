<?php

return [
    'pending_ttl' => (int) env('MFA_LOGIN_CHALLENGE_TTL', 300),
    'max_attempts' => (int) env('MFA_LOGIN_CHALLENGE_MAX_ATTEMPTS', 5),
    'window' => (int) env('MFA_LOGIN_CHALLENGE_WINDOW', 1),

    // A login challenge or dedicated confirmation opens this window for
    // high-impact actions. Activity never extends it.
    'fresh_confirmation_ttl' => (int) env('MFA_FRESH_CONFIRMATION_TTL', 300),
];
