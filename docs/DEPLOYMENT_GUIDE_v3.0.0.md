# Deployment Guide

> **Version:** 3.0.0 | **Updated:** 2026-07-27 | **Supersedes:** `DEPLOYMENT_GUIDE.md` (v2.0.0)
> **Source of truth:** `Dockerfile`, `docker-compose.yml`, `composer.json`, `config/*.php`, `.env.example`

## 0. How to read this guide

This guide is **platform-neutral by design**. It specifies the *technologies and
capabilities* the system requires — PostgreSQL, S3-compatible object storage,
Redis, an OCI container runtime, an outbound mail transport — and never a
specific hosting vendor, managed-database vendor, or PaaS product.

Any target that satisfies the capability contract in §1 can run this system with
no application code changes. Vendor selection is an operational and procurement
decision recorded outside this guide (see §12).

**Portability rules that keep this true:**

| Rule | Why |
|---|---|
| No vendor SDK in application code — only standard protocol clients (PDO/pgsql, S3 API, Redis, SMTP/HTTPS) | Swapping providers becomes a configuration change |
| All environment-specific values come from environment variables, never committed config | One image promotes unchanged across environments |
| Container image is the single deployable artefact | Identical runtime on a laptop, in CI, and in production |
| No local disk state in the request path | Enables horizontal scaling on any platform |
| Vendor names live only in deployment *values* and pipeline *credentials*, never in docs or code | Vendor changes never require a code or documentation rewrite |

---

## 1. Platform capability contract

A deployment target must provide the following. Anything that satisfies a row —
managed service, self-hosted, on-premises, or sovereign cloud — is acceptable.

| # | Capability | Minimum requirement | Consumed by |
|---|---|---|---|
| C1 | **Container runtime** | OCI-compatible; runs the project `Dockerfile` image; ≥1 vCPU / 1 GB RAM per app instance | Application, queue worker, scheduler |
| C2 | **HTTPS ingress** | TLS termination, HTTP/2, forwards `X-Forwarded-*`, routes to container port `8080` | Public traffic |
| C3 | **Relational database** | PostgreSQL **17** (15+ tolerated), TLS-capable, extensions `pgcrypto` and `pg_trgm`, role able to run DDL | All persistence, sessions, audit chain |
| C4 | **Object storage** | S3-compatible API, path-style addressing, private-by-default objects, server-side encryption | Case documents, referral attachments, audit archives |
| C5 | **Key-value store** | Redis **7+**, password auth, TLS optional-but-recommended, persistence enabled | Cache, queue broker, OTP store, rate limiters |
| C6 | **Outbound mail transport** | SMTP over 587/465 **or** an HTTPS transactional-email API reachable on 443 | OTP, MFA, notification, feedback mail |
| C7 | **Scheduled execution** | Ability to run `php artisan schedule:run` every minute (in-container supervisor, platform cron, or sidecar) | Retention, archive, reminder jobs |
| C8 | **Secret management** | Environment variables injected at runtime; values never baked into the image or written to the repo | All credentials |
| C9 | **Backup & recovery** | Automated database backups with point-in-time recovery or ≤24 h RPO, plus restore capability | BCP/DR obligations |
| C10 | **Log egress** | Ability to collect container stdout/stderr and retain per the retention policy | Audit and incident response |
| C11 | **Error/APM ingest** *(optional)* | Endpoint compatible with the installed error-tracking SDK (self-hosted or hosted) | Exception reporting |
| C12 | **Object/image CDN** *(optional)* | Public CDN for avatar images; falls back to object storage when absent | Avatars |
| C13 | **Bot-protection service** *(optional)* | CAPTCHA verify API matching the configured `TURNSTILE_*` keys; disable with `TURNSTILE_ENABLED=false` | Login protection |

**Capability gaps and their fallbacks:**

| Missing capability | Degraded mode | Where configured |
|---|---|---|
| C5 Redis | `CACHE_STORE=database`, `QUEUE_CONNECTION=database` (tables already migrated) | `.env` |
| C4 Object storage | `FILESYSTEM_DISK=local` — **single-instance only**, breaks horizontal scaling and archive immutability | `.env` |
| C6 SMTP ports blocked by the platform | Use an HTTPS transactional-email API mailer instead of SMTP | `MAIL_MAILER` |
| C11 Error ingest | File logging only; leave `SENTRY_LARAVEL_DSN` empty | `.env` |
| C12 CDN | Serve avatars from object storage | `.env` |

---

## 2. Environment matrix

| Environment | Compute | Database | Cache/Queue | Object storage | Mail |
|---|---|---|---|---|---|
| Local dev | `composer run dev` (built-in server + Vite + worker) | Local PostgreSQL | Local Redis (or `database` driver) | Local disk | `log` driver |
| Local integration | Docker Compose (nginx + php-fpm + Postgres + Redis) | Postgres container | Redis container | Local disk or MinIO-class container | Local mail-catcher container |
| CI | Ephemeral service containers | PostgreSQL 17 service container | `array`/`sync` drivers | Faked disk | `array` driver |
| Staging | Container runtime, 1 instance | Managed or self-hosted PostgreSQL 17 | Managed or self-hosted Redis 7 | S3-compatible bucket (staging) | Real transport, restricted recipients |
| Production | Container runtime, ≥2 instances | PostgreSQL 17, TLS + PITR | Redis 7, TLS + persistence | S3-compatible bucket (prod), versioned | Real transport, verified sender domain |

CI deliberately uses a PostgreSQL **service container** rather than the managed
production database: it is isolated, fast, quota-free, and the same engine
version, which is all that is needed to validate migrations and queries.

---

## 3. Local development

### Prerequisites

- PHP 8.3+ (production image uses 8.4) with `pdo_pgsql`, `bcmath`, `gd`, `intl`, `mbstring`, `zip`, `exif`, `pcntl`
- Node.js 22+, Composer 2
- PostgreSQL 15+ (17 recommended to match production)
- Redis 7+ (native, container, or a Windows-compatible Redis-protocol server)

### Setup

```bash
git clone <repo-url>
cd one-window-bayanihan
composer run setup
```

`setup` runs: `composer install` → copy `.env.example` to `.env` →
`php artisan key:generate` → `php artisan migrate --force` →
`npm install --ignore-scripts` → `npm run build`.

### Run

```bash
composer run dev
```

Starts three processes concurrently: application server (`:8000`), queue worker
(`queue:listen --tries=1 --timeout=0`), Vite dev server (`:5173`).

---

## 4. Environment variable contract

Group by capability, not by vendor. Names below are the canonical ones; the
legacy aliases noted still resolve for backward compatibility.

```env
# ── Application ───────────────────────────────────────────────
APP_NAME="One Window Bayanihan"
APP_ENV=production            # local | staging | production
APP_DEBUG=false               # MUST be false outside local
APP_KEY=                      # php artisan key:generate
APP_URL=https://<your-domain>

# ── Database (C3) ─────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=<postgres-host>
DB_PORT=5432
DB_DATABASE=one_window
DB_USERNAME=<role>
DB_PASSWORD=<secret>
DB_SSLMODE=require            # 'require' for any networked database; 'prefer' for local

# ── Object storage (C4), S3-compatible ────────────────────────
FILESYSTEM_DISK=object-storage
STORAGE_DRIVER=s3
STORAGE_ACCESS_KEY=<key>
STORAGE_SECRET_KEY=<secret>
STORAGE_REGION=ap-southeast-1
STORAGE_BUCKET=case-files
STORAGE_ENDPOINT=https://<object-storage-endpoint>
# Legacy aliases still honoured as fallbacks: SUPABASE_S3_* (see config/filesystems.php)

# ── Audit archive storage (C4) ────────────────────────────────
AUDIT_ARCHIVE_DISK=audit-archives
AUDIT_ARCHIVE_DRIVER=s3       # MUST be s3-compatible in production (immutability)
AUDIT_RETENTION_DAYS=365

# ── Cache / queue / session (C5) ──────────────────────────────
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database       # database-backed: shared across instances by default
SESSION_ENCRYPT=true
REDIS_CLIENT=phpredis
REDIS_HOST=<redis-host>
REDIS_PORT=6379
REDIS_PASSWORD=<secret>       # never null on a networked instance
# REDIS_URL=rediss://default:<secret>@<host>:6380   # single-URL form, TLS

# ── Mail (C6) ─────────────────────────────────────────────────
MAIL_MAILER=smtp              # or an HTTPS-API mailer where SMTP egress is blocked
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<user>
MAIL_PASSWORD=<secret>
MAIL_FROM_ADDRESS="noreply@<your-domain>"

# ── Edge / proxy (C2) ─────────────────────────────────────────
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16   # your ingress ranges

# ── Optional services ─────────────────────────────────────────
SENTRY_LARAVEL_DSN=           # C11 error ingest
TURNSTILE_ENABLED=false       # C13 bot protection
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
CLOUDINARY_URL=               # C12 avatar CDN
AI_CHATBOT_ENABLED=false
OPENROUTER_API_KEY=
```

Full inventory: `.env.example`. Anything absent there is not read by the app.

---

## 5. Deployment models

All models consume the same artefact: the image built from the project
`Dockerfile`. Pick the model your platform supports.

### 5.1 Container image (the portable baseline)

```bash
docker build -t one-window:$GIT_SHA .
docker push <your-registry>/one-window:$GIT_SHA
```

Multi-stage build:

1. **node-build** — Node 22, `npm ci && npm run build`, emits fingerprinted Vite assets to `public/build/`
2. **app** — PHP 8.4-fpm + nginx + supervisord

The runtime image contains:

- PHP extensions: `pdo_pgsql`, `bcmath`, `gd`, `intl`, `opcache`, `pcntl`, `exif`, `mbstring`, `zip`
- nginx listening on **8080** (the only port to expose)
- supervisord managing nginx, php-fpm, and the queue worker
- Healthcheck `curl http://127.0.0.1:8080/up`
- Hardening: `no-new-privileges`, `cap_drop: ALL` with a minimal `cap_add`
  (`FOWNER`, `SETGID`, `SETUID`), php-fpm as `www-data`, build-time
  `composer audit`, source assets stripped from the final layer

`docker/php/docker-entrypoint.sh` fixes storage permissions, warms
config/route/view caches, optionally runs migrations, then starts supervisord.

### 5.2 Compose-based deployment (single host, staging)

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed        # reference data only
docker compose logs -f app
```

Topology:

```
nginx (alpine)      :80  → reverse proxy → app:9000
app (php-fpm 8.4)        Laravel + queue worker + scheduler (supervisord)
db (postgres)            local database
redis (7-alpine)         cache, queue, OTP store
```

### 5.3 Managed container platform / PaaS (generic recipe)

Platform-agnostic steps — the vocabulary differs per product, the sequence does not:

1. Create a **container service** that builds from the repository `Dockerfile` (or pulls a pre-built tag from your registry).
2. Set the **listening port** to `8080`. Do not override the container start command; supervisord is the entrypoint.
3. Inject every variable from §4 as **runtime environment variables/secrets**.
4. Point the **health check** at `GET /up` (unauthenticated, no side effects). Allow 30–60 s for cold start.
5. Attach the **database**, **object storage**, and **Redis** endpoints from §1.
6. Run migrations as a **release/pre-deploy step** (§7), not on every container boot, once you scale past one instance.
7. Configure **autoscaling / instance count** ≥2 for production, and a **separate worker service** if you do not want workers inside web containers (§8).
8. Add a **scheduler** (C7): platform cron calling `php artisan schedule:run` each minute, or keep the in-container supervisor entry.

### 5.4 Kubernetes-class orchestrators

```
Deployment: web        replicas ≥2, container port 8080
                       readinessProbe  GET /up
                       livenessProbe   GET /up
                       envFrom: secretRef (§4)
Deployment: worker     replicas ≥1, command: php artisan queue:work redis --tries=3
CronJob:    scheduler  */1 * * * *  php artisan schedule:run
Job:        migrate    php artisan migrate --force   (helm pre-upgrade hook / init job)
Service + Ingress      TLS termination, forwards X-Forwarded-*
```

### 5.5 VM or bare metal

nginx (TLS + static files) → php-fpm 8.4 → application; systemd units for
`queue:work` and a per-minute `schedule:run` timer; PostgreSQL, Redis, and
object storage reachable over the network. Same env contract as §4.

---

## 6. Build pipeline

### Production build steps

```bash
npm ci --ignore-scripts
npm run build                                   # → public/build/ (fingerprinted)
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Vite fingerprints every asset; `public/build/manifest.json` maps logical names
to hashed filenames, so any CDN or ingress can cache assets indefinitely.

### Release sequence (any platform)

```
build image → run migrations → roll out instances → health-gate /up → smoke test → announce
```

The deploy *trigger* is the only platform-specific link in the chain. Keep it
behind indirection so switching platforms is a settings change:

| Pipeline variable | Meaning |
|---|---|
| `DEPLOY_API_URL` / deploy webhook URL | Endpoint the pipeline calls to start a rollout |
| `DEPLOY_API_TOKEN` | Credential for that endpoint |
| `DEPLOY_SERVICE_ID` | Target service/app identifier on the platform |
| `HEALTH_CHECK_URL` | `https://<env-domain>/up` — the gate the pipeline polls |

See `docs/CI_CD_GUIDE_v2.0.0.md` for the pipeline itself.

---

## 7. Migrations and zero-downtime policy

**Standing rules**

1. Migrations run as a **release step**, once per deploy, never concurrently from multiple instances.
2. Every migration must be **backward compatible with the currently running release** (expand → migrate → contract). Additive first; drop columns only in a later release.
3. Take and **verify** a database backup or snapshot before any destructive or backfilling migration.
4. `migrate:rollback` is a schema tool, not an application rollback. Data-restoring rollback = restore the snapshot.
5. Confirm pending work with `php artisan migrate:status` before and after.

### Coordinated maintenance runbook — case-category pivot

`2026_07_17_000001_create_case_category_pivot_table.php` is an undeployed
migration requiring a quiesced window. This is **not** a rolling migration.

1. Take and verify a PostgreSQL backup or snapshot; confirm the migration is pending (`php artisan migrate:status`).
2. Quiesce the application: stop or drain all old web instances and stop every queue worker, scheduler, or other process that can write cases. Keep old writers stopped until the migration, deployment, and initial reconciliation are complete — never run this migration while an old writer can create or change category assignments.
3. Run `php artisan migrate --force` against the target database. It creates `case_category`, adds its foreign keys, indexes, and pair-uniqueness constraint, then backfills one pivot row per existing non-null `cases.category_id`.
4. Deploy the release that reads and writes the pivot. `cases.category_id` remains a compatibility mirror only — it is not the canonical assignment store.
5. Before restarting any writer, reconcile pivot row counts and sampled assignments against the non-null legacy mirror.
6. Start web and queue processes, then verify category reads, writes, and filters before declaring the rollout complete.

Rollback of this migration only drops `case_category`; it neither restores pivot
assignments nor reverses the backfill. If rollback is required, stop the
incompatible release and restore the snapshot before redeploying the previous
application version.

---

## 8. Scaling model

The application is **stateless**; every scaling property follows from what is
externalised.

| Concern | Externalised to | Scaling consequence |
|---|---|---|
| Sessions | Database (`SESSION_DRIVER=database`) | Any instance serves any request; no sticky sessions needed |
| Cache | Redis | Shared warm cache; instances scale independently |
| Queue | Redis (`jobs` tables when degraded) | Add worker processes/containers to raise throughput |
| Uploads | S3-compatible object storage | Instances are disposable; no shared filesystem required |
| Audit archives | Object storage (immutable bundles) | Retention independent of instance lifecycle |
| Logs | stdout/stderr → platform collector | No local log dependency |
| Assets | Fingerprinted `public/build/` (CDN-cacheable) | Edge caching without invalidation |

**Horizontal scaling checklist**

- [ ] ≥2 web instances behind the ingress, health-gated on `/up`
- [ ] `TRUSTED_PROXIES` covers the ingress/load-balancer ranges (otherwise client IPs, rate limits, and IP allowlists misbehave)
- [ ] Migrations moved out of container boot into a release step
- [ ] Queue workers sized separately from web instances; one worker per container is acceptable but scale them by queue depth, not by web traffic
- [ ] Scheduler runs **exactly once** cluster-wide — never one per web replica
- [ ] Database connection count budgeted: `instances × php-fpm children` must stay under the server limit; use a connection pooler when it does not
- [ ] Redis sized for peak sessions/OTP/queue payloads; persistence (AOF) enabled so a restart does not drop queued jobs
- [ ] Object storage bucket lifecycle and versioning configured

**Vertical/first-step sizing:** 1 vCPU / 1 GB per web instance handles the
current caseload; scale php-fpm children with memory, not CPU, and watch
`/up` latency plus queue depth as the two primary saturation signals.

---

## 9. Operations

### Maintenance mode

```bash
php artisan down --secret="<bypass-token>"
php artisan up
```

Admin UI: `/admin/system/maintenance`. On multi-instance deployments prefer the
ingress-level maintenance page, or ensure the maintenance flag lives in a shared
store (`APP_MAINTENANCE_DRIVER=database` when instances must agree).

### Logs

- Application: `storage/logs/laravel.log` (daily rotation) **and** container stdout
- Admin UI viewer/download: `/admin/system/logs`
- Ship container output to the platform log collector; retain per the retention policy (C10)

### Queue

```bash
php artisan queue:monitor redis:default   # depth alerting
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

### Backup and restore

`scripts/` provides:

| Script | Purpose |
|---|---|
| `scripts/backup.sh` | `pg_dump` with timestamped filename |
| `scripts/restore-test.sh` | Restore a backup into a scratch database (restore drill) |
| `scripts/load-test.sh` | Load-testing utility |

Managed-database automatic backups do **not** replace a periodic *tested*
restore — restore drills are the evidence auditors ask for (§13).

### Common artisan commands

| Command | Purpose |
|---|---|
| `php artisan migrate --force` | Apply pending migrations (release step) |
| `php artisan db:seed` | Seed reference data |
| `php artisan config:cache` / `route:cache` / `view:cache` | Production caches |
| `php artisan queue:work redis` | Queue worker |
| `php artisan schedule:run` | Scheduler tick (per minute) |
| `php artisan chatbot:index` | Rebuild chatbot retrieval index |
| `php artisan audit:archive` / `audit:prune` / `audit:verify` | Audit retention and chain integrity |

---

## 10. Monitoring checklist

- [ ] `GET /up` returns 200 from outside the platform
- [ ] Database reachable over TLS; connection count well under the server limit
- [ ] Redis reachable and authenticated (`PING` → `PONG`)
- [ ] Queue worker alive; depth trending flat, `queue:failed` empty
- [ ] Scheduler ticking (verify a scheduled job's last-run timestamp)
- [ ] Mail delivering (check the `email_logs` table)
- [ ] Object storage read/write verified by a real upload
- [ ] TLS certificate valid and auto-renewing
- [ ] Error ingest receiving events (if C11 configured)
- [ ] Bot protection functional on login (if C13 enabled)
- [ ] Backup job succeeded within the RPO window; last restore drill recorded

---

## 11. Rollback

1. **Application:** redeploy the previous image tag (keep at least the last 5 tags in the registry — do not rely on a platform's build history as your only rollback path).
2. **Database:** if the release included a destructive or backfilling migration, restore from snapshot/PITR — see §7 rule 4.
3. Re-run the health gate on `/up`.
4. Watch error rate and queue depth for one full scheduler cycle before closing the incident.
5. Record the rollback in the change record (§13).

---

## 12. Platform binding inventory

Everything vendor-specific in this system is confined to the following places.
Switching platforms means changing these — not the application, and not this guide.

| Binding | Where it lives | How to switch |
|---|---|---|
| Deploy trigger (API/webhook call) | `.github/workflows/deploy-*.yml` — the deploy step and its `DEPLOY_*` repository secrets/variables | Replace the deploy step's endpoint and credential; the rest of the pipeline is provider-neutral |
| Health-gate URL | Pipeline variable `HEALTH_CHECK_URL` / `PRODUCTION_URL` | Point at the new environment domain |
| Database endpoint | `DB_*` environment values | Repoint host/credentials; keep PostgreSQL 17 and `DB_SSLMODE=require` |
| Object storage endpoint | `STORAGE_*` values (legacy `SUPABASE_S3_*` fallbacks in `config/filesystems.php`) | Repoint endpoint/bucket/keys; any S3-compatible service works |
| Cache/queue endpoint | `REDIS_*` / `REDIS_URL` | Repoint host, password, TLS scheme |
| Mail transport | `MAIL_MAILER` + transport credentials | SMTP or HTTPS-API mailer; see `docs/EMAIL_DELIVERY_v2.0.0.md` |
| Error ingest | `SENTRY_LARAVEL_DSN` | Hosted or self-hosted compatible endpoint |
| Avatar CDN | `CLOUDINARY_URL` | Repoint or drop back to object storage |
| Bot protection | `TURNSTILE_*` | Provider matching the middleware's verify API, or disable |

**Migration to a new platform — checklist**

- [ ] Verify the target against every capability row in §1; record accepted gaps and fallbacks
- [ ] Provision C3–C6 and load the env contract from §4 into the platform's secret store
- [ ] Restore a production-representative database dump into the new database; run `php artisan migrate:status`
- [ ] Copy object-storage contents (including audit archives) and re-verify `audit:verify`
- [ ] Deploy the same image tag currently in production; health-gate `/up`
- [ ] Run the smoke path: login + OTP email, case create with upload, referral, report export
- [ ] Confirm scheduler runs once cluster-wide and workers drain the queue
- [ ] Cut DNS with a low TTL; keep the old environment warm for one rollback window
- [ ] Update the deploy trigger bindings in §12 and the change record in §13

---

## 13. Standards-readiness check

This is not a certification artefact, so the compliance check applies. Items
below are what an ISO 9001 / ISO 27001 / SOC 2 / DPTM assessor will test against
this deployment strategy, and where each currently stands.

| Requirement | Standard reference | Status in this strategy | Action if gapped |
|---|---|---|---|
| Documented, repeatable deployment procedure | ISO 9001 8.5.1; SOC 2 CC8.1 | ✅ This guide, versioned with changelog | Keep version increments per change |
| Change authorisation before production release | ISO 27001 A.8.32; SOC 2 CC8.1 | ✅ Manual production trigger + approval gate (`CI_CD_GUIDE_v2.0.0.md`) | Retain approval evidence per run |
| Separation of environments | ISO 27001 A.8.31 | ✅ Distinct database, storage, Redis, and secrets per environment | Never share buckets or Redis across environments |
| Secrets not in source control | ISO 27001 A.8.24; SOC 2 CC6.1 | ✅ Runtime env injection only (C8) | Rotate on personnel change; record rotation |
| Encryption in transit | ISO 27001 A.8.24; DPTM data-protection | ✅ HTTPS ingress, `DB_SSLMODE=require`, TLS-capable Redis | Set `REDIS_SCHEME=tls`/`rediss://` where the provider offers it |
| Backup, restore, and tested recovery | ISO 27001 A.8.13; ISO 22301-aligned BCP | ⚠️ Backups defined; **restore drills must be scheduled and evidenced** | Schedule a periodic restore drill using `scripts/restore-test.sh` and log the result |
| Capacity and performance monitoring | ISO 27001 A.8.6 | ⚠️ Signals listed (§8, §10); thresholds and alert routing not yet defined | Define alert thresholds and on-call routing |
| Logging and monitoring of privileged actions | ISO 27001 A.8.15/A.8.16 | ✅ Audit chain + archive with retention (`AUDIT_RETENTION_DAYS`) | Ensure archive disk is S3-compatible in production (immutability) |
| Vulnerability management in the build | ISO 27001 A.8.8 | ✅ `composer audit` / `npm audit` in the image build and CI | Fail the build on high severity, not just report |
| Rollback and incident recovery | ISO 27001 A.5.24–A.5.26 | ✅ §11, plus snapshot-based data recovery | Keep ≥5 image tags; record every rollback |
| Supplier/subservice assurance | ISO 27001 A.5.19–A.5.22; SOC 2 CC9.2 | ⚠️ Deliberately **not** in this guide — vendor-neutral by design | Maintain named suppliers, contracts, and assurance evidence in the compliance register (`docs/compliance/`), which is where assessors expect them |

**Design decisions that would otherwise require audit-time rework, resolved here:**

- Audit archives must land on S3-compatible storage in production; a local disk
  fallback breaks the immutability claim (C4 / A.8.15).
- Sessions and queue state must be external before scaling past one instance;
  retrofitting that after go-live is a data-loss risk, not a config change.
- The scheduler must be singleton cluster-wide, otherwise retention and archive
  jobs double-run and corrupt retention evidence.

---

## 14. Changelog

| Version | Date | Change |
|---|---|---|
| 3.0.0 | 2026-07-27 | Structural overhaul to a platform-neutral strategy. Removed all hosting/managed-service vendor names in favour of technology and capability requirements. Added §1 platform capability contract with fallbacks, §2 environment matrix, §5 four deployment models (container, compose, managed platform, orchestrator, VM), §6 provider-agnostic release trigger contract, §7 zero-downtime migration policy, §8 explicit scaling model and checklist, §12 platform binding inventory with a platform-migration checklist, §13 standards-readiness check. Updated the environment contract to the canonical `STORAGE_*` / `object-storage` disk names (legacy `SUPABASE_S3_*` documented as fallbacks) and added audit-archive, scheduler, and TLS variables that v2.0.0 omitted. Retained the case-category pivot runbook verbatim. |
| 2.0.0 | 2026-07-11 | Previous revision (`DEPLOYMENT_GUIDE.md`): single named-vendor production path, vendor dashboard instructions, stale storage env names. |
