# MFA login challenge

Every enrolled account completes a TOTP or single-use recovery-code challenge after password verification, whether enrollment policy is required or optional. There is no `MFA_LOGIN_CHALLENGE_ENABLED` switch. The password POST creates a pending challenge rather than authenticating an enrolled account; successful verification establishes the authenticated session and its user/password-bound MFA marker.

Enrollment policy is separate: persisted role defaults plus per-user overrides determine who must enroll, with administrators always required. The policy is identical in every environment. Optional enrollment never skips an existing account's second factor. See [MFA_ROLLOUT.md](MFA_ROLLOUT.md) for policy controls and operator recovery.

The challenge page submits `code` to `POST /login/mfa/totp` or `POST /login/mfa/recovery`, or cancels through `POST /login/mfa/cancel`. Success redirects to the intended URL and preserves the login remember choice. Validation errors use normal Laravel responses. Challenge expiry, attempt limits, and verification-window tuning remain in MFA configuration.

A successful login challenge also opens a five-minute fresh-confirmation window. `POST /profile/mfa/confirm` accepts `otp` or `recovery_code` to refresh it using replay-safe verification. Policy changes and administrator resets require this window; ordinary activity never renews it. Disable submits its one-time proof directly to the disable action instead of consuming the same proof first at confirmation.

Recovery codes are returned only at enrollment and explicit regeneration, never through a retrieval GET. Regeneration requires password confirmation and fresh MFA confirmation. Required users cannot disable MFA; optional users must supply password and current TOTP or recovery proof. All enrolled accounts need a valid MFA session for protected access, independent of enrollment policy.
