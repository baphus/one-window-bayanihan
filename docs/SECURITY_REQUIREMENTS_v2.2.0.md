# Security Requirements

> **Version:** 2.2.0 | **Updated:** 2026-09-16 | **Source:** `app/Providers/AppServiceProvider.php` (named rate limiters), `routes/auth.php`, `routes/web.php`, `routes/api.php`, `bootstrap/app.php`, middleware files, actual implementation
> **Supersedes:** `SECURITY_REQUIREMENTS_v2.1.0.md` (2026-07-27). Infrastructure controls are stated as
> platform requirements, not vendor features — see `DEPLOYMENT_GUIDE_v3.1.0.md` §1.
>
> **Delta scope:** v2.1.0 remains the design reference (defence-in-depth layers, headers/CSP,
> RBAC, encryption, audit, infrastructure, compliance). This revision corrects every rate-limit
> number and keying against the named limiters in `AppServiceProvider.php`, and corrects the
> login/MFA auth table to the routed reality (`AuthenticatedSessionController` for login,
> `MfaChallengeController` for the TOTP/recovery challenge — there is no `LoginOtpController`
> and no `/login/verify-otp` or `/login/resend-otp` route). Anything not re-stated here is
> unchanged from v2.1.0.

## Security Architecture (Defense in Depth)

```
Layer 1: Network (platform firewall/security groups, HTTPS-only ingress, trusted proxies)
Layer 2: Application Entry (CAPTCHA verification, rate limiting)
Layer 3: Global Middleware (SecurityHeaders, CSP, LogContext)
Layer 4: Authentication (password + TOTP MFA, session management)
Layer 5: Authorization (CheckRole, IpWhitelist, lane isolation)
Layer 6: Data Protection (PII encryption, audit logging, RLS)
```

## 1. Authentication

### Login Flow

1. **Email + Password** — Standard credential validation (`AuthenticatedSessionController@store`, `POST /login`)
2. **CAPTCHA** — bot-protection token validated on login POST (`VerifyTurnstile` middleware, `TURNSTILE_*` keys)
3. **TOTP MFA challenge (only if MFA enabled for the user)** — `MfaChallengeController@totp` (`POST /login/mfa/totp`) validates the authenticator code, or `MfaChallengeController@recovery` (`POST /login/mfa/recovery`) accepts a one-time recovery code. There is no `LoginOtpController`; login has no emailed-OTP step. Email OTP (6-digit, `OtpService`) is used for profile email-change, public intake verification, and the tracking portal — never for login.

### Rate Limiting on Auth

| Endpoint | Limiter | Limit | Scope |
|----------|---------|-------|-------|
| `POST /login` | `login` | 10/minute | Per email (falls back to IP when no email given) |
| `POST /login/mfa/totp` | `totp-challenge` | 3/minute | Per pending-MFA-session + IP (`MfaChallengeController`) |
| `POST /login/mfa/recovery` | `recovery-code` | 3/minute | Per pending-MFA-session + IP (`MfaChallengeController`) |
| `POST /profile/email-change/send-otp`, `POST /profile/email-change/verify-otp` | `otp` | 5/minute | Per email + IP |
| `POST /forgot-password` | `password-reset-request` | 5/minute | Dual: per email + IP, and per IP |
| `POST /invite/{token}` | `invite-register` | 10/minute | Per IP |
| `GET /verify-email/{id}/{hash}`, `POST /email/verification-notification` | `email-verification` | 6/minute | Per authenticated user (falls back to IP) |

### MFA (TOTP)

- **Library:** `pragmarx/google2fa-laravel` (v3.0.1)
- **QR Code:** `bacon/bacon-qr-code`
- **Recovery Codes:** 8 one-time-use codes generated on enrollment
- **Storage:** `mfa_secret` (encrypted), `mfa_recovery_codes` (encrypted JSON) in `users` table
- **Enforcement:** `CheckMfaEnrolled` middleware can redirect to setup if policy requires

### Session Management

- **Driver:** Database (`sessions` table)
- **Features:** IP address, user agent, last activity tracked per session
- **Admin:** Active session viewer + ability to terminate sessions
- **Timeout:** Configurable via `SESSION_LIFETIME` (default: 120 minutes)

### Password Security

- **Hashing:** Bcrypt (4 rounds in tests, default 12 in production)
- **Complexity:** Enforced via validation rules
- **Reset:** Token-based flow with email verification

## 2. Authorization (RBAC)

### Implementation

- **Mechanism:** Custom `CheckRole` middleware (NOT Spatie laravel-permission)
- **Storage:** `users.role` column (string: `CASE_MANAGER`, `AGENCY`, `ADMIN`)
- **Usage:** `Route::middleware('role:CASE_MANAGER,ADMIN')` — comma-separated allowed roles

### IP Whitelist (Admin)

- **Middleware:** `IpWhitelist`
- **Scope:** All `/admin/*` routes
- **Config:** Managed via `SecuritySettingsController` → `system_settings` table
- **Behavior:** Returns 403 if request IP not in whitelist

### Lane Isolation (Agency)

- Application-level enforcement: agencies can only see their own referrals
- `CaseController@show` checks if agency has active referral on the case
- Query scopes filter data by `agcy_id` for agency users
- PostgreSQL RLS as secondary defense layer

## 3. Security Headers

### Global Headers (`SecurityHeaders` middleware)

| Header | Value |
|--------|-------|
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `X-XSS-Protection` | `1; mode=block` |

### Content Security Policy (`ContentSecurityPolicy` middleware)

- Dynamic CSP with per-request nonce for inline scripts
- Configured via `config/csp.php`
- Violation reports sent to `POST /api/csp/report` (limiter `csp-report`, throttled at 120/min per IP)

## 4. Rate Limiting

All budgets below are the named limiters in `AppServiceProvider.php` — code truth. Named
limiters keep independent counters per feature so unrelated guest-facing endpoints never share
a budget. Keying notes: "per IP" keys on `$request->ip()`; "per user" keys on the
authenticated user ID and falls back to IP for guests.

| Limiter | Limit | Keying | Used By / Notes |
|---------|-------|--------|-----------------|
| `login` | 10/min | Per email (fallback: IP) | `POST /login`; prevents brute force |
| `otp` | 5/min | Per email + IP | Profile email-change send/verify; prevents OTP guessing |
| `totp-challenge` | 3/min | Per pending-MFA-session + IP | `POST /login/mfa/totp` (`MfaChallengeController`) |
| `recovery-code` | 3/min | Per pending-MFA-session + IP | `POST /login/mfa/recovery` (`MfaChallengeController`) |
| `password-reset-request` | 5/min | Dual: per email + IP, and per IP | `POST /forgot-password`; the per-IP leg stops inbox flooding via field rotation |
| `invite-register` | 10/min | Per IP | `POST /invite/{token}` |
| `email-verification` | 6/min | Per user (fallback: IP) | Email verify / verification-notification |
| `tracking` | 10/min | Per IP | `/track/send-otp`, `/track/verify-otp`; prevents enumeration |
| `intake-otp` | 5/min | Dual: per email + IP, and per IP | `/intake/verify-email`; the per-IP leg caps outbound mail to third parties |
| `intake-duplicate` | 10/min | Per IP | `/intake/check-duplicate` |
| `intake-submit` | 5/min | Per IP | `/intake/submit`, `/intake/register`, `/track/register` |
| `track-request-message` | 20/min | Per IP | `/track/request/messages` |
| `track-request-replacement` | 5/min | Per IP | `/track/request/replacement` |
| `track-request-exchange` | 10/min | Per IP | `/track/request/exchange` |
| `survey-view` | 30/min | Per IP | `GET /survey/{token}`; spam prevention |
| `survey-submit` | 10/min | Per IP | `POST /survey/{token}`; spam prevention |
| `contact-form` | 5/min | Per IP | `POST /contact`; spam prevention |
| `chatbot` | 30/min | Per IP | `POST /chatbot/message`; API cost control |
| `address-lookup` | 60/min | Per user (fallback: IP) | `/api/address/*` (5 public PSGC endpoints) |
| `csp-report` | 120/min | Per IP | `POST /api/csp/report`; high-volume endpoint |
| `readiness` | 60/min | Per IP | `GET /api/readyz` |
| `resend-webhook` | 300/min | Per IP | `POST /api/webhooks/resend` (delivery-event ingest) |
| `reports-view` | 120/min | Per user (fallback: IP) | `GET /reports`; expensive queries |
| `api-global` | 60/min | Per user (fallback: IP) | Session-authenticated `/api/*` helpers |
| `api-mutations` | 10/min | Per user (fallback: IP) | Reserved for authenticated mutations |
| `agency-client-request-create` | 5/hour | Per user + referral | Agency client-request creation |
| `agency-client-access` | 3/hour | Per user + client-request | Agency access issue/reissue, reopen |
| `agency-client-delivery-recipient` | 5/hour | Per recipient email hash (no cap when recipient unknown) | Per-recipient delivery cap, stacked with the two limiters above |

## 5. Data Protection

### PII Encryption (At-Rest)

| Field | Model | Cast |
|-------|-------|------|
| `first_name` | Client | `EncryptedString` |
| `last_name` | Client | `EncryptedString` |
| `email` | Client | `EncryptedString` |
| `contact_number` | Client | `EncryptedString` |
| `date_of_birth` | Client | `EncryptedDate` |

- Uses Laravel's `APP_KEY` for encryption
- Migration `2026_07_09_000001_encrypt_pii_fields.php` converts plaintext → encrypted

### Data Masking

- User model `$hidden`: `password`, `mfa_secret`, `mfa_recovery_codes`, `remember_token`
- API responses do not expose sensitive fields
- Tracking portal only shows non-PII case data

### PostgreSQL Row-Level Security (RLS)

- Enabled on core tables via migrations
- Session variable `app.current_user_id` set by `SetPostgresSession` middleware
- RLS policies restrict row access at the database level (secondary to application logic)

## 6. File Upload Security

### Validation

- MIME type validation (server-side, not just extension)
- File size limits (configurable via `config/file-uploads.php`)
- Malware scanning (`MalwareScannerTest` confirms implementation)
- File extension whitelist

### Storage

- Files stored in S3-compatible object storage, private by default, server-side encrypted
- Signed URLs for downloads (time-limited)
- Separate buckets/prefixes for different content types; audit archives on their own disk

## 7. Audit Trail

- **Append-only:** PostgreSQL trigger prevents UPDATE/DELETE on `audit_logs`
- **Hash chain:** SHA-256 `prev_hash` links each entry to its predecessor
- **Context:** IP address, user agent, correlation ID on every entry
- **Observer:** `AuditObserver` on 10 models for automatic CREATE/UPDATE/DELETE logging
- **Manual:** Login/logout explicitly logged in auth handlers

See [AUDIT_STRATEGY_v2.2.0.md](AUDIT_STRATEGY_v2.2.0.md) for full audit design.

## 8. Error Handling & Monitoring

### Error Handling

- Production: Generates incident ID, logs full trace, shows generic error to user
- Error-tracking ingest for non-local environments (auto-reports exceptions when `SENTRY_LARAVEL_DSN` is set)
- Custom error pages (403, 404, 429, 500) — no stack traces exposed

### Logging

- Correlation ID added to all log entries (`LogContext` middleware)
- Structured JSON logging in production
- `LogViewerController` for admin log inspection

## 9. Infrastructure Security

### HTTPS

- The deployment platform must enforce HTTPS-only ingress with TLS termination (capability C2)
- `TRUSTED_PROXIES` must list the ingress/load-balancer CIDR ranges of whichever platform is in use — an incorrect value breaks client-IP logging, rate limiting, and IP allowlisting
- Database connections use `DB_SSLMODE=require`; Redis uses TLS wherever the endpoint leaves the private network
- HSTS headers recommended at the infrastructure/ingress level

### Trusted Proxies

```php
$middleware->trustProxies(
    at: explode(',', env('TRUSTED_PROXIES', '10.0.0.0/8')),
    headers: Request::HEADER_X_FORWARDED_FOR | ...
);
```

### Docker Security

- Non-root PHP-FPM user
- Read-only filesystem where possible
- No unnecessary packages installed
- Secret management via environment variables

## 10. Legal Compliance

| Regulation | Requirement | Implementation |
|------------|-------------|----------------|
| RA 10173 (Data Privacy Act) | PII protection | Encrypted at-rest, access logging, consent tracking |
| RA 11641 (DMW Act) | Authorized inter-agency coordination | RBAC, lane isolation, audit trail |
| DICT Cloud First Policy | Cloud-hosted, compliant infrastructure | Containerised deployment on a compliant cloud platform with in-region/PH-accessible data services; the specific providers and their assurance evidence are recorded in the supplier register under `docs/compliance/` |

## 11. Known Gaps / Future Work

| Gap | Risk | Mitigation |
|-----|------|------------|
| No WAF | Layer 7 attacks | Rely on the platform's edge/DDoS protection + application rate limiting; a WAF must be added at the ingress if the platform does not provide one |
| No automated pen testing in CI | Regression vulnerabilities | Manual security reviews + security test suite |
| RLS not fully enforced (some tables) | Data leakage at DB level | Application-level authorization as primary control |
| No session IP binding | Session hijacking | Session has IP/UA recorded; anomaly detection is future work |

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 2.2.0 | 2026-09-16 | Rate-limit reconciliation against `AppServiceProvider.php` code truth: `login` 5→10/min, `tracking` 5→10/min, `totp-challenge` 5→3/min, `reports-view` 60→120/min; corrected keying throughout (per email / per pending-MFA-session / per user / per IP as coded); replaced the stale `/login/verify-otp` + `/login/resend-otp` auth rows with the routed reality (password login via `AuthenticatedSessionController`, TOTP/recovery challenge via `MfaChallengeController` — no `LoginOtpController`); documented all previously missing limiters (`intake-otp`, `intake-duplicate`, `intake-submit`, `track-request-message`, `track-request-replacement`, `track-request-exchange`, `contact-form`, `survey-view`, `survey-submit`, `readiness`, `resend-webhook`, `password-reset-request`, `invite-register`, `api-mutations`, and the three per-hour agency client-request limiters). No control was weakened or removed. |
| 2.1.0 | 2026-07-27 | Restated infrastructure controls as platform requirements instead of one vendor's features: defence-in-depth Layer 1/2, storage section (S3-compatible, private, encrypted, separate audit disk), §9 HTTPS/trusted-proxy section (now includes `DB_SSLMODE=require` and Redis TLS), the DICT Cloud First implementation row (points to the supplier register for named providers), and the WAF gap row. No control was weakened or removed; vendor-specific assurances were replaced with the requirement the platform must satisfy. |
| 2.0.0 | 2026-07-11 | Previous revision (`SECURITY_REQUIREMENTS.md`): vendor-named network and storage controls. |
