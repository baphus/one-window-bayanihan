# MFA challenge activation

1. Deploy the backend with `MFA_LOGIN_CHALLENGE_ENABLED=false` and verify enrollment and challenge endpoints.
2. Confirm the production cache is shared and available for TOTP replay claims.
3. Run `php artisan mfa:revoke-enrolled-sessions --force`. The command reports and deletes only database sessions whose `user_id` belongs to a currently enrolled user; without `--force` it requires interactive confirmation.
4. Set `MFA_LOGIN_CHALLENGE_ENABLED=true` in the deployment environment and restart the web/queue processes.
5. Smoke-test an enrolled user with TOTP and recovery code, and a non-enrolled user with password-only login.

Do not enable the flag before step 3. No schema migration is required.

## Rollback

To deactivate the MFA login challenge after it has been enabled:

1. Set `MFA_LOGIN_CHALLENGE_ENABLED=false` in the deployment environment and restart the web/queue processes.
2. Any in-progress pending challenge session will be invalidated on the next request (the `EnsureMfaChallenge` middleware redirects to login when the flag is off).
3. MFA-enrolled users return to password-only login immediately.
4. The `EnsureMfaSession` middleware will no longer revoke sessions that lack the bound marker.
5. Existing MFA enrollment data (`mfa_secret`, `mfa_recovery_codes`, `mfa_enabled_at`) is preserved; re-activation requires only the activation steps above.
6. The session-revocation command (`mfa:revoke-enrolled-sessions`) can be used before re-activation if desired, but is not required for rollback.
