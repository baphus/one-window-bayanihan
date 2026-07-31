<?php

return [
    'pending_ttl' => (int) env('MFA_LOGIN_CHALLENGE_TTL', 300),
    'max_attempts' => (int) env('MFA_LOGIN_CHALLENGE_MAX_ATTEMPTS', 5),
    'window' => (int) env('MFA_LOGIN_CHALLENGE_WINDOW', 1),
    'replay_ttl' => (int) env('MFA_LOGIN_CHALLENGE_REPLAY_TTL', 120),
    'enrollment_enforcement_enabled' => (bool) env('MFA_ENROLLMENT_ENFORCEMENT_ENABLED', true),

    /*
     * Roles required to enrol in MFA before they can use the application.
     *
     * This was previously hardcoded to ADMIN only, which left case managers and
     * agency focals — who read and write OFW personal data including contact
     * details, date of birth, employment history and case narratives — able to
     * operate with a password alone. ISO 27001 A.5.17 and A.8.5 expect
     * authentication strength to match the sensitivity of the data reached.
     *
     * Comma-separated env override, e.g. MFA_ENROLLMENT_ENFORCED_ROLES=ADMIN
     * to restore the previous behaviour.
     */
    'enrollment_enforced_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MFA_ENROLLMENT_ENFORCED_ROLES', 'ADMIN,CASE_MANAGER,AGENCY'))
    ))),
];
