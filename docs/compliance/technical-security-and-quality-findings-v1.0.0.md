# Technical Security and Quality Findings — One Window Bayanihan

| Field | Value |
|---|---|
| Version | v1.2.0 |
| Date | 2026-07-08 |
| Basis | Read-only static analysis at commit `b8a7211`, branch `main`. No runtime exploitation performed. |
| Re-verification | 2026-10-03 — every `path:line` citation re-checked against the working tree; each finding now carries a **Status (2026-10-03)** line. Citations written at the original assessment are retained as history and explicitly labelled. |
| Standard editions | ISO/IEC 27001:2022, 27002:2022, 20000-1:2018, ISO 9001:2015 |
| Overall confidence | High for file-cited findings; Medium where runtime/infra behaviour is inferred (noted per finding). |

This is the master finding register. The other reports reference these `TECH-nnn` IDs. Secrets are redacted throughout (key names only).

**Status legend (2026-10-03 re-verification).** Every finding carries a **Status** line: **RESOLVED** (the issue no longer applies — current evidence given), **PARTIAL** (partly remediated — residual stated), **OPEN** (the issue persists — re-cited to current code), or **HISTORICAL** (the cited code or workflow was deleted; the finding is kept as a compliance record). Citations dated to the original assessment are labelled *(historical, commit `b8a7211`)* and are kept for traceability — they are not claims about the current tree. No finding has been deleted.

## Changelog

| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial consolidated technical findings (36 findings + positives). |
| v1.1.0 | 2026-07-08 | Phase 1-3 remediation | Remediated findings: TECH-001 (registration), TECH-002 (deactivated login), TECH-003 (MFA enforcement), TECH-004 (cookies.txt), TECH-005 (trustProxies), TECH-006 (audit hash chain), TECH-007 (audit events), TECH-009 (PR CI), TECH-010 (docs reconcilation, partial), TECH-011 (MFA secrets encryption), TECH-012 (session invalidation), TECH-013 (nonce-based CSP), TECH-015 (audit prune), TECH-017 (HTTP timeouts), TECH-020 (debug OTP scope), TECH-021-022 (CI runtimes/deps), TECH-024 (password reset enumeration), TECH-028 (duplicate headers). See iso-remediation-roadmap.md for detailed tracking. |
| v1.2.0 | 2026-10-03 | Citation re-verification | Re-verified every citation against the working tree. Removed all live reliance on code that no longer exists (`LoginOtpController`, `deploy-staging.yml`, `reset-staging-data.yml`, `tests/Feature/Auth/MfaLoginTest.php`); corrected the CI runtime claims (CI uses PHP 8.4 / Node 24 — never 8.2/18 in the current tree) and the MFA-enforcement claim (enforced for all roles in production). Added a **Status (2026-10-03)** line to every finding: RESOLVED / PARTIAL / OPEN / HISTORICAL / UNVERIFIED notes, each with current-code evidence. |

## Severity summary

| Severity | Count | IDs |
|---|---|---|
| Critical | 1 | TECH-001 |
| High | 9 | TECH-002, 003, 004, 005, 006, 007, 008, 009, 010 |
| Medium | 14 | TECH-011…024 |
| Low | 12 | TECH-025…036 |

Counts and severities are the original point-in-time assessment (2026-07-08) and are not re-numbered — this is a compliance record. Current dispositions are in each finding's **Status (2026-10-03)** line.

---

## CRITICAL

### TECH-001 — Public self-registration grants staff (`CASE_MANAGER`) role
- **Severity:** Critical · **Priority:** P0 · **Component:** `RegisteredUserController`, `routes/auth.php` · **Standards:** ISO 27002 5.15/5.16/8.2, ISO 27001 A.8, SOC 2 CC6.1, RA 10173/DPTM
- **Status (2026-10-03): RESOLVED** — no public registration route remains. `routes/auth.php:41-46` exposes only invite-token registration (`GET`/`POST invite/{token}` → `RegisterViaInviteController`), and `php artisan route:list --except-vendor` shows no public `register` route. `app/Http/Controllers/Auth/RegisteredUserController.php` still contains the original privileged-role creation (`'role' => 'CASE_MANAGER'` at `:44`) but is no longer routed — dead code retained as history.
- **Description (historical, commit `b8a7211`):** The public `guest` `register` route is live (`routes/auth.php:41-45`). `RegisteredUserController::store` (`app/Http/Controllers/Auth/RegisteredUserController.php:34-47`) validates only name/email/password, then creates `User` with `'role' => 'CASE_MANAGER'` and auto-assigns a default agency. No email-domain allowlist, admin approval, or invite token.
- **Evidence (historical):** `app/Http/Controllers/Auth/RegisteredUserController.php:34-47`; `routes/auth.php:41-45` (line numbers now describe invite routes, not public registration).
- **Failure/exploitation scenario:** Any internet user registers, self-verifies via the emailed verification link, and obtains a **CASE_MANAGER** account with access to cases, OFW client PII (demographics, contact, next-of-kin, addresses, employment), stakeholders, and scoped audit logs. Broken access control / vertical privilege escalation into staff on a government PII system.
- **Remediation:** Disable public registration (remove the route) or gate it behind admin-created invite tokens; if self-signup must exist, assign a least-privilege pending role with no data access until an ADMIN approves and sets the role; enforce an email-domain allowlist.
- **Validation:** Attempt registration from a non-allowlisted email → account cannot reach any case/client data; add a regression test asserting `register` cannot produce a privileged role.
- **Related:** TECH-002, TECH-003.

---

## HIGH

### TECH-002 — Deactivated / soft-deleted accounts can still authenticate
- **Severity:** High · **Priority:** P0 · **Component:** `LoginRequest`, `CheckUserActive`, `AdminUserController` · **Standards:** ISO 27002 5.18 (access rights), 8.2
- **Status (2026-10-03): RESOLVED** — there is no `LoginOtpController` in the tree; login is email + password via `AuthenticatedSessionController::store` → `LoginRequest::authenticate()` (`app/Http/Controllers/Auth/AuthenticatedSessionController.php:36-44`). Inactive/soft-deleted accounts are rejected after credential verification with the generic failure message (`app/Http/Requests/Auth/LoginRequest.php:68-74`), and live sessions of subsequently deactivated users are force-logged-out by `CheckUserActive` (registered in the web stack at `bootstrap/app.php:66`; logout/invalidate at `app/Http/Middleware/CheckUserActive.php:28-33`). `AdminUserController::destroy` now lives at `app/Http/Controllers/AdminUserController.php:250-279` — the cited `app/Http/Controllers/Admin/AdminUserController.php` path does not exist.
- **Description (historical, commit `b8a7211`):** The login flow (`LoginOtpController::init` L30-36, `verifyOtp` L112-140) looks up the user and calls `Auth::login($user, true)` with **no `is_active` / `is_deleted` check**. `AdminUserController::destroy` (L154-163) only sets `is_active=false`/`is_deleted=true` via `save()`; it never calls `delete()`, so the `SoftDeletes` scope never hides the row.
- **Evidence (historical):** `app/Http/Controllers/LoginOtpController.php:30-36,112-140` (file does not exist); `app/Http/Controllers/Admin/AdminUserController.php:154-163` (path does not exist).
- **Scenario:** An offboarded or compromised user who was "deactivated"/"deleted" can still log in (knows password, can receive OTP). Access revocation is ineffective.
- **Remediation:** Reject `!$user->is_active || $user->is_deleted` before issuing OTP and before `Auth::login`; add a middleware check so live sessions of deactivated users are terminated.
- **Validation:** Deactivate a user; confirm login is blocked at step 1; add a regression test.
- **Related:** TECH-001.

### TECH-003 — MFA/2FA is opt-in and never enforced (not even for ADMIN)
- **Severity:** High · **Priority:** P1 · **Component:** `LoginRequest`, `CheckMfaEnrolled`, `User` · **Standards:** ISO 27002 8.5 (secure authentication), 5.17
- **Status (2026-10-03): RESOLVED** — MFA is no longer purely opt-in. The TOTP challenge runs for enrolled users whose role passes `User::isInMfaEnforcedRole()` (`app/Http/Requests/LoginRequest.php` → correctly `app/Http/Requests/Auth/LoginRequest.php:76`), and that helper (`app/Models/User.php:118-131`) returns true for **any non-empty role when `app()->isProduction()`**, otherwise for `config('mfa.enrollment_enforced_roles')` = `ADMIN,CASE_MANAGER,AGENCY` by default (`config/mfa.php:30-33`, enforcement flag on by default at `:8`). Un-enrolled users in enforced roles are blocked from the app by the `CheckMfaEnrolled` web middleware (`bootstrap/app.php:68`) which redirects them to setup (`app/Http/Middleware/CheckMfaEnrolled.php:78`), and `EnsureMfaSession` (`bootstrap/app.php:67`) guards the pending-challenge session. There is no `MFA_LOGIN_CHALLENGE_ENABLED` flag anywhere in app code, config, routes, resources, or `.env.example` — the challenge is driven by the checks above. No `LoginOtpController` exists (there is no emailed login OTP at all).
- **Description (historical, commit `b8a7211`):** MFA is per-user opt-in (`mfa_enabled_at`); the TOTP challenge runs only `if ($user->mfa_enabled_at !== null)` (`LoginOtpController.php:120`). No policy/middleware requires MFA for any role.
- **Evidence (historical):** `app/Http/Controllers/LoginOtpController.php:120` (file does not exist); `routes/web.php:86-91`.
- **Scenario:** Admins operate with only password + email OTP; an attacker who phishes a password and reads the OTP email (or uses the debug-OTP path, TECH-020) fully authenticates to a privileged console.
- **Remediation:** Enforce MFA enrolment for ADMIN (ideally CASE_MANAGER) via middleware that redirects un-enrolled privileged users to setup and blocks other routes.
- **Validation:** Un-enrolled admin is forced to MFA setup before reaching any admin route.

### TECH-004 — Live session cookie file committed to git (`cookies.txt`)
- **Severity:** High (hygiene) / Medium (current exploitability — localhost scope) · **Priority:** P0 · **Standards:** ISO 27002 5.10, 8.24, 8.4 (source-code/secrets), SOC 2 CC6.1
- **Status (2026-10-03): RESOLVED** — `git ls-files -- cookies.txt` returns nothing and `.gitignore:37` lists `cookies.txt`; the file is no longer tracked.
- **Description (historical, commit `b8a7211`):** `cookies.txt` is git-tracked (`git ls-files`), a Netscape cookie jar containing `XSRF-TOKEN` and `bayanihan-session` (HttpOnly) for host `localhost` (values redacted). Added in commit `7694294`. `.env` itself is correctly untracked.
- **Evidence (historical):** `git ls-files` → `cookies.txt`.
- **Scenario:** Demonstrates a pattern of committing auth artifacts; a production cookie could be committed next. Session replay possible only if domain/secret reused.
- **Remediation:** `git rm --cached cookies.txt`; add to `.gitignore`; rotate `APP_KEY` if any non-local cookie was ever committed; purge from history with `git filter-repo` if the repo was shared.
- **Validation:** `git ls-files | grep cookies.txt` returns nothing; secret-scan CI gate active. *(Secret-scan CI gate remains absent — see TECH-009 residual.)*

### TECH-005 — `trustProxies(at: '*')` enables X-Forwarded-For spoofing of the admin IP allowlist and rate limits
- **Severity:** High · **Priority:** P1 · **Component:** `bootstrap/app.php`, `IpWhitelist` · **Standards:** ISO 27002 8.20/8.22 (network security), 8.2
- **Status (2026-10-03): OPEN (recited — code fixed, deployed configuration not).** The application default is now a CIDR: `bootstrap/app.php:54-61` reads `TRUSTED_PROXIES` (default `10.0.0.0/8`). **However, the production deploy pins `TRUSTED_PROXIES: "*"`** (`.github/workflows/deploy.yml:291`), restoring trust-all proxies on every deployed container. With trust-all, `$request->ip()` honours the client-supplied left-most XFF entry, and `IpWhitelist` (`app/Http/Middleware/IpWhitelist.php:23`) plus app rate limiters (e.g. `app/Providers/AppServiceProvider.php:112-113`) key on that value. nginx real-IP handling is `docker/nginx/conf.d/default.conf:5-9` (`set_real_ip_from` 10/8, 172.16/12, 192.168/16). Residual risk depends on whether the edge proxy overwrites/appends XFF — external evidence, Medium confidence.
- **Description (historical, commit `b8a7211`):** All proxies are trusted (`bootstrap/app.php:39`). `IpWhitelist` (`app/Http/Middleware/IpWhitelist.php:23`) and all rate limiters key on `$request->ip()`, which now honours the client-supplied left-most XFF entry.
- **Evidence (current):** `bootstrap/app.php:54-61`; `.github/workflows/deploy.yml:291`; `app/Http/Middleware/IpWhitelist.php:23`; `app/Providers/AppServiceProvider.php:112-113`; `docker/nginx/conf.d/default.conf:5-9`. *(Historical evidence cited `bootstrap/app.php:39`, `AppServiceProvider.php:81-111`, `default.conf:78` — those line numbers no longer hold those statements.)*
- **Scenario:** An attacker sends `X-Forwarded-For: <trusted-admin-IP>` to bypass the admin IP whitelist, or rotates spoofed IPs to evade per-IP throttling on `/login`, `/track`, and OTP endpoints.
- **Remediation:** Set `TRUSTED_PROXIES` in the deploy workflow to the LB CIDR (or a value the edge proxy enforces) instead of `'*'`; never trust arbitrary upstreams.
- **Validation:** From an untrusted network, a forged XFF does not change `$request->ip()`, and the admin whitelist rejects it. *(Medium confidence on live exploitability — depends on deployed LB topology.)*

### TECH-006 — Audit-log hash-chaining is non-functional (tamper-evidence rests only on the DB trigger)
- **Severity:** High · **Priority:** P1 · **Component:** `AuditObserver`, `AuditLog` · **Standards:** ISO 27002 8.15 (logging integrity), SOC 2 CC7.2
- **Status (2026-10-03): RESOLVED** — a SHA-256 chain is now computed: `AuditLog::boot()` registers a `creating` hook that sets `prev_hash` from the previous row's `chainDigest()` (`app/Models/AuditLog.php:138-144`), and `chainDigest()` hashes the frozen field list with `hash('sha256', …)` (`:151-170`); writers are serialised with `pg_advisory_xact_lock` (`:186`). Verification runs weekly (`audit:verify`, `routes/console.php:56`) with a repair command at `app/Console/Commands/RepairAuditChain.php`. The append-only trigger remains a second layer and is now conditional (`2026_07_08_000002_make_audit_logs_trigger_conditional.php:24,37`).
- **Description (historical, commit `b8a7211`):** `AuditObserver` copies the previous row's `prev_hash` forward but **never computes a hash** of the current record (`app/Observers/AuditObserver.php:68-75`); no `hash()`/`sha256`/`hmac` exists in the audit path. `prev_hash` stays NULL, providing zero tamper detection. The append-only Postgres trigger (P-02 below) is the real, and only, integrity control.
- **Evidence (historical):** `app/Observers/AuditObserver.php:68-75`; migration `2026_07_04_000001_improve_audit_logs_table.php:17` (this line — the `prev_hash` column — still resolves); `app/Models/AuditLog.php:28`.
- **Scenario:** A privileged actor with DB access edits a row; the "chain" cannot detect it. `docs/AUDIT_STRATEGY.md`'s "cryptographically verifiable chain" claim is unmet.
- **Remediation:** Compute `prev_hash = sha256(previous_row_hash || canonical(current_record))` per entity/sequence; add a chain-verification command.
- **Validation:** Verification command detects a manually edited row.

### TECH-007 — Authentication auditing incomplete: failed logins, logouts, and data exports are not recorded
- **Severity:** High · **Priority:** P1 · **Component:** listeners, `AuditObserver`, `DataExportService` · **Standards:** ISO 27002 8.15/8.16, SOC 2 CC7.2, RA 10173 accountability
- **Status (2026-10-03): PARTIAL (recited).** Now recorded: `Login`, `Logout`, `Failed` events are wired (`app/Providers/AppServiceProvider.php:287-289`), with `LogFailedLogin` writing a `LOGIN_FAILED` audit row including attempted email + IP (`app/Listeners/LogFailedLogin.php`); data exports are audited (`app/Http/Controllers/Admin/DataExportController.php:59-71` and `app/Http/Controllers/ReportsController.php:257-271`, both writing `EXPORT`). **Still missing:** no listener for `Lockout` (event fired at `app/Http/Requests/Auth/LoginRequest.php:96` but never recorded); no password-reset audit listener (`app/Listeners/` contains only `EmailEventSubscriber`, `LogFailedLogin`, `LogSuccessfulLogin`, `LogSuccessfulLogout`, `SendSurveyRequest`); no OTP-failure audit write (`app/Services/OtpService.php:41-59` counts attempts in cache only); and no VIEW action in `app/Enums/AuditAction.php:22-28`, so record reads remain unaudited.
- **Description (historical, commit `b8a7211`):** Only `LogSuccessfulLogin` is wired (`AppServiceProvider.php:115`). No listener for `Failed`, `Logout`, `Lockout`, `PasswordReset`, or OTP-failure. `AuditObserver` only handles `created/updated/deleted/restored` — no VIEW or EXPORT auditing; `DataExportService` exports PII with no audit write.
- **Evidence (current):** `app/Providers/AppServiceProvider.php:287-289`; `app/Listeners/LogFailedLogin.php`; `app/Listeners/LogSuccessfulLogin.php`; `app/Observers/AuditObserver.php:14-44`; `app/Enums/AuditAction.php:22-28`; `app/Http/Controllers/Admin/DataExportController.php:59-71`. *(Historical evidence cited `AppServiceProvider.php:115` and `AuditObserver.php:13-43` — superseded.)*
- **Scenario:** Brute-force/credential-stuffing detection is impossible (the query returns nothing); bulk PII export is unlogged — a privacy-accountability gap. Contradicts `docs/AUDIT_STRATEGY.md` §3/§9 which mark these ✅.
- **Remediation:** Add listeners for `Lockout`/password-reset/OTP-failure (with attempted identifier + IP); add a VIEW audit action for sensitive-record reads.
- **Validation:** A lockout and a data export each produce an audit row with actor/IP/timestamp.
- **Related:** TECH-003.

### TECH-008 — Backup relies entirely on provider defaults; no independent/offsite backup and no tested restore
- **Severity:** High · **Priority:** P1 · **Component:** deployment/ops · **Standards:** ISO 27002 8.13 (backup + restore testing), ISO 27001 A.5.29/5.30, SOC 2 A1.2/A1.3
- **Status (2026-10-03): RESOLVED (repo-side); drill execution is external evidence.** The repo now ships `scripts/backup.sh` (pg_dump with timestamped filename), `scripts/restore-test.sh`, and `scripts/drill.ps1`; the procedure is documented at `docs/DEPLOYMENT_GUIDE.md:312-316` (§6 Database Backup), and `docs/management/bcp-dr-plan.md:97-103` schedules monthly restore tests alongside RPO/RTO targets (`:24-30`). *What could not be confirmed from the repo:* evidence that a restore drill has actually been executed — that is operational evidence outside the tree.
- **Description (historical, commit `b8a7211`):** `docs/DEPLOYMENT_GUIDE.md` §4.3/§9 cites Supabase auto-backups (daily, 7-day retention) + PITR (pro plan). No `pg_dump`/`mysqldump`/`spatie/laravel-backup` anywhere; `scripts/` has only an address utility; no restore-test evidence.
- **Evidence (historical):** `docs/DEPLOYMENT_GUIDE.md` §4.3, §9 (those section numbers no longer exist in the document); negative grep of `composer.json`, `scripts/`, `.github/`.
- **Scenario:** Single-vendor dependency; 7-day retention is short for a government case system; a provider account compromise/deletion is unrecoverable; restore is asserted but never tested.
- **Remediation:** Add scheduled, encrypted logical dumps to independent offsite storage; verify storage-bucket versioning; document and perform a restore test with evidence.
- **Validation:** A documented restore drill reproduces the DB + a sample uploaded document.
- **Related:** TECH-019.

### TECH-009 — No PR-triggered CI; no lint / static analysis / type-check / dependency-audit / SAST / secret-scanning
- **Severity:** High · **Priority:** P1 · **Component:** `.github/workflows` · **Standards:** ISO 27002 8.28/8.29 (secure coding, security testing), 8.25, ISO 9001 8.6, SOC 2 CC8.1
- **Status (2026-10-03): PARTIAL (recited).** PR-gated CI exists: `.github/workflows/ci.yml:3-11` triggers on `pull_request` and `push` to `main`; the `lint-and-audit` job runs Pint (`ci.yml:71-72`), `composer audit` (`:74-75`), `npm audit --audit-level=high` (`:77-78`), a Ward security scan with a reviewed baseline and `--fail-on high` (`:85-91`), and blocking `npm run typecheck` = `tsc --noEmit` (`:94`). **Residual:** still no gitleaks/Dependabot/Renovate/CodeQL and no PHPStan/Larastan, ESLint, or Prettier anywhere in `.github/` or the manifests.
- **Description (historical, commit `b8a7211`):** Workflows trigger only on `push: main` and `workflow_dispatch` — no `pull_request` trigger, so tests run **after** merge (which auto-deploys). No Pint/PHPStan/ESLint/Prettier/tsc/`composer audit`/`npm audit`/Dependabot/gitleaks/CodeQL anywhere, despite `docs/TESTING_STRATEGY.md` listing them as "Planned — Every PR".
- **Evidence (historical):** `.github/workflows/deploy-staging.yml:3-7,78-85` (file does not exist); negative grep of `.github/` for the tools; no `pint.json`/`phpstan.neon`/`.eslintrc`.
- **Scenario:** Broken or vulnerable code lands on `main` and deploys before verification; no automated CVE/secret detection.
- **Remediation:** Add the remaining gaps: dedicated secret scanning (gitleaks/CodeQL), dependency update automation (Dependabot/Renovate), and static analysis (Larastan); make the PR jobs required status checks.
- **Validation:** A PR that fails lint/tests/audit is blocked from merge. *(Branch protection itself is external evidence.)*

### TECH-010 — Governance documentation contradicts the implemented code
- **Severity:** High · **Priority:** P1 · **Component:** `docs/*` · **Standards:** ISO 27001/9001 clause 7.5.3 (control of documented information)
- **Status (2026-10-03): PARTIAL (recited).** Corrections confirmed: (a) Spatie-RBAC claims are gone — `docs/PROJECT_RULES.md:34,220` and `docs/SECURITY_REQUIREMENTS.md:63` now state explicitly that RBAC is `CheckRole` over `users.role`, not Spatie; (b) RLS, security headers, and password-reset are documented as implemented (`docs/SECURITY_REQUIREMENTS.md:137-141`, `routes/auth.php:48-53`); (c) TOTP, recovery codes, and Turnstile are documented (`docs/SECURITY_REQUIREMENTS.md:24,38-42`) and there are dedicated `docs/MFA_LOGIN_CHALLENGE.md` / `docs/MFA_ROLLOUT.md`; (d) `docs/DATA_MODEL.md` is v2.2.0 with a migration-derived table inventory (`:3`). **Residual — (e):** stale "2026-05-28" headers remain in five documents: `docs/README.md`, `docs/ACCESSIBILITY_REQUIREMENTS.md`, `docs/IMPLEMENTATION_BACKLOG.md`, `docs/REQUIREMENTS_TRACEABILITY.md`, `docs/UI_PATTERNS.md`.
- **Description (historical, commit `b8a7211`):** Multiple docs misdescribe reality: (a) four docs claim **Spatie RBAC** with `roles`/`permissions` tables that do not exist — actual is a `users.role` column check; (b) RLS, security headers, and password-reset are marked "not done / 404" but are actually implemented; (c) TOTP, recovery codes, and Turnstile are implemented but undocumented; (d) `DATA_MODEL.md` fixes "39 tables" while the schema has grown (feedback_invitations, case categories/issues, active sessions) and dropped others; (e) all docs carry a stale "2026-05-28" header.
- **Evidence (current):** `docs/PROJECT_RULES.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_REQUIREMENTS.md`, `docs/DATA_MODEL.md` (all present); `app/Http/Middleware/CheckRole.php:13`; `composer.json` (no spatie); migration `2026_06_01_000008_enable_row_level_security.php`; `app/Http/Middleware/ContentSecurityPolicy.php`, `app/Http/Middleware/SecurityHeaders.php`; `routes/auth.php`.
- **Scenario:** An auditor sampling docs vs code finds the documentation unreliable — a direct nonconformity against control of documented information, and it erodes trust in every other claim in the doc set.
- **Remediation:** Refresh the five residual stale headers; establish a doc-review-on-change control.
- **Validation:** A sampling review finds doc statements match code for RBAC, RLS, headers, auth, and the table inventory.

---

## MEDIUM

### TECH-011 — TOTP secret and recovery codes stored in plaintext; non-constant-time recovery-code check
- **Severity:** Medium · **Priority:** P1 · **Standards:** ISO 27002 8.24 (cryptography)
- **Status (2026-10-03): RESOLVED.** `mfa_secret` is cast `encrypted` (`app/Models/User.php:81`); recovery codes are stored as HMAC-SHA256 hashes (`app/Services/MfaService.php:18-20`) and compared with `hash_equals` (`:54`) — the `array` cast at `app/Models/User.php:82` now holds hashes, not plaintext codes. Passwords are hashed at rest via `'password' => 'hashed'` (`app/Models/User.php:78`). **UNVERIFIED citation:** the claimed plaintext-password test `tests/Feature/Auth/MfaLoginTest.php:326` does not exist — no `MfaLoginTest` file exists anywhere in `tests/`; current MFA tests are `tests/Feature/MfaControllerTest.php`, `tests/Feature/Security/MfaDisablePasswordTest.php`, and `tests/Feature/Security/RevokeMfaEnrolledSessionsTest.php`.
- **Description (historical, commit `b8a7211`):** `mfa_secret` has no `encrypted` cast; `mfa_recovery_codes` cast as plain `array`; recovery match uses `in_array(...)` (plaintext, not constant-time). `$hidden` only prevents serialization, not at-rest exposure.
- **Evidence (historical):** `app/Models/User.php:69-83`; `app/Http/Controllers/LoginOtpController.php:212` (file does not exist); test confirms plaintext (`MfaLoginTest.php:326`) (file does not exist).
- **Scenario:** A DB read (SQLi, backup leak, insider) yields working TOTP seeds + recovery codes for every user, defeating 2FA.
- **Remediation:** `'mfa_secret' => 'encrypted'`; store recovery codes hashed (`Hash::make`) and verify with `Hash::check`; show plaintext codes only once at generation.
- **Validation:** DB inspection shows ciphertext; recovery-code login still works.

### TECH-012 — No session invalidation after password reset or change
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.5, 5.17
- **Status (2026-10-03): RESOLVED.** `NewPasswordController::store` rotates `remember_token` (`app/Http/Controllers/Auth/NewPasswordController.php:55`) and deletes all of the user's DB sessions (`:72-74`); `PasswordController::update` deletes every other session for the user (`app/Http/Controllers/Auth/PasswordController.php:30-33`).
- **Description (historical, commit `b8a7211`):** `NewPasswordController::store` (L46-56) rotates `remember_token` but does not invalidate DB sessions; `PasswordController::update` (L16-28) has no `Auth::logoutOtherDevices()`.
- **Evidence (historical):** `app/Http/Controllers/Auth/NewPasswordController.php:46-56`; `app/Http/Controllers/Auth/PasswordController.php:16-28` (line numbers superseded by the current implementations cited in the Status line).
- **Scenario:** After a reset (typical compromise response), a pre-existing attacker session (120-min lifetime, always-on "remember") remains valid.
- **Remediation:** Call `Auth::logoutOtherDevices($password)` and delete other `sessions` rows on password change/reset.
- **Validation:** Second session is invalidated after reset.

### TECH-013 — CSP allows `'unsafe-inline'` and `'unsafe-eval'` in production `script-src`
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.26, 8.9
- **Status (2026-10-03): RESOLVED.** The enforced production/staging policy is nonce-based: `script-src 'nonce-{…}' 'strict-dynamic' https://challenges.cloudflare.com` with `script-src-attr 'none'`, `object-src 'none'`, `base-uri 'none'`, `frame-ancestors 'none'` (`app/Http/Middleware/ContentSecurityPolicy.php:79-90`); `'unsafe-eval'` is gone from production and the Report-Only header is removed (`:46`). Documented residual tradeoff: `style-src` keeps `'unsafe-inline'` for Tailwind JIT (`:82`).
- **Description (historical, commit `b8a7211`):** `getProdPolicy` sets `script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com` (`app/Http/Middleware/ContentSecurityPolicy.php:75`), removing CSP's XSS-mitigation value.
- **Evidence (historical):** `app/Http/Middleware/ContentSecurityPolicy.php:75`.
- **Remediation:** Remove `unsafe-inline`/`unsafe-eval`; adopt nonce/hash-based CSP for the Inertia bootstrap.
- **Validation:** Strict CSP with no console violations; inline `<script>` injection blocked.

### TECH-014 — PII stored in plaintext at rest (no application-layer encryption)
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.24, 5.34 (PII protection), RA 10173/DPTM
- **Status (2026-10-03): PARTIAL (recited).** Application-layer encryption now covers the targeted high-sensitivity fields: `EncryptedString` casts on client addresses (`app/Models/ClientAddress.php:32`), employment history (`app/Models/ClientEmployment.php:38-42`), and next-of-kin (`app/Models/NextOfKin.php:42-44`), plus `EncryptedDate` for date of birth (`app/Models/Client.php:40`), applied by migration `2026_07_09_000001_encrypt_pii_fields.php` and documented at `docs/SECURITY_REQUIREMENTS.md:128-129`. **Residual:** core `clients` columns such as `email` and `contact_number` have no `encrypted` cast (`app/Models/Client.php:39-42`), and a key-management approach beyond `APP_KEY` is still outstanding (tracked as D60-2 in `iso-remediation-roadmap.md`). The cited `docs/SECURITY_REQUIREMENTS.md:140` no longer acknowledges plaintext key storage — that line now documents RLS session variables.
- **Description (historical, commit `b8a7211`):** Client PII, next-of-kin, and employment data have no `encrypted` casts; reliance is on Postgres RLS + disk security. `docs/SECURITY_REQUIREMENTS.md:140` acknowledges plaintext key storage as "documented technical debt".
- **Evidence (historical):** `docs/SECURITY_REQUIREMENTS.md:140`.
- **Remediation:** Apply `encrypted` casts or column-level encryption to the most sensitive PII fields; define a key-management approach.
- **Validation:** DB inspection shows ciphertext for targeted fields; queries still function.

### TECH-015 — Audit retention/prune conflicts with the append-only trigger; retention policy unenforced
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.15, 5.33; RA 10173 (retention/disposal)
- **Status (2026-10-03): RESOLVED (runtime) + doc-drift note.** Prune is now archive-gated and chain-aware: `audit:prune` only deletes rows whose period has a verified archive bundle (`app/Console/Commands/PruneAuditLogs.php:19,60,121`), the append-only trigger is conditional (`2026_07_08_000002_make_audit_logs_trigger_conditional.php:24,37`), and `audit:archive` → `audit:prune --force` → `audit:verify` are scheduled (`routes/console.php:52-56`). Retention is a configurable hot window (`config/audit.php:40`, default 365 days). **Doc drift (tracked under TECH-010):** `docs/AUDIT_STRATEGY.md:172-177` still describes retention as "No automatic deletion", which understates the scheduled archive+prune.
- **Description (historical, commit `b8a7211`):** Monthly `audit:prune --force` calls `forceDelete()` (`PruneAuditLogs.php:46`) but the `BEFORE UPDATE OR DELETE` trigger `RAISE EXCEPTION`s, so prune fails at runtime; default flat 365-day retention also contradicts the tiered policy in `AUDIT_STRATEGY.md` §6 (which admits it is "not yet implemented").
- **Evidence (historical):** `app/Console/Commands/PruneAuditLogs.php:46`.
- **Remediation:** Choose an archival/partition strategy or a privileged maintenance path that is itself audited; align retention tiers with policy (LEGAL-006).
- **Validation:** Prune runs without error and honours tiered retention.

### TECH-016 — No centralized logging, error tracking, or alerting
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.16, SOC 2 CC7.2
- **Status (2026-10-03): PARTIAL (recited).** Error tracking now exists: `sentry/sentry-laravel` (`composer.json:25`), integrated at `bootstrap/app.php:191`, configured via `config/sentry.php` with DSN keys in `.env.example:39-42`, browser SDK `@sentry/react` (`package.json:37`), and scheduler failures reported to Sentry (`routes/console.php:22-38`); production logs to stderr (`deploy.yml:259`, `LOG_CHANNEL: "stderr"`). **Residual:** local logging is still the file `stack` channel (`config/logging.php:21`, `.env.example:34`); `slack`/`papertrail` channels remain defined but env-gated and unset (`config/logging.php:76-85`) — and no Slack integration remains in any workflow either; container logs remain `json-file` with `max-size: 10m` / `max-file: 3` (~30 MB) (`docker-compose.yml:36-40`); no alerting beyond Sentry.
- **Description (historical, commit `b8a7211`):** Default log stack → single file; `slack`/`papertrail` channels env-gated and unset; no Sentry/Bugsnag/Telescope/Horizon. Container logs use `json-file` with 10m×3 rotation (~30 MB). Slack is CI-only, not app alerting.
- **Evidence (current):** `config/logging.php:21,76-85`; `bootstrap/app.php:191`; `routes/console.php:22-38`; `docker-compose.yml:36-40`.
- **Remediation:** Route logs to a durable centralized channel; configure alerting rules (beyond Sentry defaults) for failed-job and security events.
- **Validation:** A forced error and a failed job produce an alert.

### TECH-017 — External HTTP calls have no timeout/retry (availability risk)
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.6 (capacity), ISO 20000-1 8.6.1
- **Status (2026-10-03): RESOLVED.** Explicit timeouts are now set on the outbound paths: Turnstile `->timeout(5)->connectTimeout(3)` (`app/Http/Middleware/VerifyTurnstile.php:31-33`, session variant `:48-49`); chatbot AI calls are bounded (`app/Services/Chatbot/ChatbotConversationService.php:51-57` + `config/ai-chatbot.php:10`); the Cloudinary SDK timeout is set to 30 s (`app/Providers/AppServiceProvider.php:306-309`); reports document the edge read timeout (`config/reports.php:12,27`).
- **Description (historical, commit `b8a7211`):** `VerifyTurnstile` (`:26`), Cloudinary SDK, and AI calls (`ChatbotController.php:52`, `ReportsController.php:172`) set no `timeout()`/`retry()`. With php.ini `max_execution_time=60`, a slow third party (Turnstile on the login path) can exhaust FPM workers.
- **Evidence (historical):** `app/Http/Controllers/ChatbotController.php:52` (the file is now a 16-line delegator and holds no HTTP call); `app/Http/Controllers/ReportsController.php:172` (line now holds unrelated code).
- **Remediation:** Set explicit `timeout()`/`connectTimeout()`/`retry()` on all outbound HTTP; move non-critical calls to queued jobs; define fail-open/closed per endpoint.
- **Validation:** Simulated slow upstream does not stall the login path.

### TECH-018 — No rollback strategy; deploy step masks failures (`continue-on-error: true`)
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 20000-1 8.5.1 (release/deployment), ISO 9001 8.6, ISO 27002 8.32
- **Status (2026-10-03): RESOLVED.** The current deploy is health-gated with a pre-migration snapshot: no `continue-on-error` exists in `deploy.yml` (the only remaining occurrence in `.github/` is `build-image.yml:211`); the container service health-check probes `/up` (`deploy.yml:310-316`); the job health-gates `/up` (`deploy.yml:382-392`) and then applies a deep readiness gate (`:394-406`); a database snapshot is taken before migrations run (`deploy.yml:122-132`; migrations run inside the container, `RUN_MIGRATIONS=true` at `:288`); rollback procedure documented at `docs/DEPLOYMENT_GUIDE.md` §8 (`:346`).
- **Description (historical, commit `b8a7211`):** The deploy step is `continue-on-error: true` with only `sleep 30` after; no health-gate, no rollback, migrations run `--force` with no pre-migration snapshot.
- **Evidence (historical):** `.github/workflows/deploy-staging.yml:96-106` (file does not exist); `docs/DEPLOYMENT_GUIDE.md` §9 (section does not exist).
- **Remediation:** Remove `continue-on-error`; add a health-gated post-deploy probe that fails the job; add a pre-deploy DB snapshot and documented rollback.
- **Validation:** A deliberately failing deploy fails the pipeline and triggers rollback.

### TECH-019 — No RPO/RTO or DR/BCP documentation
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27001 A.5.29/5.30, ISO 20000-1 8.7.2, SOC 2 A1.2/A1.3
- **Status (2026-10-03): RESOLVED.** `docs/management/bcp-dr-plan.md` (2026-07-09) provides a BIA, quantified objectives — RTO 4 h, RPO 24 h, MTD 8 h (`:24-30`) — disaster scenarios, a communication plan, and a DR test schedule (`:97-103`). The NFR-PERF-007 99% availability target still exists and is still marked provider-dependent (`docs/REQUIREMENTS_TRACEABILITY.md:183`).
- **Description (historical, commit `b8a7211`):** No RPO/RTO figures anywhere; `DEPLOYMENT_GUIDE.md` §9 lists backup assets but no recovery objectives, no BIA, no DR site, no BCP doc. NFR-PERF-007 states a 99% availability target (provider-dependent, unmeasured).
- **Remediation:** Author a DR/BCP with quantified RPO/RTO and a tested recovery runbook.
- **Related:** TECH-008.

### TECH-020 — Debug OTP returned in HTTP response and partial OTP logged
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.5, 8.31 (test/prod separation)
- **Status (2026-10-03): RESOLVED (for the cited login-OTP path) with a scope note.** There is no `LoginOtpController` and no emailed login OTP, so the cited debug path is gone; `app/Services/OtpService.php` contains no `Log::`/`logger()` calls — OTP digits are no longer written to logs. The remaining debug-OTP surface is a single system setting (`app/Http/Controllers/SystemSettingsController.php:16,26,31-32`) consumed only outside production: `EmailChangeController.php:65` (local/testing), `IntakeController.php:135-137,142` (local/testing), `TrackController.php:56` (local/testing), and `AdminUserController.php:201` (local/**staging**/testing — staging is still included in this one gate). Cited `docs/SECURITY_REQUIREMENTS.md:132` no longer discusses debug OTP (it now heads the Data Masking section).
- **Description (historical, commit `b8a7211`):** `LoginOtpController::init`/`resendOtp` return `debug_otp` when `debug_otp_enabled` and env ∈ local/staging/testing (L51, L88); `OtpService::verify` logs the first 2 digits (`OtpService.php:47-52`). `docs/SECURITY_REQUIREMENTS.md:132` notes a related debug-OTP exposure via Inertia meta-props.
- **Evidence (historical):** `app/Http/Controllers/LoginOtpController.php` (file does not exist); `OtpService.php:47-52` (no digit logging at those lines).
- **Scenario:** If toggled on in shared staging, OTPs are handed to the client, bypassing the email factor.
- **Remediation:** Keep debug OTP out of any shared non-local environment (drop `'staging'` from `AdminUserController.php:201`); keep OTP fragments out of logs.
- **Validation:** Staging never returns `debug_otp`; logs contain no OTP digits.

### TECH-021 — CI runtime versions below manifest requirements
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 9001 8.5.1, ISO 27002 8.31
- **Status (2026-10-03): RESOLVED (original claim doubly wrong).** `composer.json` requires `"php": ">=8.4.1 <9.0"` (not `^8.3`), and CI sets up PHP **8.4** (`.github/workflows/ci.yml:38,124`) and Node **24** (`ci.yml:45,131,228`) — matching the manifest (`vite ^8`, `@types/node ^26` need Node 20+). The original claim cited `deploy-staging.yml:39`, a file that does not exist in the tree.
- **Description (historical, commit `b8a7211`):** `composer.json` requires PHP `^8.3` but CI sets up PHP 8.2 (`deploy-staging.yml:39`); Vite `^8`/`@types/node ^26` need Node 20+ but CI uses Node 18 (`:46`). CI does not test the declared runtime.
- **Evidence (historical):** `.github/workflows/deploy-staging.yml:39,46` (file does not exist).
- **Remediation:** Bump CI to PHP 8.3 and Node 20/22.
- **Validation:** CI matrix matches `composer.json`/`package.json` requirements.

### TECH-022 — Dependency conflicts and dual lockfiles
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.28, ISO 9001 8.4
- **Status (2026-10-03): RESOLVED.** Single package manager and lockfile: `package-lock.json` only — no `bun.lock` in the tree (CI uses npm). Type/runtime alignment: `@types/react ^18.2.79` with React `^18.2.0` (`package.json:21,29`). Single Tailwind major: `tailwindcss ^3.2.1` (`package.json:31`) with no `@tailwindcss/vite` dependency.
- **Description (historical, commit `b8a7211`):** Both `bun.lock` and `package-lock.json` present (drift risk; CI uses `npm ci`). `@types/react ^19` vs runtime React 18. `tailwindcss ^3.2` declared alongside `@tailwindcss/vite ^4` (bun resolves Tailwind 4.3.0) — contradictory major versions.
- **Remediation:** Single package manager + single lockfile; align `@types/react` to 18; consolidate Tailwind to one major with matching config.
- **Validation:** Only one lockfile is tracked; `npm ci` succeeds.

### TECH-023 — Testing-strategy documentation materially false
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 9001/27001 7.5.3, ISO 9001 8.6
- **Status (2026-10-03): PARTIAL (recited).** The core statistics are corrected: `docs/TESTING_STRATEGY.md` is v2.0.0 updated 2026-07-11 (`:6`), documents PHPUnit 12 on PostgreSQL (`:12-13`, `:41`), and the suite now comprises 234 `*Test.php` files. **Residual:** the same document still documents an E2E harness that does not exist — `playwright.config.ts` (`:15`) and `npm run test:e2e` (`:31-32`, `:241`) are referenced, but there is no Playwright config anywhere in the repo and no `test:e2e` script in `package.json` (see TECH-036).
- **Description (historical, commit `b8a7211`):** `docs/TESTING_STRATEGY.md` states "29 tests", "unit tests not yet written", SQLite in-memory test DB, "PHPUnit 11.x". Actual: 116 Feature + 13 Unit files, Postgres test DB (`phpunit.xml:26-27`), PHPUnit `^12.5`, ~984 passing. Stale "8 pre-existing failures … Do NOT fix" guidance could suppress real regressions.
- **Remediation:** Remove or implement the Playwright/E2E references; version-bump on each suite change.
- **Related:** TECH-010, TECH-036.

### TECH-024 — User enumeration via password-reset responses
- **Severity:** Medium · **Priority:** P2 · **Standards:** ISO 27002 8.5
- **Status (2026-10-03): RESOLVED.** `PasswordResetLinkController::store` ignores the `Password::sendResetLink` result and always returns the same `RESET_LINK_SENT` status (`app/Http/Controllers/Auth/PasswordResetLinkController.php:33-40`), so the response no longer distinguishes registered from unregistered emails.
- **Description (historical, commit `b8a7211`):** `PasswordResetLinkController::store` (L39-49) returns success only on `RESET_LINK_SENT` and otherwise throws with the translated status, revealing whether an email is registered. (Login itself returns generic errors — good.)
- **Remediation:** Always return the same generic "if that email exists…" response.
- **Validation:** A request for an unknown address returns the identical response as for a known one.

---

## LOW

### TECH-025 — Notification IDOR: mark-as-read not scoped to owner
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): RESOLVED (with residual branch).** The sole caller passes the owner: `NotificationController::markAsRead` calls `markAsRead($id, 'user', $request->user())` (`app/Http/Controllers/NotificationController.php:62`), and `NotificationService::markAsRead` then scopes the update through `$notifiable->notifications()` (`app/Services/NotificationService.php:96-117`). Residual: an unscoped fallback branch remains for backwards compatibility when `$notifiable` is null (`:118-124`) — dead for current call sites but fragile.

### TECH-026 — RLS session variables set via string interpolation
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): RESOLVED.** `SetPostgresSession` now sets both variables with bound parameters via `SELECT set_config(?, ?, ?)` (`app/Http/Middleware/SetPostgresSession.php:31-44`); the interpolated `SET SESSION` form is gone. Original note: `SetPostgresSession.php:35-36` interpolated `$user->id`/`$user->role` into `SET SESSION`.

### TECH-027 — Health-check path mismatch (`/health` vs `/up`)
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): HISTORICAL / RESOLVED.** The cited `reset-staging-data.yml` does not exist (the staging workflow set was deleted; only `ci.yml`, `build-image.yml`, `deploy.yml`, `deploy-production.yml` remain) and no workflow references `/health`. `/up` is the single health endpoint (`bootstrap/app.php:41`), used by the deploy gate (`deploy.yml:310-316,382-392`) and by the local compose health check (`docker-compose.yml:94-95`). Original note: App exposes `/up` (`bootstrap/app.php`), `reset-staging-data.yml:32` curled `/health`, which had no route.

### TECH-028 — Conflicting/duplicated security headers (nginx vs Laravel)
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): RESOLVED.** nginx no longer emits `X-Frame-Options` or `X-XSS-Protection` — it sets only `X-Content-Type-Options`, `Referrer-Policy`, and `Permissions-Policy` (`docker/nginx/conf.d/default.conf:74-76`); Laravel is the single source, setting `X-Frame-Options: DENY` (`app/Http/Middleware/SecurityHeaders.php:31`). Original note: nginx set `SAMEORIGIN` (`default.conf:23`) while Laravel set `DENY` (`SecurityHeaders.php:27`).

### TECH-029 — Upload validation divergence (FormRequest vs config)
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): RESOLVED.** The FormRequest now reads config: `'max:'.config('file-uploads.max_size', 20480)` (`app/Http/Requests/StoreCaseDocumentRequest.php:27`), and `config/file-uploads.php:19` defaults to 20480 KB (20 MB), overridable via `FILE_UPLOAD_MAX_SIZE`. The former 20 MB-vs-10 MB divergence is gone. Real gate remains `StorageService::validate()` (config-driven + finfo + ClamAV).

### TECH-030 — Staging credentials broadcast in CI/Slack
- **Severity:** Low (staging-scoped) · **Priority:** P3 · **Status (2026-10-03): HISTORICAL.** Both cited workflows — `.github/workflows/deploy-staging.yml` and `.github/workflows/reset-staging-data.yml` — no longer exist. The four remaining workflows contain no Slack posts and no fixed test-logins/passwords; the only credential-looking values are the ephemeral CI database passwords for the Postgres service container (`ci.yml:109,196,206,216`). Original note: `deploy-staging.yml:121-122` and `reset-staging-data.yml:42` posted fixed test logins + a short numeric password to Slack.

### TECH-031 — Chatbot forwards raw user input to the LLM (prompt injection)
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): OPEN (recited).** Input is still embedded in the agent prompt: `ChatbotMessageRequest` caps message/history at 1000 chars (`app/Http/Requests/ChatbotMessageRequest.php:17-18`), `ChatbotController` is now a 16-line delegator (`app/Http/Controllers/ChatbotController.php:12-14`) to `ChatbotConversationService::reply` (`app/Services/Chatbot/ChatbotConversationService.php:22`), which prompts a static guide with a bounded timeout. The bot remains unprivileged (no tools/data beyond the cached helpdesk corpus), and replies render through `react-markdown` + `rehype-sanitize` (`resources/js/Components/Helpdesk/MarkdownRenderer.jsx:2` — `rehype-raw` is not used). The original citation `ChatbotController.php:48-54` no longer resolves (file is 16 lines). Fix: output caps + refusal guard; keep bot unprivileged.

### TECH-032 — Container app health-check is trivial
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): RESOLVED.** The compose health check now probes the real endpoint: `curl -sf http://nginx/up || exit 1` (`docker-compose.yml:94-95`), and nginx gates on it, so a broken app stops receiving traffic. Original note: `docker-compose.yml:86-91` used `php -r "echo 'ok';"`.

### TECH-033 — No `declare(strict_types=1)`; weak frontend typing
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): OPEN (recited; partly improved).** Still true: 0 of 642 PHP files under `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/` declare `strict_types`; 269 `.jsx` vs 10 `.tsx`; `tsconfig.json` keeps `skipLibCheck: true` (`:15`) and `strictNullChecks: false` (`:18`) with only incremental strict flags. **Improved:** `tsc --noEmit` now runs in CI (`ci.yml:94` via `npm run typecheck`, `package.json:11`) — the "no `tsc --noEmit` step" part is resolved. Original note: 0/166 PHP files, 190 `.jsx` vs 6 `.tsx`. Fix: adopt strict types incrementally; enable `strict`.

### TECH-034 — RLS enable-migration soft-fails on non-Postgres
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): RESOLVED.** The migration now fails closed: both `up()` and `down()` catch blocks log the error and **rethrow** (`database/migrations/2026_06_01_000008_enable_row_level_security.php:113-119,162-168`), so a non-Postgres environment aborts the migration instead of silently running without row-level isolation. Original note: the enable-RLS migration was wrapped in try/catch and only warned on non-PG.

### TECH-035 — Queue worker & scheduler absent in the PaaS single-container image
- **Severity:** Low–Medium (Medium confidence) · **Priority:** P2 · **Status (2026-10-03): RESOLVED.** The shipped image starts both: `docker/supervisord.conf:33-34` (`queue-worker`, gated by `RUN_QUEUE_WORKER`) and `:56-57` (`scheduler`, gated by `RUN_SCHEDULER`), and the deploy sets both to `"true"` (`.github/workflows/deploy.yml:289-290`), so queued emails/notifications and scheduled jobs run in the default deployment. Any platform-level deviation from the shipped image remains external evidence.

### TECH-036 — Frontend and end-to-end critical-path test coverage largely absent
- **Severity:** Low · **Priority:** P3 · **Status (2026-10-03): OPEN (recited).** Backend coverage expanded to 234 `*Test.php` files (was 129) and the frontend now has 51 Vitest test files (was ~2-3). **E2E remains absent:** no `playwright.config.ts` anywhere and no `test:e2e` script in `package.json`, although `docs/TESTING_STRATEGY.md:15,31-32,241` documents a Playwright harness and the six critical E2E paths — the documented "login→OTP→dashboard" path additionally predates the current password→MFA login flow. Fix: implement documented critical-path E2E + expand component coverage.

---

## Positive controls (implemented effectively)

| ID | Control | Evidence | Why effective | Tests |
|---|---|---|---|---|
| P-01 | File-upload pipeline | `app/Services/StorageService.php` (ClamAV scan, UUID filenames, finfo MIME sniff, extension+size allowlist); commit `b8a7211` tightened types across FormRequest/config/map/React | Defense-in-depth; content-based, not extension-trust | `CaseDocumentUploadValidationTest`, `StorageServiceMimeValidationTest` |
| P-02 | Append-only audit table (DB trigger) | migration `2026_07_04_000001:34-48` (`BEFORE UPDATE OR DELETE … RAISE EXCEPTION` at `:39,:46`); INSERT-only model | Real tamper-evidence at the DB layer (compensates for TECH-006) | `RLS`/audit integrity tests |
| P-03 | Sensitive-data redaction in audit values | `app/Models/AuditLog.php:57-119` (config-driven recursive key redaction `:57`, CR/LF stripping `:117-119`) | Prevents secret leakage and log injection | — |
| P-04 | Object-level authorization in sensitive controllers | `ReferralController::authorizeReferralAccess` (`:626`), `ClientController::authorizeClientAccess` (`:400`, 404-not-403), `CaseDocumentController::authorizeAccess` (`:163`) | Correct horizontal-access control; existence non-disclosure | `AuthorizationGapTest`, `ReferralAuthorizationTest`, `ClientControllerAuthTest` |
| P-05 | Session cookie hardening + regeneration | `config/session.php` (`encrypt`/`secure`/`http_only`/`same_site=lax`, `:50,:172,:185,:202`); regeneration on login, invalidation on logout/delete | Strong session security | `AuthenticationTest`, `MfaControllerTest`, `RevokeMfaEnrolledSessionsTest` |
| P-06 | Multi-factor authentication by policy | Password login with per-IP throttling (`LoginRequest.php:90-106`); TOTP challenge for enrolled users in enforced roles — all roles in production (`LoginRequest.php:76`; `User.php:118-131`; `config/mfa.php:30-33`); enrollment enforced by `CheckMfaEnrolled` (`bootstrap/app.php:68`); email OTP (CSPRNG `random_int`, 5-attempt lockout) for email-change/intake/tracking flows (`OtpService.php:11,17`) | Raises the bar beyond a single password; no emailed login OTP exists (password → MFA challenge) | `AuthenticationTest`, `MfaControllerTest`, `OtpServiceTest` |
| P-07 | Comprehensive rate limiting | 27 named limiters (`AppServiceProvider.php:112-283`) + nginx `limit_req_zone` (`default.conf:50,112`) | App + edge defense-in-depth | `RateLimit` security tests |
| P-08 | CSRF fully enforced (no exceptions) | no `validateCsrfTokens(except:)` anywhere; origin-only CSRF validation via `preventRequestForgery(originOnly: true)` (`bootstrap/app.php:49-52`) | All state-changing web routes protected | — |
| P-09 | dompdf hardened | `config/dompdf.php` (`enable_remote=false` `:270`, `enable_php=false` `:236`, `chroot` `:81`) | Blocks SSRF/LFI via PDF | — |
| P-10 | CSV formula-injection neutralized | `DataExportService.php:609` (`writeSafeCell`) | Prevents spreadsheet formula injection | — |
| P-11 | Parameterized SQL throughout | raw SQL is static or bound | No SQLi found | — |
| P-12 | Mass-assignment discipline | explicit `$fillable`; `ProfileUpdateRequest` omits `role`/`agcy_id`/`is_active` | Blocks privilege escalation via profile update | — |
| P-13 | npm supply-chain hardening | `.npmrc` `ignore-scripts=true`, `audit=true`; `trustedDependencies` allowlist (`package.json:58-61`) | Blocks malicious postinstall scripts | — |
| P-14 | Container hardening | compose `no-new-privileges` (`:73,:135,:192`), `cap_drop: ALL` (`:74,:136,:193`), resource limits | Reduces blast radius | — |
| P-15 | Requirements traceability | `docs/REQUIREMENTS_TRACEABILITY.md` (354 reqs → impl → verification, total at `:698`) | Strong ISO 9001 §8.3 design evidence | — |

---

*Confidence: High for all file-cited findings; Medium for inferred runtime/infra behaviour (TECH-005 live exploitability depends on edge XFF handling, TECH-008 drill-execution evidence is operational/external). Original assessment performed read-only at commit `b8a7211`; no files were modified during that assessment. Citations and dispositions re-verified against the working tree on 2026-10-03 (v1.2.0) — see Status lines and changelog.*
