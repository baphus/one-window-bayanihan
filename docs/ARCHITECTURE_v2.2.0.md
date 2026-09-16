# Architecture

> **Version:** 2.2.0 | **Updated:** 2026-09-15 | **Status:** Verified against source code
> **Source:** `composer.json`, `package.json`, `Dockerfile`, `docker-compose.yml`,
> `docker/supervisord.conf`, `bootstrap/app.php`, `routes/web.php`, `routes/api.php`,
> `routes/auth.php`, `config/filesystems.php`, `config/mfa.php`, `config/auth.php`,
> `app/Services/Chatbot/`, `app/Services/OtpService.php`,
> `app/Http/Controllers/ReferralMessageController.php`,
> `database/seeders/StagingSeeder.php`, `scripts/sync-philippine-addresses.cjs`,
> `.github/workflows/deploy.yml`
>
> Supersedes `ARCHITECTURE_v2.1.0.md` (v2.1.0), retained unchanged per document
> versioning policy. This is a delta document — everything in v2.1.0 still holds
> unless restated below. Infrastructure is described by technology and capability,
> never by hosting vendor — see `DEPLOYMENT_GUIDE_v3.0.0.md` §1.

## Changelog

**2.2.0 (2026-09-15)** — ground-truth reconciliation pass. Every version number,
path, middleware name, and queue policy below was read from code on 2026-09-15:

- **PHP floor raised to 8.4.1.** `composer.json:9` requires `php: >=8.4.1 <9.0`
  (v2.1.0 §2 said `PHP ^8.3`). Framework is `laravel/framework: ^13.7`
  (`composer.json:16`). The container builds on `php:8.4-fpm`
  (`Dockerfile:28`) with pinned `phpredis 6.1.0` (`Dockerfile:41`).
- **Auth flow corrected.** v2.1.0 §5 described an email-OTP login via
  `LoginOtpController` — that controller does not exist. Login is password-based
  (`AuthenticatedSessionController`, `routes/auth.php:21-29`, bot-protection +
  `throttle:login`), with a separate TOTP/recovery MFA challenge
  (`MfaChallengeController`, `routes/auth.php:31-38`). Email OTP is a separate
  mechanism (`App\Services\OtpService`: `TTL_MINUTES = 5`, `MAX_ATTEMPTS = 5`)
  used by email-change, intake, and tracking flows — not by login.
- **Chatbot pipeline documented.** `ChatbotConversationService` drives the
  authorized `HelpdeskAgent` (`ChatbotConversationService.php:44`); article
  retrieval is `ChatbotKnowledge`'s in-memory weighted article search
  (`ChatbotHelpdeskService.php:341`). There is no vector database and no
  full-text index — retired designs must not be reintroduced.
- **Second object-storage disk.** `config/filesystems.php:87-99` adds an `r2`
  disk (S3-compatible, path-style, activated with `FILESYSTEM_DISK=r2`); the
  `object-storage` disk remains the default (`filesystems.php:63-79`). The
  `audit-archives` disk (`filesystems.php:128-140`) inherits credentials from
  whichever of `r2` / `object-storage` is active. Compose passes the `R2_*`
  variables through to every service (`docker-compose.yml:66-71`).
- **Queue topology: three policies, not one.** v2.1.0 §12 named only the local
  `queue:listen` command. Reality: local dev listens on
  `--queue=default,notifications --tries=1 --timeout=0` (`composer.json:61`);
  the compose `queue` service listens with `--tries=3 --timeout=90`
  (`docker-compose.yml:156`); the image's supervisord worker runs
  `queue:work --queue=default,notifications,heavy --sleep=3 --tries=3
  --timeout=30 --max-jobs=500 --max-time=3600` (`docker/supervisord.conf:34`),
  with `stopwaitsecs=35` so an in-flight job finishes before SIGKILL. The
  scheduler runs `schedule:work` in the same image, gated by `RUN_SCHEDULER` /
  `RUN_QUEUE_WORKER` guards (`docker/supervisord.conf:34,57`) so the web tier
  can scale out with exactly one scheduler.
- **Staging seed data.** `database/seeders/StagingSeeder.php` (with helpers under
  `database/seeders/Staging/`: `StagingDataFactory`, `TemporalEngine`,
  `VolumeModel`, `AuditChainWriter`) generates volume-realistic staging data with
  a valid audit hash chain.
- **Agency pair-messaging.** `ReferralMessageController` (`index`/`store`/
  `markRead`) backs the per-referral "Other Agencies on This Case" thread
  (`routes/web.php:98-100`); the read side is rate-limited (`throttle:api-global`).
- **PSGC address sync script.** `npm run addresses:sync` runs
  `scripts/sync-philippine-addresses.cjs`, producing the lookup table that
  `AddressNameResolver` reads from `resources/js/data/` at runtime (the
  `Dockerfile:120-127` cleanup deliberately preserves `resources/js/data` —
  deleting it silently degrades every address display to raw PSGC codes).
- **Deploy workflow reality.** The reusable `.github/workflows/deploy.yml`
  (433 lines) does not migrate from the runner: the database is not reachable
  from hosted runners, so migrations run inside the container entrypoint before
  traffic is accepted (`--isolated` lock, non-zero exit fails the release and
  keeps the previous revision). The runner owns the pre-deploy snapshot,
  the image-tag deployment, and the health gates (`/up` shallow + `/api/readyz`
  deep — see `routes/api.php:13-15`, which is deliberately *not* the container
  health check so a database blip cannot cause a restart loop).
- **Queue/cache drift from v2.1.0 resolved.** The "known drift" note in v2.1.0
  §15 is closed: the canonical backend is Redis 7 (`redis:7-alpine`,
  `docker-compose.yml:296`; `CACHE_STORE`/`QUEUE_CONNECTION` default to `redis`
  in compose), with the database drivers as degraded fallback. OTP state lives
  in cache (`OtpService` uses `Cache::`), and MFA challenge tuning lives in
  `config/mfa.php` (`pending_ttl` 300, `max_attempts` 5, `replay_ttl` 120).

**2.1.0 (2026-07-27)** — see `ARCHITECTURE_v2.1.0.md`.

## 1. System Overview (carried forward, corrected)

```
Browser (React 18.2 + Inertia 2.0)
    │ HTTPS
Laravel 13 application (PHP >= 8.4.1)
    ├─ Global middleware (SetPostgresSession → LogContext → SecurityHeaders
    │   → StripRedirectResponseBody; origin-only forgery protection;
    │   trusted proxies 10.0.0.0/8) — bootstrap/app.php:44-61
    ├─ Web middleware (ContentSecurityPolicy prepended; CheckUserActive →
    │   EnsureMfaSession → CheckMfaEnrolled → HandleInertiaRequests →
    │   AddLinkHeadersForPreloadedAssets) — bootstrap/app.php:63-72
    ├─ Route middleware aliases: role, ip.whitelist, turnstile,
    │   turnstile.session, mfa.pending — bootstrap/app.php:74-80
    │
    ▼ Controller → Service → Model
PostgreSQL (15-alpine local, newer managed server in CI/production)
    ├─ S3-compatible object storage (case documents, attachments, audit archives;
    │   selectable r2 disk)
    ├─ Image CDN (avatar images — optional; falls back to object storage)
    └─ Redis 7 (cache, queue broker, OTP store, rate limiters;
        database drivers as degraded fallback)
```

## 2. Tech Stack (carried forward, corrected)

| Component | Technology | Version | Source |
|-----------|-----------|---------|--------|
| Language | PHP | `>=8.4.1 <9.0` | `composer.json:9` |
| Framework | Laravel | `^13.7` | `composer.json:16` |
| Frontend | React + Inertia | `18.2` + `2.0` | `package.json:29-30,16`, `composer.json:14` |
| CSS | Tailwind CSS | `^3.2.1` | `package.json:31` |
| Build | Vite | `^8.0` | `package.json:33` |
| Language (frontend) | TypeScript | `^5.9.3` | `package.json:32` |
| Database | PostgreSQL | `15-alpine` local (`docker-compose.yml:268`) | `config/database.php:87-101` |
| Queue / Cache | Redis | `7-alpine` (`docker-compose.yml:296`) | compose defaults `redis` |
| Session | Database driver | — | compose `SESSION_DRIVER=database` |
| PDF | DomPDF | `^3.1` | `composer.json:11` |
| Excel | PhpSpreadsheet | `5.9.0` | `composer.json:22` |
| AI | LLM API via configurable provider | `openai-php/client ^0.19.2`, `laravel/ai *` | `composer.json:15,21` |
| MFA | TOTP (authenticator app) | `pragmarx/google2fa-laravel 3.0.1` | `composer.json:23` |
| Bot protection | Bot-protection verify API | — | `VerifyTurnstile` middleware; `TURNSTILE_*` keys |
| Error tracking | Generic Sentry SDK | `^4.8` | `composer.json:25`; any compatible ingest endpoint |
| File storage | S3-compatible object storage (`object-storage` default, `r2` selectable) | `flysystem-aws-s3-v3 3.34.0` | `config/filesystems.php:63-99` |
| Images | Image CDN SDK | `3.1.3` | optional; falls back to object storage |
| URL generation | Ziggy | `^2.0` | `composer.json:26` |
| Client data fetching | TanStack Query | `^5.101.0` | `package.json:38` |
| Image runtime | OCI image `node:22-bookworm` build → `php:8.4-fpm` runtime | phpredis `6.1.0` pinned | `Dockerfile:5,28,41` |
| CI | PHP `8.4`, Node `24`, database service container | — | `.github/workflows/ci.yml:38,45` |

## 3. Architecture Pattern (carried forward — unchanged)

```
Controller (thin) → Service (business logic) → Model (data access) → PostgreSQL
```

Layer responsibilities are as documented in v2.1.0 §3
(`app/Http/Controllers/` → `app/Http/Requests/` → `app/Services/` →
`app/Models/` → `app/Observers/` → `app/Events/`+`app/Listeners/` → `app/DTOs/`).
Row-level scoping is enforced at the database session layer via
`SetPostgresSession` (`app.current_user_id`), as in v2.1.0 §4.

## 4. Authentication Flow (corrects v2.1.0 §5)

```
1. POST /login (email + password + bot-protection token)
   → turnstile + throttle:login (routes/auth.php:27-29)
   → AuthenticatedSessionController::store validates credentials
   → If MFA required: arms the MFA challenge session, redirects to login/mfa
   → If no MFA: authenticates, redirects to /dashboard

2. POST /login/mfa/totp (totp_code) [mfa.pending + throttle:totp-challenge]
   → MfaChallengeController::totp validates the authenticator code
   → Replay protection: replay_ttl 120s (config/mfa.php:7)
   → Authenticates, redirects to /dashboard

Alternative: POST /login/mfa/recovery (recovery_code) [throttle:recovery-code]
   → Consumes one recovery code, authenticates
```

Challenge policy (`config/mfa.php:4-7`): `pending_ttl` 300s, `max_attempts` 5,
`replay_ttl` 120s. Enrollment is enforced for `ADMIN,CASE_MANAGER,AGENCY`
(`config/mfa.php:22-25`; OFW excluded) via `CheckMfaEnrolled`
(`bootstrap/app.php:68`) and `EnsureMfaSession` (`bootstrap/app.php:67`).
Email OTP (`OtpService`, TTL 5 min, max 5 attempts) is independent of login —
see §5 of `ROLES_AND_PERMISSIONS_v1.0.0.md` for where each mechanism applies.

## 5. Chatbot Pipeline (new in this revision)

No vector database, no full-text index. The pipeline is entirely in-memory over
the cached parsed helpdesk corpus:

```
POST /chatbot/message (turnstile.session + throttle:chatbot, routes/web.php:440-442)
  → ChatbotController::message
  → ChatbotConversationService → HelpdeskAgent (authorized knowledge/tools,
     response assembler — ChatbotConversationService.php:44)
  → ChatbotKnowledge weighted article search (ChatbotHelpdeskService.php:341)
  → assembled answer with verified sources
```

Pre-warm the corpus with `php artisan chatbot:index`. Client-side helpdesk
search additionally uses Fuse.js (`package.json:43`).

## 6. Queue & Scheduling Topology (corrects v2.1.0 §12)

| Context | Command | Policy |
|---------|---------|--------|
| Local dev (`composer run dev`) | `queue:listen --queue=default,notifications --tries=1 --timeout=0` | fail fast, infinite job timeout (`composer.json:61`) |
| Compose `queue` service | `queue:listen --tries=3 --timeout=90` | patient retries (`docker-compose.yml:156`) |
| Image supervisord worker | `queue:work --queue=default,notifications,heavy --sleep=3 --tries=3 --timeout=30 --max-jobs=500 --max-time=3600` | bounded jobs; `stopwaitsecs=35` (`docker/supervisord.conf:33-45`) |
| Image scheduler | `schedule:work` | exactly one instance cluster-wide; `RUN_SCHEDULER=false` on scaled web tier, single-instance scheduler service from the same image (`docker/supervisord.conf:47-57`) |

Canonical broker is Redis; the `database` driver (`jobs`/`failed_jobs` tables)
is the degraded fallback. Queued work: feedback invitations, case/referral
email notifications, email-event logging, OTP mail (`OtpService::generate`
queues `OtpMail`).

## 7. Deployment Topology (carried forward — see v2.1.0 §9)

Unchanged roles, stated by capability: HTTPS ingress → stateless application
instances (nginx + php-fpm 8.4 + supervisord in one OCI image) → PostgreSQL
primary with backups → Redis 7 → S3-compatible object storage → optional
error-tracking ingest → stdout/stderr log collection. Local development is the
compose stack (nginx `1.27-alpine`, app, `postgres:15-alpine`, `redis:7-alpine`,
one-shot `migrate` profile). Release safety comes from in-container
`--isolated` migrations plus `/up` and `/api/readyz` gates — see the changelog
entry above, not a vendor runbook.
