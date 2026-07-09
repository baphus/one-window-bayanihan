# System and Service Profile — One Window Bayanihan

| Field | Value |
|---|---|
| Document | System & Service Profile |
| Version | v1.0.0 |
| Date | 2026-07-08 |
| Status | Draft for review (REVIEW tier — see executive summary triage) |
| Prepared by | Automated alignment assessment (Claude Code), evidence-based |
| Assumed standard editions | ISO/IEC 27001:2022, ISO/IEC 27002:2022, ISO/IEC 20000-1:2018, ISO 9001:2015 |
| Basis | Read-only static inspection of the repository at commit `b8a7211` (branch `main`). No runtime observation. |

> **Limitation:** This profile is derived solely from repository evidence. Deployed production topology (Render/Sevalla service configuration) is outside the repository and is marked *Medium confidence* where inferred.

---

## Changelog

| Version | Date | Author | Change |
|---|---|---|---|
| v1.0.0 | 2026-07-08 | Alignment assessment | Initial system and service profile. |

---

## 1. Project purpose and business processes

An inter-agency **"one window" case-management and referral system for Overseas Filipino Workers (OFWs)** and their families, operated by the Philippine **Department of Migrant Workers (DMW) Region VII**, with partner agencies OWWA, DMW, TESDA, DSWD, and DOLE.
Evidence: `app/Http/Controllers/ChatbotController.php:17-23`, `Dockerfile:25`, `README.md`, `srs_extracted.txt`.

Business processes supported:
- Case intake and management (draft → publish → archive lifecycle) — `routes/web.php:125-137`.
- Client (OFW) profile management: addresses, employment history, next-of-kin — `app/Http/Controllers/ClientController.php`, migration `..._000002_*`.
- Inter-agency referrals with status workflow, milestones, comments, versioned attachments, compliance-requirement fulfilment — `routes/web.php:94-108`, migration `..._000003_*`.
- Public case tracking via tracker number + email OTP — `TrackController`, `routes/web.php:296-306`.
- Feedback / SERVQUAL surveys via tokenized public links — `PublicFeedbackController`, `routes/web.php:46-51`.
- Reporting and analytics, including Excel/PDF export and AI-generated insights — `ReportsController`.
- System administration (users, agencies, services, reference data, logs, sessions, security, maintenance, data export) — `routes/web.php:210-270`.
- AI helpdesk chatbot "Bayani" — `ChatbotController`.

## 2. User roles and privileged roles

Roles are modelled as a **single string column `users.role` (VARCHAR(50))** — not an enum table, not `spatie/laravel-permission`.
Evidence: `database/migrations/0001_01_01_000000_create_framework_tables.php:16`; `app/Models/User.php:90-103`.

| Role | Scope | Enforcement |
|---|---|---|
| `ADMIN` (privileged) | Exclusive `/admin/*` and `/admin/system/*`; bypasses all Postgres RLS | `role:ADMIN` + `ip.whitelist` middleware (`routes/web.php:210`) |
| `CASE_MANAGER` | Owns cases, clients, documents, scoped audit logs | `role` middleware + in-controller ownership checks |
| `AGENCY` | Scoped to referrals for their `agcy_id` | `role` middleware + `authorizeReferralAccess` |

There is **no citizen/end-user account role**; the public interacts anonymously via token/OTP flows. Enforcement middleware `CheckRole` (`app/Http/Middleware/CheckRole.php:13`) is default-deny (`abort(403)`).

> **Documentation contradiction (load-bearing):** `docs/PROJECT_RULES.md`, `docs/ARCHITECTURE.md`, `docs/SECURITY_REQUIREMENTS.md`, and `docs/DATA_MODEL.md` state RBAC uses Spatie `laravel-permission` with `roles`/`permissions` tables. Those tables and that package **do not exist**. Only `README.md` correctly describes the `users.role`/`CheckRole` mechanism. See finding **TECH-010**.

## 3. Technology stack

- **Backend:** PHP `^8.3` (Docker runtime `php:8.4-fpm`); Laravel `^13.7`; Inertia Laravel `^2.0`; Sanctum `^4.0` (installed, no tokens issued); Ziggy `^2.0`. Evidence: `composer.json:8-21`.
- **Key libraries:** `barryvdh/laravel-dompdf ^3.1`, `phpoffice/phpspreadsheet 5.8.0`, `cloudinary/cloudinary_php 3.1.3`, `league/flysystem-aws-s3-v3 3.34.0`, `openai-php/client ^0.19.2` + `laravel/ai *`, `pragmarx/google2fa-laravel 3.0.1`.
- **Frontend:** React 18 + `@inertiajs/react ^2.0`, Vite `^8`, Tailwind `^3.2` (with `@tailwindcss/vite ^4` — version conflict, see TECH-022), TanStack Query, Chart.js, react-markdown + rehype-sanitize, zod.
- **Testing:** PHPUnit `^12.5`, Vitest `^4`, Playwright `^1.61`.
- **Package managers:** both Bun (`bun.lock`) and npm (`package-lock.json`) present — dual-lockfile drift risk (TECH-022).

## 4. Deployment model

- **PaaS target: Render.com** (staging `https://bayanihan-staging.onrender.com`). Deploy on push to `main` → run tests against a Postgres 15 service → trigger Render deploy via API. Evidence: `.github/workflows/deploy-staging.yml`.
- **Single-container image** (`Dockerfile`, multi-stage `node:22` build → `php:8.4-fpm` runtime bundling Nginx + Supervisor), health-check `curl /up`. Supervisor runs **only php-fpm + nginx** — no queue worker or scheduler in the PaaS image (`docker/supervisord.conf`). See SPOF in §11.
- **docker-compose.yml** (fuller local/self-host topology): separate `nginx`, `app`, `queue` (`queue:listen --tries=3 --timeout=90`), `scheduler` (`schedule:work`), `migrate`, `db` (postgres:15), `redis`. Hardened with `no-new-privileges`, `cap_drop: ALL`, CPU/memory limits, health-checks.
- **Scheduled staging data reset:** cron daily 02:00 UTC re-migrates and re-seeds staging — `reset-staging-data.yml`. Scoped to staging only (no prod reference).
- Trusted proxies: `trustProxies(at: '*')` — **all proxies trusted** (`bootstrap/app.php:39`), which undermines IP-based admin control (TECH-005).

## 5. External services and data flows

| Service | Purpose | Data sent | Cross-border? |
|---|---|---|---|
| Supabase (managed Postgres + S3 storage) | Primary DB + `case-files` bucket | All application data + uploaded case/referral documents | Likely (US) |
| S3-compatible object storage | Case documents, referral attachments (private) | Uploaded PII documents | Likely |
| Cloudinary `3.1.3` | User/client avatar images | Profile/client images | Yes |
| OpenAI (default AI provider) | (a) Chatbot; (b) reports AI insight | Chatbot: user message + static guide (no case PII). Reports: aggregated KPI summary (~300 tokens), not raw records | Yes |
| Pusher / websockets | Realtime notifications | Notification events | Depends |
| PSGC API (`psgc.cloud`) | Address lookup (public gov data) | Address query params only | Yes |
| Mail (log driver default; SES/Postmark/Resend configs present) | OTP, feedback, notifications | Recipient email, OTP codes, content | Depends on provider |
| Cloudflare Turnstile | Bot protection on login/register | CAPTCHA token | Yes |
| ClamAV (optional; `null` by default) | Malware scanning of uploads | Uploaded files | Local |

> **Privacy note:** Cross-border transfers to OpenAI, Cloudinary, and Supabase invoke RA 10173 (PH Data Privacy Act) obligations for data-processing agreements and cross-border safeguards. `cases.consent_given_at` exists (good), but no PIA, no processor agreements, and no privacy notice are in the repository (see `external-evidence-required` and TECH-014/DPTM gaps).

## 6. Data stores

- **Database:** PostgreSQL (Supabase managed), `DB_CONNECTION=pgsql`, SSL `require`, UUID PKs, JSONB columns, Row-Level Security, DB extensions enabled. Evidence: `.env.example:23-31`, migration `..._000007_enable_extensions`.
- **Cache / session / queue:** `.env.example` → Redis (session `SESSION_ENCRYPT=true`, 120-min lifetime); compose defaults → `database`. Tables `sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs` (driver `database-uuids`) present. Config differs by environment (Medium confidence on production driver).
- **File-storage disks:** `local`, `private`, `public`, `object-storage` (default), legacy `supabase`, `s3` — all cloud disks `visibility: private`. Evidence: `config/filesystems.php:63-96`.

## 7. Authentication methods

- Custom multi-step login (`LoginOtpController`): email+password → emailed 6-digit OTP → TOTP/recovery code **only if MFA enabled**. Breeze `AuthenticatedSessionController` exists but is not routed.
- Session-based web auth (guard `web`). Sanctum installed but issues no tokens.
- TOTP 2FA via `pragmarx/google2fa-laravel` + single-use recovery codes (`MfaController`).
- Cloudflare Turnstile CAPTCHA on login/register; email verification required (`verified` middleware).
- Public tracking uses a separate email-OTP flow (`TrackController`).

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
- **feedback:** ratings + free-text; **email_logs:** recipient email; **sessions:** IP + user agent; **audit_logs:** old/new JSONB (User PII fields excluded via `$auditExclude`).

## 9. Data flows and trust boundaries

**Internet-facing, unauthenticated entry points:** `/`, `/partners`, `/contact`, `/privacy`, `/terms`, `/helpdesk/*`; public feedback `GET|POST /feedback/{token}` (throttled); public tracking `/track*` (OTP-gated); public chatbot `POST /chatbot/message` (throttled, ≤1000 chars, forwards to OpenAI); public address API `/api/address/*`; auth endpoints (Turnstile + throttle).

**Authenticated boundary:** everything under `auth` + `verified` (`routes/web.php:63`); session-authenticated internal API under `/api/clients*`.

**Admin boundary:** `/admin/*` requires `role:ADMIN` **+ `ip.whitelist`** — IP-restricted admin console.

**Database trust boundary:** Postgres Row-Level Security on 11 PII tables (33 policies: admin bypass / case_manager owns / agency scoped-by-referral), context set per-request by `SetPostgresSession` middleware. **Requires a direct DB connection (not PgBouncer transaction mode)** — a pooling misconfiguration silently disables per-user isolation.

**Global middleware order:** `SetPostgresSession` → `LogContext` → `SecurityHeaders`, then web group appends `ContentSecurityPolicy` + `HandleInertiaRequests` (`bootstrap/app.php:35-52`).

**Webhooks:** none inbound. Outbound API calls only.

## 10. Background jobs and scheduled tasks

- No `app/Jobs/` classes (directory empty). Async work via Notifications, Mailables, and Listeners dispatched to the queue.
- Events/Listeners: `ReferralCompleted`; `EmailEventSubscriber`, `LogSuccessfulLogin`, `SendFeedbackRequest`.
- Scheduled tasks (`routes/console.php`): `helpcenter:sync` hourly; `logs:cleanup` daily 03:00; `audit:prune --force` monthly (conflicts with append-only trigger — TECH-015); `storage:cleanup-orphans` daily.
- Artisan commands: BackfillAuditDescriptions, CleanupLogs, CleanupOrphanedFiles, PruneAuditLogs, PruneEmailLogs, SyncFailedEmails.

## 11. Critical business functions and single points of failure

- **Critical functions:** case creation/referral routing, public OTP tracking, RLS-based data isolation, document storage.
- **SPOF — object storage:** all case documents/attachments; no replication in config. Loss = loss of legal case evidence.
- **SPOF — Supabase Postgres:** single managed DB; RLS depends on it and on non-PgBouncer-transaction-mode connections.
- **SPOF/gap — queue worker & scheduler absent in the PaaS single-container image:** if Render uses that image, queued emails/notifications and all scheduled jobs would not run unless Render defines separate worker/cron services (not evidenced). *(Medium confidence.)*
- **RLS-migration soft-fail:** the enable-RLS migration is wrapped in try/catch and only warns on non-Postgres — a non-PG environment runs with no row-level isolation (TECH-034).
- **Third-party dependencies:** OpenAI (degrades gracefully), Cloudinary, Pusher, mail provider.

## 12. Health-check endpoints

- **`/up`** — Laravel built-in health endpoint (`bootstrap/app.php:33`), used by `Dockerfile` HEALTHCHECK and compose.
- **`/health`** — referenced by `reset-staging-data.yml` but **no such route exists** (mismatch — TECH-027).

---

## Textual architecture / data-flow description

```
                        Internet
                           |
        +------------------+-------------------+
        | (unauth)                             | (auth)
   Public flows                          Login: password
   /track (OTP)                           -> email OTP
   /feedback/{token}                      -> TOTP (if enabled)
   /chatbot -> OpenAI                     Turnstile CAPTCHA
   /api/address -> PSGC                          |
        |                                        v
        +------------> Nginx (rate-limit, headers) ------> PHP-FPM (Laravel)
                                                   |
             Global MW: SetPostgresSession, LogContext, SecurityHeaders, CSP
                                                   |
             +-------------------+-----------------+------------------+
             |                   |                 |                  |
      role:ADMIN+ip.whitelist  CASE_MANAGER      AGENCY          Notifications/
      /admin/* console         (owns data)   (agcy_id scope)      Mail (queue)
             |                   |                 |
             +---------- Postgres (Supabase) w/ Row-Level Security --------+
                                 |                          |
                     Object storage (S3/Supabase)      Cloudinary (avatars)
                     private case documents
```

**Trust boundaries:** (1) Internet↔Nginx; (2) Nginx↔app (proxy trust — currently `*`, TECH-005); (3) role/IP middleware at the app layer; (4) Postgres RLS at the data layer (defense-in-depth, PG-dependent); (5) app↔external processors (OpenAI/Cloudinary/Supabase — cross-border PII).

---

*Confidence: High for stack, routes, models, migrations, auth methods, integrations, and RLS design (directly observed). Medium for production cache/queue/session/worker topology (compose, Dockerfile, and `.env.example` disagree; Render service config is outside the repository).*
