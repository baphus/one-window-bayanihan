# Security Requirements

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Source:** `bootstrap/app.php`, middleware files, actual implementation

## Security Architecture (Defense in Depth)

```
Layer 1: Network (Render firewall, HTTPS-only, trusted proxies)
Layer 2: Application Entry (Turnstile CAPTCHA, rate limiting)
Layer 3: Global Middleware (SecurityHeaders, CSP, LogContext)
Layer 4: Authentication (OTP + TOTP MFA, session management)
Layer 5: Authorization (CheckRole, IpWhitelist, lane isolation)
Layer 6: Data Protection (PII encryption, audit logging, RLS)
```

## 1. Authentication

### Login Flow (Multi-Factor)

1. **Email + Password** — Standard credential validation
2. **Cloudflare Turnstile** — CAPTCHA validation on login POST (`VerifyTurnstile` middleware)
3. **Email OTP** — 6-digit OTP sent to registered email (required for all users)
4. **TOTP (optional)** — Google Authenticator / any TOTP app (if MFA enabled)

### Rate Limiting on Auth

| Endpoint | Limit | Scope |
|----------|-------|-------|
| `POST /login` | 5/minute | Per email |
| `POST /login/verify-otp` | 3/minute | Per session |
| `POST /login/resend-otp` | 3/minute | Per session |
| `POST /login/verify-totp` | 5/minute | Per session |
| `POST /login/verify-recovery-code` | 3/minute | Per session |

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
- Violation reports sent to `POST /api/csp/report` (throttled at 120/min)

## 4. Rate Limiting

| Endpoint/Group | Limit | Notes |
|----------------|-------|-------|
| Login | 5/min per email | Prevents brute force |
| OTP verification | 3/min per session | Prevents OTP guessing |
| TOTP challenge | 5/min per session | |
| Recovery codes | 3/min per session | |
| Tracking portal | 5/min per IP | Prevents enumeration |
| API global | 60/min per user | Authenticated API |
| Reports page | 60/min per route | Expensive queries |
| AI insights | 10/min per route | API cost control |
| Chatbot | 30/min per route | API cost control |
| Public feedback | 30/min (view), 10/min (submit) | Spam prevention |
| CSP reports | 120/min | High-volume endpoint |
| Address API | 60/min | Public PSGC data |
| Email verification | 6/min | |

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

- Files stored in Supabase Storage (S3-compatible)
- Signed URLs for downloads (time-limited)
- Separate buckets for different content types

## 7. Audit Trail

- **Append-only:** PostgreSQL trigger prevents UPDATE/DELETE on `audit_logs`
- **Hash chain:** SHA-256 `prev_hash` links each entry to its predecessor
- **Context:** IP address, user agent, correlation ID on every entry
- **Observer:** `AuditObserver` on 10 models for automatic CREATE/UPDATE/DELETE logging
- **Manual:** Login/logout explicitly logged in auth handlers

See [AUDIT_STRATEGY.md](AUDIT_STRATEGY.md) for full audit design.

## 8. Error Handling & Monitoring

### Error Handling

- Production: Generates incident ID, logs full trace, shows generic error to user
- Sentry integration for non-local environments (auto-reports exceptions)
- Custom error pages (403, 404, 429, 500) — no stack traces exposed

### Logging

- Correlation ID added to all log entries (`LogContext` middleware)
- Structured JSON logging in production
- `LogViewerController` for admin log inspection

## 9. Infrastructure Security

### HTTPS

- Render enforces HTTPS-only
- `TRUSTED_PROXIES` configured for Render's proxy network
- HSTS headers recommended at infrastructure level

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
| DICT Cloud First Policy | Cloud-hosted, compliant infrastructure | Supabase + Render (PH-accessible) |

## 11. Known Gaps / Future Work

| Gap | Risk | Mitigation |
|-----|------|------------|
| No WAF | Layer 7 attacks | Rely on Render's DDoS protection + rate limiting |
| No automated pen testing in CI | Regression vulnerabilities | Manual security reviews + security test suite |
| RLS not fully enforced (some tables) | Data leakage at DB level | Application-level authorization as primary control |
| No session IP binding | Session hijacking | Session has IP/UA recorded; anomaly detection is future work |
