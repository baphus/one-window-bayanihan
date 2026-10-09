# System and Service Profile — One Window Bayanihan

| Field | Value |
|---|---|
| Document | System & Service Profile |
| Version | v1.0.0 |
| Date | 2026-07-08 |
| Status | Draft for review (REVIEW tier — see executive summary triage) |
| Prepared by | Automated alignment assessment (Claude Code), evidence-based |
| Assumed standard editions | ISO/IEC 27001:2022, ISO/IEC 27002:2022, ISO/IEC 20000-1:2018, ISO 9001:2015 |
| Basis | Read-only static inspection of the repository at commit `b8a7211` (branch `main`), with every code citation re-verified against the working tree on 2026-10-02 (see changelog v1.1.0). No runtime observation. |

> **Limitation:** This profile is derived solely from repository evidence. Deployed production topology (the hosting provider's container-service and database configuration) is outside the repository and is marked *Medium confidence* where inferred.

---

## Changelog

| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial system and service profile. |
| v1.1.0 | 2026-10-02 | Factual corrections | Re-verified every code citation against the working tree: auth flow (§7), roles (§2), deployment workflows (§4), background jobs/schedule (§10), health endpoints (§12), surveys replacing tokenized feedback links, and route/middleware line references. |

---

## 1. Project purpose and business processes

An inter-agency **"one window" case-management and referral system for Overseas Filipino Workers (OFWs)** and their families, operated by the Philippine **Department of Migrant Workers (DMW) Region VII**, with partner agencies OWWA, DMW, TESDA, DSWD, and DOLE.
Evidence: `Dockerfile:25-28`, `README.md`, `srs_extracted.txt`, `.env.example` (`APP_OWNER`).

Business processes supported:
- Case intake and management (draft → publish → archive lifecycle) — `routes/web.php:122-145`.
- Client (OFW) profile management: addresses, employment history, next-of-kin — `app/Http/Controllers/ClientController.php`, migration `..._000002_*`.
- Inter-agency referrals with status workflow, milestones, comments, versioned attachments, compliance-requirement fulfilment — `routes/web.php:76-107`, migration `..._000003_*`.
- Public case tracking via tracker number + email OTP — `TrackController`, `routes/web.php:383-394`.
- Feedback / SERVQUAL surveys via tokenized public links — `PublicSurveyController`, `routes/web.php:52-58`; agency survey forms and response dashboards — `SurveyFormController` (`routes/web.php:211-220`), `SurveyResponseController` (`routes/web.php:222-226`). (The former `PublicFeedbackController` / `/feedback/{token}` flow no longer exists; `feedback` tables remain in the schema but no feedback controller or model is present.)
- Reporting and analytics, including Excel/PDF export — `ReportsController`.
- System administration (users, agencies, services, reference data, logs, sessions, security, maintenance, data export) — `routes/web.php:245-315`.
- AI helpdesk chatbot "Bayani" — `ChatbotController` (`routes/web.php:440-442`).

## 2. User roles and privileged roles

Roles are modelled as a **single string column `users.role` (VARCHAR(50))** — not an enum table, not `spatie/laravel-permission`. There are exactly **four** role values: `CASE_MANAGER`, `AGENCY`, `ADMIN`, `OFW`.
Evidence: `database/migrations/0001_01_01_000000_create_framework_tables.php:16`; `app/Models/User.php:98-131`.

| Role | Scope | Enforcement |
|---|---|---|
| `ADMIN` (privileged) | Exclusive `/admin/*` and `/admin/system/*`; bypasses all Postgres RLS | `role:ADMIN` + `ip.whitelist` middleware (`routes/web.php:245`) |
| `CASE_MANAGER` | Owns cases, clients, documents, scoped audit logs | `role` middleware + in-controller ownership checks |
| `AGENCY` | Scoped to referrals for their `agcy_id` | `role` middleware + `authorizeReferralAccess` |
| `OFW` (end user) | Own portal `/my-cases/*` (own case milestones, notifications, profile) | `role:OFW` middleware (`routes/web.php:445-452`) |

OFW accounts are created by the citizen themselves through the public intake or tracking flows (`app/Http/Controllers/IntakeRegistrationController.php:64-68`, `app/Http/Controllers/TrackRegistrationController.php:78-82` — both `'role' => 'OFW'`), then auto-logged-in to the OFW portal. Staff accounts are invite-only (`RegisterViaInviteController`, `routes/auth.php:41-46`); there is no self-service staff registration. The public otherwise interacts anonymously via token/OTP flows. Enforcement middleware `CheckRole` (`app/Http/Middleware/CheckRole.php:13`) is default-deny (`abort(403)`).

> **Documentation contradiction (resolved):** an earlier revision of this profile recorded that `docs/PROJECT_RULES.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_REQUIREMENTS.md`, and `docs/DATA_MODEL.md` described Spatie `laravel-permission` with `roles`/`permissions` tables that do not exist. Those governance docs have since been reconciled to the code: `docs/PROJECT_RULES_v2.1.0.md:34` and `docs/SECURITY_REQUIREMENTS_v2.2.0.md:71` now state the `users.role`/`CheckRole` mechanism explicitly (finding **TECH-010**, partially remediated).

## 3. Technology stack

- **Backend:** PHP `>=8.4.1 <9.0` (Docker runtime `php:8.4-fpm`); Laravel `^13.7`; Inertia Laravel `^2.0`; Sanctum `^4.0` (installed, no token-issuing code path found); Ziggy `^2.0`. Evidence: `composer.json` `require` block.
- **Key libraries:** `barryvdh/laravel-dompdf ^3.1`, `phpoffice/phpspreadsheet 5.9.0`, `cloudinary/cloudinary_php 3.1.3`, `league/flysystem-aws-s3-v3 3.34.0`, `openai-php/client ^0.19.2` + `laravel/ai *`, `pragmarx/google2fa-laravel 3.0.1`, `sentry/sentry-laravel ^4.8`, `resend/resend-php ^1.6`.
- **Frontend:** React 18 + `@inertiajs/react ^2.0`, Vite `^8`, Tailwind `^3.2` (with `@tailwindcss/forms ^0.5.3` — single Tailwind major, no v3/v4 conflict), TanStack Query, Chart.js, react-markdown + rehype-sanitize, zod, `@sentry/react`.
- **Testing:** PHPUnit `^12.5`, Vitest `^4` + Testing Library; `npm run typecheck` (`tsc --noEmit`) available (no Playwright/E2E runner in `package.json`).
- **Package managers:** npm only — `package-lock.json`; `.npmrc` sets `ignore-scripts=true` (no `bun.lock` in the repository).

## 4. Deployment model

- **CI (no auto-deploy):** `.github/workflows/ci.yml` runs on pull requests **and** pushes to `main` (`lint-and-audit`, `backend-tests` against a Postgres 17 service container, `frontend-tests`). CI configures PHP 8.4 and Node 24. Evidence: `ci.yml:3-9,38,45,103-116,124,130,228`.
- **Release:** `.github/workflows/build-image.yml` is a manual image build/push (tagged with the commit SHA); `.github/workflows/deploy-production.yml` is manual-only and requires typing the confirmation phrase `PRODUCTION` (`deploy-production.yml:36-42`) before calling the reusable `deploy.yml`, which runs migrations inside the container at start and health-gates `/up` (`deploy.yml:382-392`). The single platform-specific step (a provider CLI call) lives inside `deploy.yml`; credentials are OIDC-federated, not static repository keys.
- **Single-container image** (`Dockerfile`, multi-stage `node:22-bookworm` build → `php:8.4-fpm` runtime bundling Nginx + Supervisor), health-check `curl /up` (`Dockerfile:151`). Supervisor runs **php-fpm + nginx + queue worker + scheduler** — the worker and scheduler programs are env-gated (`RUN_QUEUE_WORKER` / `RUN_SCHEDULER`, both default `true`) in `docker/supervisord.conf`.
- **docker-compose.yml** (fuller local/self-host topology): separate `nginx`, `app`, `queue` (`queue:listen --tries=3 --timeout=90`, `docker-compose.yml:156`), `scheduler` (`schedule:work`, `:213`), `migrate`, `db` (postgres:15-alpine, `:268`), `redis`. Hardened with `no-new-privileges`, `cap_drop: ALL`, CPU/memory limits, health-checks (`docker-compose.yml:73-74,95`).
- **Scheduled staging data reset:** no such workflow exists — `.github/workflows/` contains only `ci.yml`, `build-image.yml`, `deploy.yml`, `deploy-production.yml`.
- Trusted proxies: `trustProxies(at: explode(',', env('TRUSTED_PROXIES', '10.0.0.0/8')))` — **CIDR-restricted, not wildcard** (`bootstrap/app.php:54-61`), replacing the earlier `at: '*'` behaviour (finding TECH-005, remediated).

## 5. External services and data flows

| Service | Purpose | Data sent | Cross-border? |
|---|---|---|---|
| Managed PostgreSQL (SSL required; `DB_*` credentials supplied as deploy-environment secrets — provider outside the repository) | Primary DB | All application data | Depends on provider |
| S3-compatible object storage (`object-storage` disk, `STORAGE_*`) and optional `r2` disk | Case documents, referral attachments (private) | Uploaded PII documents | Likely |
| Cloudinary `3.1.3` | User/client avatar images | Profile/client images | Yes |
| AI chatbot provider (`AI_CHATBOT_PROVIDER`, default `gemini`; `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `OPENROUTER_API_KEY` / `GEMINI_API_KEY` in `.env.example`) | Chatbot | User message + static helpdesk articles (no case PII) | Yes |
| Pusher / websockets | Realtime notifications | Notification events | Depends |
| PSGC API (`psgc.cloud`) | Address lookup (public gov data) | Address query params only | Yes |
| Mail (`log` driver default; `resend` transport for production; `ses`/`postmark` configs present) | Invitations, verification links, survey requests, notifications | Recipient email, content | Depends on provider |
| Cloudflare Turnstile | Bot protection on login, password reset, contact, public intake/tracking, chatbot | CAPTCHA token | Yes |
| ClamAV (optional; `MALWARE_SCANNER` `null` by default) | Malware scanning of uploads | Uploaded files | Local |

> **Privacy note:** Cross-border transfers to the AI provider, Cloudinary, and the database/object-storage providers invoke RA 10173 (PH Data Privacy Act) obligations for data-processing agreements and cross-border safeguards. `cases.consent_given_at` exists (good), but no PIA, no processor agreements, and no privacy notice are in the repository (see `external-evidence-required` and TECH-014/DPTM gaps).

## 6. Data stores

- **Database:** PostgreSQL, `DB_CONNECTION=pgsql`, SSL `require` (`DB_SSLMODE=require`), UUID PKs, JSONB columns, Row-Level Security, DB extensions enabled. Evidence: `.env.example:45-53`, migration `..._000007_enable_extensions`, RLS migrations `2026_06_01_000008`, `2026_06_02_000001`, `2026_07_19_000003`, `2026_07_19_000004`, `2026_07_21_000001`, `2026_08_13_000002`, `2026_09_08_000001`.
- **Cache / session / queue:** `.env.example` → session `database` (`SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, 120-min lifetime, `.env.example:55-58`), queue and cache `redis` (`.env.example:73,75`). Tables `sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs` (failed driver `database-uuids`, `config/queue.php:124`) present. Compose/local defaults may differ (database-backed cache/queue/session) — verify the active `.env` before relying on async behaviour.
- **File-storage disks:** `local`, `private`, `public`, `object-storage`, `r2`, `supabase` (legacy), `s3`, `audit-archives` — all cloud disks `visibility: private`; default disk from `FILESYSTEM_DISK` (`object-storage` in `.env.example`). Evidence: `config/filesystems.php:31-166`.

## 7. Authentication methods

- Multi-step login, **email + password first, then TOTP MFA only when MFA applies**:
  1. `GET login` → `AuthenticatedSessionController@create` (`routes/auth.php:21`); `POST login` (Turnstile + `throttle:login`) → `@store` (`routes/auth.php:27-29`).
  2. Credential verification, rate limiting and inactive/soft-deleted account rejection happen in `LoginRequest::authenticate()` (`app/Http/Requests/Auth/LoginRequest.php:44-83`); the `is_active`/`is_deleted` check is at `:68-74`.
  3. If `mfa_enabled_at !== null` **and** the role is MFA-enforced (`LoginRequest.php:76`; `User::isInMfaEnforcedRole()` at `app/Models/User.php:118-131` — every role in production, `ADMIN,CASE_MANAGER,AGENCY` by default elsewhere via `config/mfa.php`), the request is parked in a pending-MFA session (`MfaPendingState`) and redirected to the TOTP/recovery challenge: `GET login/mfa` → `MfaChallengeController@show` (`routes/auth.php:31`), `POST login/mfa/totp` → `@totp` (`:33`), `POST login/mfa/recovery` → `@recovery` (`:35`), `POST login/mfa/cancel` → `@cancel` (`:37`), all behind the `mfa.pending` alias (`EnsureMfaChallenge`).
  4. Otherwise the user is logged in directly (`LoginRequest.php:80`).
  There is **no emailed login-OTP step** — no `LoginOtpController` exists; email OTP (`OtpService`) is used only for email-change verification (`routes/auth.php:87-93`), public case intake (`routes/web.php:370-372`), and citizen tracking-number verification (`routes/web.php:384-392`).
- **MFA enrolment is enforced, not opt-in:** `CheckMfaEnrolled` (appended to the web group, `bootstrap/app.php:68`) redirects un-enrolled users in enforced roles to profile MFA setup; `EnsureMfaSession` (`bootstrap/app.php:67`) logs out MFA-enabled users whose session lacks a valid MFA marker; `MfaController` (`routes/web.php:69-73`) manages TOTP enrolment and single-use recovery codes.
- Session-based web auth (guard `web`). Sanctum installed but no token-issuing code path found.
- TOTP 2FA via `pragmarx/google2fa-laravel` + HMAC-hashed single-use recovery codes (`MfaService`); `mfa_secret` is an `encrypted` cast (`app/Models/User.php:81`).
- Cloudflare Turnstile CAPTCHA on login and password reset (`routes/auth.php:28,52`); email verification required (`verified` middleware).
- Public tracking uses a separate email-OTP flow (`TrackController`, `routes/web.php:383-394`).

Detailed authn/authz assessment: see `technical-security-and-quality-findings`.

## 8. Sensitive and personal data processed (PII inventory)

Extensive OFW PII, regulated under RA 10173:
- **clients:** name, middle initial, suffix, `date_of_birth`, `sex`, email, contact number, avatar — migration `..._000002:13-30`.
- **client_addresses:** region, province, city/municipality, barangay, street — `:67-83`.
- **client_employments:** employer, position, country, employment dates, date of arrival — `:86-105`.
- **next_of_kin:** name, relationship, phone, email, full address — `:108-133`.
- **cases:** `consent_given_at`, `vulnerability_indicator`, `nok_vulnerability_indicator`, `escalation_reason`, `draft_client_data` (JSONB raw PII), tracker number, summary — `:35-64`.
- **users:** name, email, bcrypt password (rounds 12), contact number, position/department/office, `emergency_contact`, `mfa_secret`, `mfa_recovery_codes` — migration `0001_...:11-41`.
- **case_documents / referral_attachments:** uploaded ID/contract documents (most sensitive payload) — private object storage.
- **survey forms / responses / invitations:** ratings + free-text (successor to the retired `feedback` tables, whose schema rows still exist); **email_logs:** recipient email; **sessions:** IP + user agent; **audit_logs:** old/new JSONB (User PII fields excluded via `$auditExclude`).

## 9. Data flows and trust boundaries

**Internet-facing, unauthenticated entry points:** `/`, `/partners`, `/contact`, `/privacy`, `/terms`, `/help/*` (helpdesk); public surveys `GET|POST /survey/{token}` (throttled, `routes/web.php:53-58`); public tracking `/track*` (OTP-gated, `routes/web.php:383-413`); public intake `/intake*` (email-OTP verified, `routes/web.php:366-381`); public chatbot `POST /chatbot/message` (throttled, ≤1000 chars, forwards to the configured AI provider); auth endpoints (Turnstile + throttle).

**Authenticated boundary:** everything under `auth` + `verified` (`routes/web.php:62`); session-authenticated internal API under `/api/clients*` (`routes/web.php:432-437`).

**Admin boundary:** `/admin/*` requires `role:ADMIN` **+ `ip.whitelist`** (`routes/web.php:245`) — IP-restricted admin console. Note: the allowlist is config-gated and **disabled by default** (`config/auth.php` → `auth.ip_whitelist.enabled = env('AUTH_IP_WHITELIST_ENABLED', false)`).

**Database trust boundary:** Postgres Row-Level Security on the PII tables — enabled on 11 core tables (`2026_06_01_000008`: cases, clients, client_addresses, client_employments, next_of_kin, referrals, milestones, referral_attachments, referral_comments, case_documents, case_notifications) and extended to the referral-client inbox/message tables (`2026_07_19_000004`, `2026_08_13_000002`, `2026_09_08_000001`); policies are admin-bypass / case-manager-owns / agency-scoped-by-referral, with later revisions for case-manager read access and hardened document/referral rules. Context is set per-request by `SetPostgresSession` (parameterized `set_config`, `app/Http/Middleware/SetPostgresSession.php:35-44`). **Requires a direct DB connection (not PgBouncer transaction mode)** — a pooling misconfiguration silently disables per-user isolation.

**Global middleware order:** `SetPostgresSession` → `LogContext` → `SecurityHeaders` → `StripRedirectResponseBody` (`bootstrap/app.php:44-47`); web group prepends `ContentSecurityPolicy` and appends `CheckUserActive`, `EnsureMfaSession`, `CheckMfaEnrolled`, `HandleInertiaRequests` (`bootstrap/app.php:63-72`).

**Webhooks:** one inbound endpoint — `POST /api/webhooks/resend` (delivery/bounce/complaint events, signature-verified, `routes/api.php:37`). Otherwise outbound API calls only.

## 10. Background jobs and scheduled tasks

- Queued work: one job class exists (`app/Jobs/GenerateSystemReport.php`); most async work is dispatched via Notifications, Mailables, and Listeners to the queue (`QUEUE_CONNECTION=redis` in `.env.example`).
- Events/Listeners: `ReferralCompleted`; `EmailEventSubscriber`, `LogSuccessfulLogin`, `LogFailedLogin`, `LogSuccessfulLogout`, `SendSurveyRequest` — auth listeners registered at `app/Providers/AppServiceProvider.php:287-289`.
- Scheduled tasks (`routes/console.php`): scheduler heartbeat every minute; `helpcenter:sync` hourly; `logs:cleanup` daily 03:00; `audit:archive` monthly (01:00); `audit:prune --force` monthly (02:30) — archive-gated with chain checkpoints (`PruneAuditLogs.php:34-123`), so it no longer conflicts with the append-only trigger; `audit:verify` weekly (chain verification); `storage:cleanup-orphans` daily; `cases:purge-trashed` daily 02:00; `documents:prune` daily 03:30.
- Artisan commands include: ArchiveAuditLogs, BackfillAuditDescriptions, CleanupLogs, CleanupOrphanedFiles, PruneAuditLogs, PruneEmailLogs, PurgeTrashedCases, RebuildChatbotIndex, RepairAuditChain, RevokeMfaEnrolledSessions, SyncFailedEmails, VerifyAuditChain, WarmCache (`app/Console/Commands/`).

## 11. Critical business functions and single points of failure

- **Critical functions:** case creation/referral routing, public OTP tracking, RLS-based data isolation, document storage.
- **SPOF — object storage:** all case documents/attachments; no replication in config. Loss = loss of legal case evidence.
- **SPOF — managed Postgres:** single managed DB; RLS depends on it and on non-PgBouncer-transaction-mode connections.
- **Watch item — queue worker & scheduler in the single-container image:** `docker/supervisord.conf` now defines `queue-worker` and `scheduler` programs alongside php-fpm and nginx, gated by `RUN_QUEUE_WORKER`/`RUN_SCHEDULER` (default `true`). Whether the deployed service actually runs them (and with which flags) is outside the repository — confirm as external evidence. *(Medium confidence.)*
- **RLS-migration soft-fail:** the enable-RLS migration is wrapped in try/catch and only warns on non-Postgres — a non-PG environment runs with no row-level isolation (TECH-034).
- **Third-party dependencies:** the configured AI provider (degrades gracefully), Cloudinary, Pusher, mail provider.

## 12. Health-check endpoints

- **`/up`** — Laravel built-in health endpoint (`bootstrap/app.php:41`), used by the `Dockerfile` HEALTHCHECK (`Dockerfile:151`), compose health-check (`docker-compose.yml:95`), and the deploy health gate (`deploy.yml:382-392`).
- **`/api/readyz`** — readiness probe with a deep database/scheduler check (`routes/api.php:12-14`), called by the deploy readiness gate with `MONITORING_READINESS_TOKEN` (`deploy.yml:396-416`).
- **`/health`** — **no such route exists** anywhere in the application (the earlier CI workflow that probed it no longer exists; nothing in `.github/workflows/` references `/health`).

---

## Textual architecture / data-flow description

```
                        Internet
                           |
        +------------------+-------------------+
        | (unauth)                             | (auth)
   Public flows                          Login: password
   /track (OTP)                           -> TOTP MFA challenge
   /survey/{token}                        -> (only if MFA applies)
   /chatbot -> AI provider                Turnstile CAPTCHA
          |                                        |
         +------------> Nginx (rate-limit, headers) ------> PHP-FPM (Laravel)
                                                   |
             Global MW: SetPostgresSession, LogContext, SecurityHeaders, StripRedirectResponseBody
                                                   |
             +-------------------+-----------------+------------------+
             |                   |                 |                  |
      role:ADMIN+ip.whitelist  CASE_MANAGER      AGENCY          Notifications/
      /admin/* console         (owns data)   (agcy_id scope)      Mail (queue)
             |                   |                 |               + OFW (/my-cases)
             +---------- Postgres (managed) w/ Row-Level Security --+
                                 |                          |
                     Object storage (S3-compatible)    Cloudinary (avatars)
                     private case documents
```

**Trust boundaries:** (1) Internet↔Nginx; (2) Nginx↔app (proxy trust — `TRUSTED_PROXIES` CIDR, `bootstrap/app.php:54-61`); (3) role/IP middleware at the app layer (admin IP allowlist config-gated, default off); (4) Postgres RLS at the data layer (defense-in-depth, PG-dependent); (5) app↔external processors (AI provider/Cloudinary/object storage — cross-border PII).

---

*Confidence: High for stack, routes, models, migrations, auth methods, integrations, and RLS design (directly observed). Medium for production cache/queue/session/worker topology (compose, Dockerfile, and `.env.example` disagree; the hosting provider's service configuration is outside the repository).*
