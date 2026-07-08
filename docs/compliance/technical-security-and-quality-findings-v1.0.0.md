# Technical Security and Quality Findings — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.0.0 |
| Date | 2026-07-08 |
| Basis | Read-only static analysis at commit `b8a7211`, branch `main`. No runtime exploitation performed. |
| Standard editions | ISO/IEC 27001:2022, 27002:2022, 20000-1:2018, ISO 9001:2015 |
| Overall confidence | High for file-cited findings; Medium where runtime/infra behaviour is inferred (noted per finding). |

This is the master finding register. The other reports reference these `TECH-nnn` IDs. Secrets are redacted throughout (key names only).

## Changelog

| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial consolidated technical findings (36 findings + positives). |

## Severity summary

| Severity | Count | IDs |
|---|---|---|
| Critical | 1 | TECH-001 |
| High | 9 | TECH-002, 003, 004, 005, 006, 007, 008, 009, 010 |
| Medium | 14 | TECH-011…024 |
| Low | 12 | TECH-025…036 |

---

## CRITICAL

### TECH-001 — Public self-registration grants staff (`CASE_MANAGER`) role
- **Severity:** Critical · **Priority:** P0 · **Component:** `RegisteredUserController`, `routes/auth.php` · **Standards:** ISO 27002 5.15/5.16/8.2, ISO 27001 A.8, SOC 2 CC6.1, RA 10173/DPTM
- **Description:** The public `guest` `register` route is live (`routes/auth.php:41-45`). `RegisteredUserController::store` (`app/Http/Controllers/Auth/RegisteredUserController.php:34-47`) validates only name/email/password, then creates `User` with `'role' => 'CASE_MANAGER'` and auto-assigns a default agency. No email-domain allowlist, admin approval, or invite token.
- **Evidence:** `app/Http/Controllers/Auth/RegisteredUserController.php:34-47`; `routes/auth.php:41-45`.
- **Failure/exploitation scenario:** Any internet user registers, self-verifies via the emailed verification link, and obtains a **CASE_MANAGER** account with access to cases, OFW client PII (demographics, contact, next-of-kin, addresses, employment), stakeholders, and scoped audit logs. Broken access control / vertical privilege escalation into staff on a government PII system.
- **Remediation:** Disable public registration (remove the route) or gate it behind admin-created invite tokens; if self-signup must exist, assign a least-privilege pending role with no data access until an ADMIN approves and sets the role; enforce an email-domain allowlist.
- **Validation:** Attempt registration from a non-allowlisted email → account cannot reach any case/client data; add a regression test asserting `register` cannot produce a privileged role.
- **Related:** TECH-002, TECH-003.

---

## HIGH

### TECH-002 — Deactivated / soft-deleted accounts can still authenticate
- **Severity:** High · **Priority:** P0 · **Component:** `LoginOtpController`, `AdminUserController` · **Standards:** ISO 27002 5.18 (access rights), 8.2
- **Description:** The login flow (`LoginOtpController::init` L30-36, `verifyOtp` L112-140) looks up the user and calls `Auth::login($user, true)` with **no `is_active` / `is_deleted` check**. `AdminUserController::destroy` (L154-163) only sets `is_active=false`/`is_deleted=true` via `save()`; it never calls `delete()`, so the `SoftDeletes` scope never hides the row.
- **Evidence:** `app/Http/Controllers/LoginOtpController.php:30-36,112-140`; `app/Http/Controllers/Admin/AdminUserController.php:154-163`.
- **Scenario:** An offboarded or compromised user who was "deactivated"/"deleted" can still log in (knows password, can receive OTP). Access revocation is ineffective.
- **Remediation:** Reject `!$user->is_active || $user->is_deleted` before issuing OTP and before `Auth::login`; add a middleware check so live sessions of deactivated users are terminated.
- **Validation:** Deactivate a user; confirm login is blocked at step 1; add a regression test.
- **Related:** TECH-001.

### TECH-003 — MFA/2FA is opt-in and never enforced (not even for ADMIN)
- **Severity:** High · **Priority:** P1 · **Component:** `LoginOtpController`, `MfaController` · **Standards:** ISO 27002 8.5 (secure authentication), 5.17
- **Description:** MFA is per-user opt-in (`mfa_enabled_at`); the TOTP challenge runs only `if ($user->mfa_enabled_at !== null)` (`LoginOtpController.php:120`). No policy/middleware requires MFA for any role.
- **Evidence:** `app/Http/Controllers/LoginOtpController.php:120`; `routes/web.php:86-91`.
- **Scenario:** Admins operate with only password + email OTP; an attacker who phishes a password and reads the OTP email (or uses the debug-OTP path, TECH-020) fully authenticates to a privileged console.
- **Remediation:** Enforce MFA enrolment for ADMIN (ideally CASE_MANAGER) via middleware that redirects un-enrolled privileged users to setup and blocks other routes.
- **Validation:** Un-enrolled admin is forced to MFA setup before reaching any admin route.

### TECH-004 — Live session cookie file committed to git (`cookies.txt`)
- **Severity:** High (hygiene) / Medium (current exploitability — localhost scope) · **Priority:** P0 · **Standards:** ISO 27002 5.10, 8.24, 8.4 (source-code/secrets), SOC 2 CC6.1
- **Description:** `cookies.txt` is git-tracked (`git ls-files`), a Netscape cookie jar containing `XSRF-TOKEN` and `bayanihan-session` (HttpOnly) for host `localhost` (values redacted). Added in commit `7694294`. `.env` itself is correctly untracked.
- **Evidence:** `git ls-files` → `cookies.txt`.
- **Scenario:** Demonstrates a pattern of committing auth artifacts; a production cookie could be committed next. Session replay possible only if domain/secret reused.
- **Remediation:** `git rm --cached cookies.txt`; add to `.gitignore`; rotate `APP_KEY` if any non-local cookie was ever committed; purge from history with `git filter-repo` if the repo was shared.
- **Validation:** `git ls-files | grep cookies.txt` returns nothing; secret-scan CI gate active.

### TECH-005 — `trustProxies(at: '*')` enables X-Forwarded-For spoofing of the admin IP allowlist and rate limits
- **Severity:** High · **Priority:** P1 · **Component:** `bootstrap/app.php`, `IpWhitelist` · **Standards:** ISO 27002 8.20/8.22 (network security), 8.2
- **Description:** All proxies are trusted (`bootstrap/app.php:39`). `IpWhitelist` (`app/Http/Middleware/IpWhitelist.php:23`) and all rate limiters key on `$request->ip()`, which now honours the client-supplied left-most XFF entry.
- **Evidence:** `bootstrap/app.php:39`; `app/Http/Middleware/IpWhitelist.php:23`; `app/Providers/AppServiceProvider.php:81-111`; `docker/nginx/conf.d/default.conf:78`.
- **Scenario:** An attacker sends `X-Forwarded-For: <trusted-admin-IP>` to bypass the admin IP whitelist, or rotates spoofed IPs to evade per-IP throttling on `/login`, `/track`, and OTP endpoints.
- **Remediation:** Set `trustProxies(at: '<LB CIDR>')` matching the nginx `set_real_ip_from` ranges; never trust arbitrary upstreams.
- **Validation:** From an untrusted network, a forged XFF does not change `$request->ip()`, and the admin whitelist rejects it. *(Medium confidence on live exploitability — depends on deployed LB topology.)*

### TECH-006 — Audit-log hash-chaining is non-functional (tamper-evidence rests only on the DB trigger)
- **Severity:** High · **Priority:** P1 · **Component:** `AuditObserver`, `AuditLog` · **Standards:** ISO 27002 8.15 (logging integrity), SOC 2 CC7.2
- **Description:** `AuditObserver` copies the previous row's `prev_hash` forward but **never computes a hash** of the current record (`app/Observers/AuditObserver.php:68-75`); no `hash()`/`sha256`/`hmac` exists in the audit path. `prev_hash` stays NULL, providing zero tamper detection. The append-only Postgres trigger (P-02 below) is the real, and only, integrity control.
- **Evidence:** `app/Observers/AuditObserver.php:68-75`; migration `2026_07_04_000001_improve_audit_logs_table.php:17`; `app/Models/AuditLog.php:28`.
- **Scenario:** A privileged actor with DB access edits a row; the "chain" cannot detect it. `docs/AUDIT_STRATEGY.md`'s "cryptographically verifiable chain" claim is unmet.
- **Remediation:** Compute `prev_hash = sha256(previous_row_hash || canonical(current_record))` per entity/sequence; add a chain-verification command.
- **Validation:** Verification command detects a manually edited row.

### TECH-007 — Authentication auditing incomplete: failed logins, logouts, and data exports are not recorded
- **Severity:** High · **Priority:** P1 · **Component:** listeners, `AuditObserver`, `DataExportService` · **Standards:** ISO 27002 8.15/8.16, SOC 2 CC7.2, RA 10173 accountability
- **Description:** Only `LogSuccessfulLogin` is wired (`AppServiceProvider.php:115`). No listener for `Failed`, `Logout`, `Lockout`, `PasswordReset`, or OTP-failure. `AuditObserver` only handles `created/updated/deleted/restored` — no VIEW or EXPORT auditing; `DataExportService` exports PII with no audit write.
- **Evidence:** `app/Listeners/LogSuccessfulLogin.php`; `app/Observers/AuditObserver.php:13-43`; `app/Services/Export/DataExportService.php`.
- **Scenario:** Brute-force/credential-stuffing detection is impossible (the query returns nothing); bulk PII export is unlogged — a privacy-accountability gap. Contradicts `docs/AUDIT_STRATEGY.md` §3/§9 which mark these ✅.
- **Remediation:** Add listeners for `Failed`/`Logout`/`Lockout`/OTP-failure (with attempted identifier + IP); add explicit audit writes on show/download/export actions.
- **Validation:** A failed login and a data export each produce an audit row with actor/IP/timestamp.
- **Related:** TECH-003.

### TECH-008 — Backup relies entirely on provider defaults; no independent/offsite backup and no tested restore
- **Severity:** High · **Priority:** P1 · **Component:** deployment/ops · **Standards:** ISO 27002 8.13 (backup + restore testing), ISO 27001 A.5.29/5.30, SOC 2 A1.2/A1.3
- **Description:** `docs/DEPLOYMENT_GUIDE.md` §4.3/§9 cites Supabase auto-backups (daily, 7-day retention) + PITR (pro plan). No `pg_dump`/`mysqldump`/`spatie/laravel-backup` anywhere; `scripts/` has only an address utility; no restore-test evidence.
- **Evidence:** `docs/DEPLOYMENT_GUIDE.md` §4.3, §9; negative grep of `composer.json`, `scripts/`, `.github/`.
- **Scenario:** Single-vendor dependency; 7-day retention is short for a government case system; a provider account compromise/deletion is unrecoverable; restore is asserted but never tested.
- **Remediation:** Add scheduled, encrypted logical dumps to independent offsite storage; verify storage-bucket versioning; document and perform a restore test with evidence.
- **Validation:** A documented restore drill reproduces the DB + a sample uploaded document.
- **Related:** TECH-019.

### TECH-009 — No PR-triggered CI; no lint / static analysis / type-check / dependency-audit / SAST / secret-scanning
- **Severity:** High · **Priority:** P1 · **Component:** `.github/workflows` · **Standards:** ISO 27002 8.28/8.29 (secure coding, security testing), 8.25, ISO 9001 8.6, SOC 2 CC8.1
- **Description:** Workflows trigger only on `push: main` and `workflow_dispatch` — no `pull_request` trigger, so tests run **after** merge (which auto-deploys). No Pint/PHPStan/ESLint/Prettier/tsc/`composer audit`/`npm audit`/Dependabot/gitleaks/CodeQL anywhere, despite `docs/TESTING_STRATEGY.md` listing them as "Planned — Every PR".
- **Evidence:** `.github/workflows/deploy-staging.yml:3-7,78-85`; negative grep of `.github/` for the tools; no `pint.json`/`phpstan.neon`/`.eslintrc`.
- **Scenario:** Broken or vulnerable code lands on `main` and deploys before verification; no automated CVE/secret detection.
- **Remediation:** Add a `pull_request` CI job (tests + Pint + Larastan + ESLint + `tsc --noEmit` + `composer/npm audit` + gitleaks) and make it a required status check; add Dependabot/Renovate.
- **Validation:** A PR that fails lint/tests/audit is blocked from merge. *(Branch protection itself is external evidence.)*

### TECH-010 — Governance documentation contradicts the implemented code
- **Severity:** High · **Priority:** P1 · **Component:** `docs/*` · **Standards:** ISO 27001/9001 clause 7.5.3 (control of documented information)
- **Description:** Multiple docs misdescribe reality: (a) four docs claim **Spatie RBAC** with `roles`/`permissions` tables that do not exist — actual is a `users.role` column check; (b) RLS, security headers, and password-reset are marked "not done / 404" but are actually implemented; (c) TOTP, recovery codes, and Turnstile are implemented but undocumented; (d) `DATA_MODEL.md` fixes "39 tables" while the schema has grown (feedback_invitations, case categories/issues, active sessions) and dropped others; (e) all docs carry a stale "2026-05-28" header.
- **Evidence:** `docs/PROJECT_RULES.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_REQUIREMENTS.md`, `docs/DATA_MODEL.md`; vs `app/Http/Middleware/CheckRole.php:13`, `composer.json` (no spatie), migrations `..._000008_enable_row_level_security`, `app/Http/Middleware/{ContentSecurityPolicy,SecurityHeaders}.php`, `routes/auth.php`.
- **Scenario:** An auditor sampling docs vs code finds the documentation unreliable — a direct nonconformity against control of documented information, and it erodes trust in every other claim in the doc set.
- **Remediation:** Reconcile all governance docs with the code; version-bump per changelog convention; establish a doc-review-on-change control.
- **Validation:** A sampling review finds doc statements match code for RBAC, RLS, headers, auth, and the table inventory.

---

## MEDIUM

### TECH-011 — TOTP secret and recovery codes stored in plaintext; non-constant-time recovery-code check
- **Severity:** Medium · **Priority:** P1 · **Standards:** ISO 27002 8.24 (cryptography)
- **Description:** `mfa_secret` has no `encrypted` cast; `mfa_recovery_codes` cast as plain `array`; recovery match uses `in_array(...)` (plaintext, not constant-time). `$hidden` only prevents serialization, not at-rest exposure.
- **Evidence:** `app/Models/User.php:69-83`; `app/Http/Controllers/LoginOtpController.php:212`; test confirms plaintext (`MfaLoginTest.php:326`).
- **Scenario:** A DB read (SQLi, backup leak, insider) yields working TOTP seeds + recovery codes for every user, defeating 2FA.
- **Remediation:** `'mfa_secret' => 'encrypted'`; store recovery codes hashed (`Hash::make`) and verify with `Hash::check`; show plaintext codes only once at generation.
- **Validation:** DB inspection shows ciphertext; recovery-code login still works.

### TECH-012 — No session invalidation after password reset or change
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.5, 5.17
- **Description:** `NewPasswordController::store` (L46-56) rotates `remember_token` but does not invalidate DB sessions; `PasswordController::update` (L16-28) has no `Auth::logoutOtherDevices()`.
- **Evidence:** `app/Http/Controllers/Auth/NewPasswordController.php:46-56`; `app/Http/Controllers/Auth/PasswordController.php:16-28`.
- **Scenario:** After a reset (typical compromise response), a pre-existing attacker session (120-min lifetime, always-on "remember") remains valid.
- **Remediation:** Call `Auth::logoutOtherDevices($password)` and delete other `sessions` rows on password change/reset.
- **Validation:** Second session is invalidated after reset.

### TECH-013 — CSP allows `'unsafe-inline'` and `'unsafe-eval'` in production `script-src`
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.26, 8.9
- **Description:** `getProdPolicy` sets `script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com` (`app/Http/Middleware/ContentSecurityPolicy.php:75`), removing CSP's XSS-mitigation value.
- **Remediation:** Remove `unsafe-inline`/`unsafe-eval`; adopt nonce/hash-based CSP for the Inertia bootstrap.
- **Validation:** Strict CSP with no console violations; inline `<script>` injection blocked.

### TECH-014 — PII stored in plaintext at rest (no application-layer encryption)
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.24, 5.34 (PII protection), RA 10173/DPTM
- **Description:** Client PII, next-of-kin, and employment data have no `encrypted` casts; reliance is on Postgres RLS + disk security. `docs/SECURITY_REQUIREMENTS.md:140` acknowledges plaintext key storage as "documented technical debt".
- **Remediation:** Apply `encrypted` casts or column-level encryption to the most sensitive PII fields; define a key-management approach.
- **Validation:** DB inspection shows ciphertext for targeted fields; queries still function.

### TECH-015 — Audit retention/prune conflicts with the append-only trigger; retention policy unenforced
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.15, 5.33; RA 10173 (retention/disposal)
- **Description:** Monthly `audit:prune --force` calls `forceDelete()` (`PruneAuditLogs.php:46`) but the `BEFORE UPDATE OR DELETE` trigger `RAISE EXCEPTION`s, so prune fails at runtime; default flat 365-day retention also contradicts the tiered policy in `AUDIT_STRATEGY.md` §6 (which admits it is "not yet implemented").
- **Remediation:** Choose an archival/partition strategy or a privileged maintenance path that is itself audited; align retention tiers with policy (LEGAL-006).
- **Validation:** Prune runs without error and honours tiered retention.

### TECH-016 — No centralized logging, error tracking, or alerting
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.16, SOC 2 CC7.2
- **Description:** Default log stack → single file; `slack`/`papertrail` channels env-gated and unset; no Sentry/Bugsnag/Telescope/Horizon. Container logs use `json-file` with 10m×3 rotation (~30 MB). Slack is CI-only, not app alerting.
- **Remediation:** Wire an error tracker (e.g. Sentry); route logs to a durable channel; configure failed-job and security-event alerting.
- **Validation:** A forced error and a failed job produce an alert.

### TECH-017 — External HTTP calls have no timeout/retry (availability risk)
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.6 (capacity), ISO 20000-1 8.6.1
- **Description:** `VerifyTurnstile` (`:26`), Cloudinary SDK, and AI calls (`ChatbotController.php:52`, `ReportsController.php:172`) set no `timeout()`/`retry()`. With php.ini `max_execution_time=60`, a slow third party (Turnstile on the login path) can exhaust FPM workers.
- **Remediation:** Set explicit `timeout()`/`connectTimeout()`/`retry()` on all outbound HTTP; move non-critical calls to queued jobs; define fail-open/closed per endpoint.
- **Validation:** Simulated slow upstream does not stall the login path.

### TECH-018 — No rollback strategy; deploy step masks failures (`continue-on-error: true`)
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 20000-1 8.5.1 (release/deployment), ISO 9001 8.6, ISO 27002 8.32
- **Description:** The deploy step is `continue-on-error: true` with only `sleep 30` after; no health-gate, no rollback, migrations run `--force` with no pre-migration snapshot.
- **Evidence:** `.github/workflows/deploy-staging.yml:96-106`; `docs/DEPLOYMENT_GUIDE.md` §9.
- **Remediation:** Remove `continue-on-error`; add a health-gated post-deploy probe that fails the job; add a pre-deploy DB snapshot and documented rollback.
- **Validation:** A deliberately failing deploy fails the pipeline and triggers rollback.

### TECH-019 — No RPO/RTO or DR/BCP documentation
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27001 A.5.29/5.30, ISO 20000-1 8.7.2, SOC 2 A1.2/A1.3
- **Description:** No RPO/RTO figures anywhere; `DEPLOYMENT_GUIDE.md` §9 lists backup assets but no recovery objectives, no BIA, no DR site, no BCP doc. NFR-PERF-007 states a 99% availability target (provider-dependent, unmeasured).
- **Remediation:** Author a DR/BCP with quantified RPO/RTO and a tested recovery runbook.
- **Related:** TECH-008.

### TECH-020 — Debug OTP returned in HTTP response and partial OTP logged
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.5, 8.31 (test/prod separation)
- **Description:** `LoginOtpController::init`/`resendOtp` return `debug_otp` when `debug_otp_enabled` and env ∈ local/staging/testing (L51, L88); `OtpService::verify` logs the first 2 digits (`OtpService.php:47-52`). `docs/SECURITY_REQUIREMENTS.md:132` notes a related debug-OTP exposure via Inertia meta-props.
- **Scenario:** If toggled on in shared staging, OTPs are handed to the client, bypassing the email factor.
- **Remediation:** Restrict debug OTP to `local` only; remove OTP fragments from logs.
- **Validation:** Staging never returns `debug_otp`; logs contain no OTP digits.

### TECH-021 — CI runtime versions below manifest requirements
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 9001 8.5.1, ISO 27002 8.31
- **Description:** `composer.json` requires PHP `^8.3` but CI sets up PHP 8.2 (`deploy-staging.yml:39`); Vite `^8`/`@types/node ^26` need Node 20+ but CI uses Node 18 (`:46`). CI does not test the declared runtime.
- **Remediation:** Bump CI to PHP 8.3 and Node 20/22.

### TECH-022 — Dependency conflicts and dual lockfiles
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.28, ISO 9001 8.4
- **Description:** Both `bun.lock` and `package-lock.json` present (drift risk; CI uses `npm ci`). `@types/react ^19` vs runtime React 18. `tailwindcss ^3.2` declared alongside `@tailwindcss/vite ^4` (bun resolves Tailwind 4.3.0) — contradictory major versions.
- **Remediation:** Single package manager + single lockfile; align `@types/react` to 18; consolidate Tailwind to one major with matching config.

### TECH-023 — Testing-strategy documentation materially false
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 9001/27001 7.5.3, ISO 9001 8.6
- **Description:** `docs/TESTING_STRATEGY.md` states "29 tests", "unit tests not yet written", SQLite in-memory test DB, "PHPUnit 11.x". Actual: 116 Feature + 13 Unit files, Postgres test DB (`phpunit.xml:26-27`), PHPUnit `^12.5`, ~984 passing. Stale "8 pre-existing failures … Do NOT fix" guidance could suppress real regressions.
- **Remediation:** Regenerate from the current suite; version-bump.
- **Related:** TECH-010.

### TECH-024 — User enumeration via password-reset responses
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.5
- **Description:** `PasswordResetLinkController::store` (L39-49) returns success only on `RESET_LINK_SENT` and otherwise throws with the translated status, revealing whether an email is registered. (Login itself returns generic errors — good.)
- **Remediation:** Always return the same generic "if that email exists…" response.

---

## LOW

### TECH-025 — Notification IDOR: mark-as-read not scoped to owner
- **Severity:** Low · **Priority:** P3 · `NotificationService::markAsRead` looks up by `id` only (`NotificationService.php:96-105`). Any authenticated user can mark another's notification read (UUID-guessing; no data disclosed). Fix: scope to `$request->user()->notifications()`.

### TECH-026 — RLS session variables set via string interpolation
- **Severity:** Low · **Priority:** P3 · `SetPostgresSession.php:35-36` interpolates `$user->id`/`$user->role` into `SET SESSION`. Not currently injectable (UUID + enum) but fragile. Fix: `SELECT set_config(?, ?, false)` with bindings.

### TECH-027 — Health-check path mismatch (`/health` vs `/up`)
- **Severity:** Low · **Priority:** P3 · App exposes `/up` (`bootstrap/app.php:33`); `reset-staging-data.yml:32` curls `/health`, which has no route → false health signal in CI. Fix: standardize on `/up`.

### TECH-028 — Conflicting/duplicated security headers (nginx vs Laravel)
- **Severity:** Low · **Priority:** P3 · nginx sets `X-Frame-Options SAMEORIGIN` (`default.conf:23`) while Laravel sets `DENY` (`SecurityHeaders.php:27`); both emitted. nginx also sets deprecated `X-XSS-Protection`. Fix: single source of truth (Laravel), drop `X-XSS-Protection`.

### TECH-029 — Upload validation divergence (FormRequest vs config)
- **Severity:** Low · **Priority:** P3 · `StoreCaseDocumentRequest.php:27` hardcodes `max:20480` (20 MB) while `config/file-uploads.php` allows 10 MB. Real gate is `StorageService::validate()` (config-driven + finfo + ClamAV). Fix: drive FormRequest limits from config.

### TECH-030 — Staging credentials broadcast in CI/Slack
- **Severity:** Low (staging-scoped) · **Priority:** P3 · `deploy-staging.yml:121-122` and `reset-staging-data.yml:42` post fixed test logins + a short numeric password to Slack. Fix: remove credentials from notifications; reference a secured runbook.

### TECH-031 — Chatbot forwards raw user input to the LLM (prompt injection)
- **Severity:** Low · **Priority:** P3 · `ChatbotController.php:48-54` passes validated (≤1000 char) user input into the agent prompt with a static guide (no path traversal; reply rendered without `rehype-raw`). Bot has no privileged tools/data. Fix: output caps + refusal guard; keep bot unprivileged.

### TECH-032 — Container app health-check is trivial
- **Severity:** Low · **Priority:** P3 · `docker-compose.yml:86-91` app health-check is `php -r "echo 'ok';"` (proves only the binary runs); nginx gates on it, so a broken app still receives traffic. Fix: curl `/up`.

### TECH-033 — No `declare(strict_types=1)`; weak frontend typing
- **Severity:** Low · **Priority:** P3 · 0/166 PHP files use strict types; 190 `.jsx` vs 6 `.tsx`; `tsconfig.json` not `strict`, `skipLibCheck:true`; no `tsc --noEmit` step. Fix: adopt strict types incrementally; enable `strict` + a `tsc` CI check.

### TECH-034 — RLS enable-migration soft-fails on non-Postgres
- **Severity:** Low · **Priority:** P3 · The enable-RLS migration is wrapped in try/catch and only warns on non-PG; a non-PG environment runs with no row-level isolation, relying solely on app-layer checks. Fix: fail-closed or document the hard PG dependency.

### TECH-035 — Queue worker & scheduler absent in the PaaS single-container image
- **Severity:** Low–Medium (Medium confidence) · **Priority:** P2 · `docker/supervisord.conf` starts only php-fpm + nginx. If Render uses this image without separate worker/cron services, queued emails/notifications and all scheduled cleanup/prune/sync jobs silently do not run. Verify Render service config (external evidence).

### TECH-036 — Frontend and end-to-end critical-path test coverage largely absent
- **Severity:** Low · **Priority:** P3 · Backend well-covered (129 files) but only ~2–3 Vitest component tests and E2E limited to onboarding; the 6 documented critical E2E paths (login→OTP→dashboard, create case, referral, closure, public tracking) are unimplemented. Fix: implement documented critical-path E2E + expand component coverage.

---

## Positive controls (implemented effectively)

| ID | Control | Evidence | Why effective | Tests |
|---|---|---|---|---|
| P-01 | File-upload pipeline | `app/Services/StorageService.php` (ClamAV scan, UUID filenames, finfo MIME sniff, extension+size allowlist); commit `b8a7211` tightened types across FormRequest/config/map/React | Defense-in-depth; content-based, not extension-trust | `CaseDocumentUploadValidationTest`, `StorageServiceMimeValidationTest` |
| P-02 | Append-only audit table (DB trigger) | migration `2026_07_04_000001:34-48` (`BEFORE UPDATE OR DELETE … RAISE EXCEPTION`); INSERT-only model | Real tamper-evidence at the DB layer (compensates for TECH-006) | `RLS`/audit integrity tests |
| P-03 | Sensitive-data redaction in audit values | `app/Models/AuditLog.php:39-79` (recursive key redaction + CR/LF stripping) | Prevents secret leakage and log injection | — |
| P-04 | Object-level authorization in sensitive controllers | `ReferralController::authorizeReferralAccess`, `ClientController::authorizeClientAccess` (404-not-403), `CaseDocumentController::authorizeAccess` | Correct horizontal-access control; existence non-disclosure | `AuthorizationGapTest`, `ReferralAuthorizationTest`, `ClientControllerAuthTest` |
| P-05 | Session cookie hardening + regeneration | `config/session.php` (encrypt/secure/httponly/lax); regeneration on login, invalidation on logout/delete | Strong session security | `AuthenticationTest`, `OtpSessionBindingTest` |
| P-06 | Multi-factor-by-default login | password + emailed OTP for all logins, session-bound OTP, CSPRNG `random_int`, 5-attempt lockout | Raises the bar even without TOTP | `MfaLoginTest`, `OtpServiceTest` |
| P-07 | Comprehensive rate limiting | 7 named limiters (`AppServiceProvider.php:81-113`) + nginx `limit_req_zone` | App + edge defense-in-depth | `RateLimit` security tests |
| P-08 | CSRF fully enforced (no exceptions) | no `validateCsrfTokens(except:)` anywhere | All state-changing web routes protected | — |
| P-09 | dompdf hardened | `config/dompdf.php` (`enable_remote=false`, `enable_php=false`, `chroot`) | Blocks SSRF/LFI via PDF | — |
| P-10 | CSV formula-injection neutralized | `DataExportService.php:251-255` (`writeSafeCell`) | Prevents spreadsheet formula injection | — |
| P-11 | Parameterized SQL throughout | raw SQL is static or bound | No SQLi found | — |
| P-12 | Mass-assignment discipline | explicit `$fillable`; `ProfileUpdateRequest` omits `role`/`agcy_id`/`is_active` | Blocks privilege escalation via profile update | — |
| P-13 | npm supply-chain hardening | `.npmrc` `ignore-scripts=true`, `audit=true`; `trustedDependencies` allowlist | Blocks malicious postinstall scripts | — |
| P-14 | Container hardening | compose `no-new-privileges`, `cap_drop: ALL`, resource limits | Reduces blast radius | — |
| P-15 | Requirements traceability | `docs/REQUIREMENTS_TRACEABILITY.md` (354 reqs → impl → verification) | Strong ISO 9001 §8.3 design evidence | — |

---

*Confidence: High for all file-cited findings; Medium for inferred runtime/infra behaviour (TECH-005 live exploitability, TECH-035 Render topology). No files were modified during this assessment.*
