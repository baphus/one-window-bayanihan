# Deployment Guide

> **Version:** 3.1.0 | **Updated:** 2026-09-15 | **Supersedes:** `DEPLOYMENT_GUIDE_v3.0.0.md` (2026-07-27)
> **Source of truth:** `Dockerfile`, `docker-compose.yml`, `docker/supervisord.conf`, `docker/php/docker-entrypoint.sh`, `composer.json`, `config/*.php`, `.env.example`, `.github/workflows/deploy.yml`
> **Delta scope:** v3.0.0 remains the full strategy. This revision corrects floors, pins, and divergences verified against the repo on 2026-09-15. Anything not re-stated here is unchanged from v3.0.0 (§0 portability rules, §2 environment matrix shape, §5 models, §7 runbooks, §8 scaling, §9 operations, §10 monitoring, §11 rollback, §13 standards check).

## 0. What changed in v3.1.0 (delta)

| # | v3.0.0 said | Verified reality (2026-09-15) |
|---|---|---|
| D1 | PHP "8.3+ (production image uses 8.4)" | Floor is **PHP >= 8.4.1** (`composer.json` `>=8.4.1 <9.0`). Fix everywhere; 8.3 is unsupported |
| D2 | Laravel unspecified | **Laravel ^13.7** (locked **13.24.0** in `composer.lock`) |
| D3 | "Node 22" uniformly | **Node 24 in CI** (`ci.yml` `node-version: '24'`) vs **node:22-bookworm in `Dockerfile` stage 1**. Both build the same Vite output; CI is the newer toolchain |
| D4 | PostgreSQL 17 uniformly | **Compose `db` is `postgres:15-alpine`**; **CI backend-tests use `postgres:17`**. Production target stays 17 per §1 C3 (15+ tolerated). Local Compose is intentionally older — do not treat it as the prod reference |
| D5 | Queue `--queue=default,notifications` (dev) | Supervisord worker consumes **`--queue=default,notifications,heavy`** (`docker/supervisord.conf`); `composer run dev` listens on `default,notifications`. Compose `queue` service runs `queue:listen --tries=3 --timeout=90` |
| D6 | Migrations as "release step, not on boot" | **Migrations run inside the container entrypoint**: `RUN_MIGRATIONS=true` → `php artisan migrate --force --isolated --no-interaction`; fail-closed (non-zero exit, previous deployment keeps serving). See §6 |
| D7 | Health gate on `/up` only | Gates are **`/up` (liveness) + `/api/readyz` (deep readiness, token-gated)**. See §6 |
| D8 | Config defaults not stated | `config/queue.php` default `database`, `config/cache.php` default `database`, `config/session.php` default `database` — but **`.env.example` sets `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=database`**; the **deploy payload forces all three to `database`** (deploy override). See §4 annex |
| D9 | Mail pointer to `EMAIL_DELIVERY_v2.0.0.md` | Pointer moves to **`docs/EMAIL_DELIVERY_v2.1.0.md`** (v2.1.0 supersedes v2.0.0); `EMAIL_DOMAIN_RESEND.md` is tombstoned (superseded banner only) |

## 1. Platform capability contract (re-affirmed, floors corrected)

Same 13 rows (C1–C13) as v3.0.0 §1, with these floor corrections:

| # | Capability | Minimum requirement | Change vs v3.0.0 |
|---|---|---|---|
| C1 | Container runtime | OCI-compatible; runs the project `Dockerfile` image; ≥1 vCPU / 1 GB RAM per app instance | — |
| C2 | HTTPS ingress | TLS termination, HTTP/2, forwards `X-Forwarded-*`, routes to container port `8080` | — |
| C3 | Relational database | PostgreSQL **17** (15+ tolerated), TLS-capable, extensions `pgcrypto` + `pg_trgm`, role able to run DDL | Compose ships pg15 for local dev only — not the prod bar |
| C4 | Object storage | S3-compatible API, path-style addressing, private-by-default, SSE | — |
| C5 | Key-value store | Redis **7+**, password auth, TLS optional-but-recommended, persistence enabled | Compose: `redis:7-alpine`, `--appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru --tcp-keepalive 60` |
| C6 | Outbound mail transport | SMTP over 587/465 **or** HTTPS transactional-email API on 443 | Default safe value is `MAIL_MAILER=log`; `resend` only with verified sender domain + `RESEND_API_KEY` |
| C7 | Scheduled execution | `php artisan schedule:run` every minute (in-container `schedule:work` under supervisord, platform cron, or sidecar) | Singleton cluster-wide (see §6) |
| C8 | Secret management | Runtime env injection; never baked into image or repo | Entrypoint fails closed on missing `APP_KEY`/`DB_*` |
| C9 | Backup & recovery | Automated DB backups, ≤24 h RPO, restore capability; **pre-deploy snapshot** is taken by the pipeline | — |
| C10 | Log egress | Container stdout/stderr collection + retention | — |
| C11 | Error/APM ingest (optional) | Endpoint compatible with installed SDK | `SENTRY_LARAVEL_DSN`, traces sample `0.2`, `SENTRY_RELEASE=<image tag>` |
| C12 | Object/image CDN (optional) | Public CDN for avatars; falls back to object storage | `CLOUDINARY_URL` |
| C13 | Bot-protection (optional) | CAPTCHA verify API matching `TURNSTILE_*`; disable with `TURNSTILE_ENABLED=false` | — |
| + | Runtime floors | **PHP >= 8.4.1**, **Laravel ^13.7**, **phpredis 6.1.0** (pinned `ARG PHPREDIS_VERSION`), nginx **1.27-alpine** (Compose front), GD **with freetype+jpeg** | NEW row content |

Fallbacks unchanged: no Redis → `CACHE_STORE=database` + `QUEUE_CONNECTION=database`; no object storage → `FILESYSTEM_DISK=local` single-instance only; SMTP blocked → HTTPS-API mailer; no error ingest → file logging; no CDN → object storage.

## 4. Environment variable contract (canonical, verified 2026-09-15)

Group by capability. Canonical names below match `.env.example`; legacy `SUPABASE_S3_*` keys remain fallbacks in `config/filesystems.php`.

```env
# ── Application ───────────────────────────────────────────────
APP_NAME=Bayanihan
APP_ENV=local                        # local | staging | production
APP_DEBUG=false
APP_KEY=                             # php artisan key:generate
APP_URL=http://localhost

# ── Database (C3) ─────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=one_window
DB_USERNAME=postgres
DB_PASSWORD=secret
DB_SSLMODE=require                    # 'prefer' local dev, 'require' networked, 'disable' CI/tests only

# ── Object storage (C4) ───────────────────────────────────────
FILESYSTEM_DISK=object-storage        # or 'r2' for Cloudflare R2
STORAGE_ACCESS_KEY=
STORAGE_SECRET_KEY=
STORAGE_REGION=ap-southeast-1
STORAGE_ENDPOINT=
STORAGE_BUCKET=case-files
# Legacy fallbacks: SUPABASE_S3_* (config/filesystems.php)
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=                          # https://<account-id>.r2.cloudflarestorage.com
R2_PUBLIC_URL=

# ── Cache / queue / session (C5) ──────────────────────────────
CACHE_STORE=redis                     # config default 'database'; .env canonical is redis
QUEUE_CONNECTION=redis                # config default 'database'; .env canonical is redis
SESSION_DRIVER=database               # database-backed: shared across instances
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null                   # never null on a networked instance

# ── Mail (C6) ─────────────────────────────────────────────────
MAIL_MAILER=log                       # log | smtp (Mailpit :2525 local) | resend (prod)
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=                       # send-only key, required when MAIL_MAILER=resend
RESEND_WEBHOOK_SECRET=                # Svix whsec_*; empty disables POST /api/webhooks/resend
CONTACT_RECIPIENT_EMAIL=              # defaults to MAIL_FROM_ADDRESS

# ── Audit (retention + archive) ───────────────────────────────
AUDIT_RETENTION_DAYS=365              # queryable window + export range cap basis
AUDIT_ARCHIVE_DISK=audit-archives     # MUST be S3-compatible in production
AUDIT_CHAIN_VERIFIED_FROM=            # blank = strict full-chain verify
AUDIT_EXPORT_MAX_ROWS=100000
QUEUE_FAILED_ALERT_DRIVER=log

# ── Error ingest (C11) ────────────────────────────────────────
SENTRY_DSN=
SENTRY_LARAVEL_DSN=
SENTRY_LARAVEL_TRACES_SAMPLE_RATE=0.2
SENTRY_RELEASE=                       # pipeline sets to deployed commit SHA
VITE_SENTRY_DSN_PUBLIC=               # public browser DSN; empty disables client reporting
VITE_SENTRY_RELEASE=
VITE_APP_ENV=local

# ── MFA ───────────────────────────────────────────────────────
MFA_LOGIN_CHALLENGE_TTL=300           # pending_ttl 300, enforced
MFA_LOGIN_CHALLENGE_MAX_ATTEMPTS=5    # max 5, enforced
MFA_LOGIN_CHALLENGE_WINDOW=1
MFA_LOGIN_CHALLENGE_REPLAY_TTL=120
MFA_ENROLLMENT_ENFORCEMENT_ENABLED=true
MFA_ENROLLMENT_ENFORCED_ROLES=ADMIN,CASE_MANAGER,AGENCY

# ── AI chatbot ────────────────────────────────────────────────
AI_CHATBOT_ENABLED=false
AI_CHATBOT_PROVIDER=gemini
AI_CHATBOT_MODEL=gemini-flash-latest
AI_CHATBOT_TEMPERATURE=0.2
AI_CHATBOT_MAX_TOKENS=2000
AI_CHATBOT_TIMEOUT=45
AI_CHATBOT_MAX_STEPS=6
AI_CHATBOT_MAX_TOOL_CALLS=8
AI_CHATBOT_MAX_ARTICLE_READS=6
AI_CHATBOT_MAX_CONTEXT_CHARACTERS=24000
APP_ASSISTANT_NAME=Bayani
OPENAI_API_KEY= / ANTHROPIC_API_KEY= / OPENROUTER_API_KEY= / GEMINI_API_KEY=
AI_EMBEDDING_API_KEY=
AI_EMBEDDING_MODEL=text-embedding-3-small
AI_CHAT_RATE_LIMIT=30
AI_MAX_TOKENS_PER_CHUNK=800
AI_CHUNK_OVERLAP_TOKENS=100
AI_RETRIEVAL_MAX_RESULTS=5
AI_RETRIEVAL_MIN_SCORE=0.3
AI_LOGGING_ENABLED=true
AI_FALLBACK_MESSAGE="I could not find documentation for that. Please try rephrasing your question or browse our Help Center."

# ── Edge / misc ───────────────────────────────────────────────
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
TURNSTILE_ENABLED=false
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
MALWARE_SCANNER=null                  # 'clamav' or null (daemon localhost:3310)
SEARCH_INDEXING_ENABLED=false         # opt-in go-live step; false emits X-Robots-Tag: noindex
CLOUDINARY_URL=
```

R2 / Svix / Sentry coverage from v3.0.0 §4 carries forward unchanged. Mail transport-selection rule lives in `docs/EMAIL_DELIVERY_v2.1.0.md` (supersedes v2.0.0).

### Annex 4A — queue/cache/session: config default vs .env vs deploy override

| Layer | `CACHE_STORE` | `QUEUE_CONNECTION` | `SESSION_DRIVER` |
|---|---|---|---|
| `config/*.php` default (no env) | `database` | `database` | `database` |
| `.env.example` canonical (local/Compose) | `redis` | `redis` | `database` |
| `docker-compose.yml` app/queue/scheduler/migrate | `redis` | `redis` | `database` |
| **Deploy payload (`deploy.yml` jq block)** | **`database`** | **`database`** | **`database`** |

The deploy override is deliberate: the hosted container runs cache+queue on `database` (lock-compatible for `--isolated` migrations). Local dev/Compose uses Redis. Do not "fix" the deploy payload to redis without also re-proving the `--isolated` lock store and worker topology.

## 5. Container topology (verified)

**Compose services (7):** `nginx` (`nginx:1.27-alpine`, `:80` → app, health-gated on app) · `app` (target `app`, `.env` with `DB_HOST=db`, `REDIS_HOST=redis`, health `curl -sf http://nginx/up`) · `queue` (`queue:listen --tries=3 --timeout=90`) · `scheduler` (`schedule:work`) · `migrate` (profile `migrate`, one-shot `migrate --force --no-interaction`) · `db` (`postgres:15-alpine`) · `redis` (`redis:7-alpine`, appendonly, 128 MB, allkeys-lru).

**Dockerfile:** stage 1 `node:22-bookworm`, `npm ci --ignore-scripts` + `npm run build` → `public/build/`; stage 2 `php:8.4-fpm` + nginx + supervisor, `phpredis 6.1.0`, GD configured `--with-freetype --with-jpeg`, extensions `pdo/pdo_pgsql/sockets/bcmath/gd/intl/opcache/pcntl/exif/mbstring/zip`. Healthcheck `curl -f http://127.0.0.1:8080/up`. `ENTRYPOINT docker-entrypoint.sh`, `CMD supervisord`.

**Supervisord** (`docker/supervisord.conf`): `php-fpm`, `nginx`, `queue-worker` (`queue:work --queue=default,notifications,heavy --sleep=3 --tries=3 --timeout=30 --max-jobs=500 --max-time=3600`, `RUN_QUEUE_WORKER` guard, stopwait 35 s), `scheduler` (`schedule:work`, `RUN_SCHEDULER` guard — exactly one instance cluster-wide; scale-out recipe: web replicas `RUN_SCHEDULER=false` + one single-instance scheduler service).

## 6. Build pipeline and release sequence (reconciled)

Production build steps and the provider-agnostic trigger contract (`DEPLOY_API_URL` / `DEPLOY_API_TOKEN` / `DEPLOY_SERVICE_ID` / `HEALTH_CHECK_URL`) are unchanged from v3.0.0. What v3.0.0 described as "migrations as a release step, not on boot" is reconciled with the verified design:

1. **Migrations run in the entrypoint** (`docker/php/docker-entrypoint.sh`): precondition check (fail closed on missing `APP_KEY`/`DB_*`) → `mail:verify-transport --no-send` (fail closed before mutating schema) → `migrate --force --isolated --no-interaction` (atomic cache lock; exactly one container migrates; failure aborts boot so the previous deployment keeps serving) → optional `chatbot:index` → `exec "$@"` (supervisord).
2. **Why inside, not from CI:** the database no longer accepts public connections (private endpoint), so a hosted runner cannot reach it; running migrations from CI would require keeping the DB internet-facing purely for the pipeline (documented in `deploy.yml` header).
3. **Rolling-deploy safety still requires backward-compatible migrations** (expand → migrate → contract): the old container serves while the new one migrates.
4. **Gates:** `/up` (liveness, no side effects) **and** `/api/readyz` (deep readiness: DB + queue + scheduler heartbeat, `X-Monitoring-Token` required). The pipeline polls deployment `ACTIVE`-on-expected-image, then `/up` × 10, then `/api/readyz` × 8 (after a 75 s scheduler-heartbeat settle). `/up` 200 with `/api/readyz` failing = serving but degraded.
5. **Pre-deploy snapshot** is taken by the runner before submitting the deployment; `migrate:rollback` remains a schema tool, not a data rollback.

## 12. Platform binding inventory (sole vendor pit — unchanged rule)

Same rule as v3.0.0: everything vendor-specific is confined to the deploy step's endpoint/credentials, `HEALTH_CHECK_URL`, `DB_*`, `STORAGE_*`/`R2_*`, `REDIS_*`, `MAIL_MAILER` + transport credentials (see `docs/EMAIL_DELIVERY_v2.1.0.md`), `SENTRY_LARAVEL_DSN`, `CLOUDINARY_URL`, `TURNSTILE_*`. The committed pipeline's deploy step is the **only** platform-specific link; the rest (install → lint → audit → test → build → gate → notify) is provider-neutral. This section is the only place provider names may appear; compliance/management docs are exempt from the neutrality scrub per repo policy.

## 14. Changelog

| Version | Date | Change |
|---|---|---|
| 3.1.0 | 2026-09-15 | Delta corrections: PHP floor >= 8.4.1; Laravel ^13.7 (13.24.0); Node CI-24 vs Dockerfile-22 split; Compose pg15 vs CI pg17; supervisord `default,notifications,heavy` worker queues; entrypoint migration-in-boot + `--isolated` lock + mail preflight + `/api/readyz` deep gate reconciled into §6; queue/cache/session config-default vs .env vs deploy-override matrix (Annex 4A); full canonical env contract re-verified; mail pointer moved to `EMAIL_DELIVERY_v2.1.0`; §12 rule re-affirmed. |
| 3.0.0 | 2026-07-27 | Platform-neutral overhaul (see its §14). |
| 2.0.0 | 2026-07-11 | Previous revision (`DEPLOYMENT_GUIDE.md`). |
