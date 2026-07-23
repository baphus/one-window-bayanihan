<?php

return [
    'login_challenge_enabled' => (bool) env('MFA_LOGIN_CHALLENGE_ENABLED', false),
    'pending_ttl' => (int) env('MFA_LOGIN_CHALLENGE_TTL', 300),
    'max_attempts' => (int) env('MFA_LOGIN_CHALLENGE_MAX_ATTEMPTS', 5),
    'window' => (int) env('MFA_LOGIN_CHALLENGE_WINDOW', 1),
    'replay_ttl' => (int) env('MFA_LOGIN_CHALLENGE_REPLAY_TTL', 120),
    'enrollment_enforcement_enabled' => (bool) env('MFA_ENROLLMENT_ENFORCEMENT_ENABLED', true),
];
