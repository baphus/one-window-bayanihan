# MFA challenge activation

There is no `MFA_LOGIN_CHALLENGE_ENABLED` flag in the codebase, so there is no flag to turn on. The challenge runs for any user with `mfa_enabled_at` set whose role passes `User::isInMfaEnforcedRole()` (`app/Http/Requests/Auth/LoginRequest.php:76`); in production that helper returns true for every role (`app/Models/User.php:118-131`). What a deployment configures is *enrollment* enforcement and challenge tuning, both in `config/mfa.php`.

1. Deploy the backend and confirm enrollment enforcement: `MFA_ENROLLMENT_ENFORCEMENT_ENABLED=true` and `MFA_ENROLLMENT_ENFORCED_ROLES=ADMIN,CASE_MANAGER,AGENCY` (`.env.example`). Non-production honours both keys; `APP_ENV=production` ignores them and enforces every role (`app/Http/Middleware/CheckMfaEnrolled.php`).
2. Confirm the production cache is shared and available for TOTP replay claims: replay detection is `Cache::add(..., mfa.replay_ttl)` and fails closed when the cache is unavailable (`app/Services/MfaService.php:162`).
3. Run `php artisan mfa:revoke-enrolled-sessions --force`. The command reports and deletes only database sessions whose `user_id` belongs to a currently enrolled user; without `--force` it requires interactive confirmation (`app/Console/Commands/RevokeMfaEnrolledSessions.php:10,16`).
4. No configuration flip is needed afterwards. A non-enrolled user in an enforced role is redirected to the profile MFA setup on their next non-excluded request (`app/Http/Middleware/CheckMfaEnrolled.php`).
5. Smoke-test an enrolled user with TOTP and with a recovery code, and a non-enrolled user with password-only login (expect the MFA setup prompt rather than a completed login).

No schema migration is required.

## Rollback

The login challenge is not config-switchable:

- `EnsureMfaChallenge` reads only the `mfa_pending` session state and `mfa.pending_ttl`; there is no flag for it to check (`app/Http/Middleware/EnsureMfaChallenge.php`).
- In production `MfaController::disable` returns 403, so a user cannot drop the second factor themselves (`app/Http/Controllers/MfaController.php:102-108`).

Supported rollback paths:

1. Per user: `POST /users/{user}/reset-mfa` (admin, password-confirmed) clears `mfa_secret`, `mfa_recovery_codes`, and `mfa_enabled_at` and deletes the target's sessions inside a transaction, then writes a security audit row (`app/Http/Controllers/AdminUserController.php`, `UserService::resetMfa`).
2. Non-production only: set `MFA_ENROLLMENT_ENFORCEMENT_ENABLED=false` to skip the enrollment redirect, or narrow `MFA_ENROLLMENT_ENFORCED_ROLES`. Both keys are ignored when `APP_ENV=production`.
3. In-progress pending challenge sessions expire on their own: `EnsureMfaChallenge` clears state older than `mfa.pending_ttl` and redirects to login.
4. Existing MFA enrollment data for untouched users (`mfa_secret`, `mfa_recovery_codes`, `mfa_enabled_at`) is preserved; re-activation requires only the steps above.
5. The session-revocation command (`mfa:revoke-enrolled-sessions`) can be used before either path if desired, but is not required for rollback.
