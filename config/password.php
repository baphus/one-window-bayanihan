<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Password Validation Rules
    |--------------------------------------------------------------------------
    |
    | This config is the single source of truth for password strength rules.
    | Both AppServiceProvider (for server-side Password::defaults()) and
    | HandleInertiaRequests (for client-side Zod schemas) read from here.
    |
    */

    'min_length' => 8,

    'require_mixed_case' => true,

    'require_numbers' => true,

    'require_symbols' => true,
];
