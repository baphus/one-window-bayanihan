# Redis Integration Guide

> **Version:** 2.1.0 | **Updated:** 2026-09-15 | **Status:** Implemented
> **Supersedes:** `REDIS_INTEGRATION_v2.0.0.md` (2026-07-27, status Planning)
> **Source of truth:** `config/database.php`, `config/cache.php`, `config/queue.php`, `config/session.php`, `.env.example`, `docker-compose.yml`, `docker/supervisord.conf`, `Dockerfile`, `composer.json`
> **Delta scope:** architecture, data-flow, performance, monitoring, troubleshooting, and fallback sections from v2.0.0 carry forward. This revision flips Planning → Implemented, corrects queues/toolchain, and replaces obsolete local-setup content.

## 0. What changed in v2.1.0 (delta)

| # | v2.0.0 said | Verified reality (2026-09-15) |
|---|---|---|
| D1 | **Status: Planning**; "switch requires installing Redis, enabling extension" | **Status: Implemented.** Compose ships `redis:7-alpine` (appendonly, 128 MB, allkeys-lru); `.env.example` + Compose default to `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis`; `phpredis 6.1.0` pinned in `Dockerfile`; CI asserts `redis` in `php -m` |
| D2 | Queues: `default` (+ `queue:work redis --tries=3`) | Workers consume **`default,notifications,heavy`** in-supervisord (`queue:work --queue=default,notifications,heavy --sleep=3 --tries=3 --timeout=30 --max-jobs=500 --max-time=3600`); `composer run dev` listens on `default,notifications`; Compose `queue` service runs `queue:listen --tries=3 --timeout=90`. Deploy payload runs `database` queue (override — see Deployment Guide v3.1.0 Annex 4A) |
| D3 | Step 1: WSL/Memurai/Docker-Desktop options; Step 2: PHP 8.3 DLL download walkthrough | **Replaced with the PHP 8.4 note below.** WSL distro steps, Memurai, and the PHP-8.3 DLL block are obsolete and removed |
| D4 | Sessions "remain on database driver" as future work | Re-affirmed as the standing decision: `SESSION_DRIVER=database` (config default **and** `.env` canonical **and** deploy payload). Redis session guidance stays documented as an option, not the posture |

## 1. Current state (Implemented)

| Component | Driver (local/Compose) | Driver (deployed container) | Code changes |
|---|---|---|---|
| OTP / general cache | Cache (redis, DB 1) | Cache (database) | None — `Cache` facade |
| Queue | redis | database | None — `ShouldQueue` |
| Session | database | database | None — stays on database driver |
| Rate limiting | Cache (redis, DB 1) | Cache (database) | None — `RateLimiter` facade |

Zero application code changes were ever required; the flip is pure configuration across three vars. Tests pin `array`/`sync`/`array` (`phpunit.xml`) and need no Redis.

## 2. Architecture (re-affirmed)

Redis DB separation unchanged: DB 0 `default` (queue/sessions/general, prefix `bayanihan-database-`) · DB 1 `cache` (OTP/rate limits, prefix `bayanihan-cache-`). `config/cache.php` `stores.redis` → `cache` connection (lock via `default`); `config/queue.php` `connections.redis` → `default` connection, `after_commit: true`; `config/session.php` driver `database`, lifetime 120, encrypt true.

## 3. Setup (current)

### Compose (canonical local path)

```yaml
redis:
  image: redis:7-alpine
  command: redis-server --appendonly yes --maxmemory 128mb
    --maxmemory-policy allkeys-lru --tcp-keepalive 60
```

App/queue/scheduler set `REDIS_HOST=redis` (default), `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=database`, with `depends_on: redis condition: service_healthy`. No per-service Redis setup beyond `.env`.

### PHP 8.4 note (replaces the old Steps 1–2)

- Local runtime floor is **PHP >= 8.4.1** with the `redis` extension (`REDIS_CLIENT=phpredis`). Verify with `php -m | findstr redis` / `php -r "echo extension_loaded('redis') ? 'phpredis OK' : 'NOT LOADED';"`.
- The container path needs no host extension work: the `Dockerfile` pins and installs `phpredis 6.1.0` (`ARG PHPREDIS_VERSION=6.1.0`, `pecl install`, `docker-php-ext-enable redis`), and `build-image.yml` fails the build if `redis` is absent from `php -m`.
- Native-Windows and PHP-8.3-specific extension instructions from earlier revisions are obsolete and intentionally not carried forward; use Compose (or any Redis 7+ endpoint meeting C5) with a PHP 8.4 toolchain.

### Switch drivers

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null        # never null on a networked instance
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
```

Then `php artisan config:clear && php artisan cache:clear && php artisan queue:restart`. Existing database sessions invalidate on driver change (one re-login).

### Run the worker

```bash
php artisan queue:listen --tries=3 --timeout=90                                   # dev / Compose queue service
php artisan queue:work --queue=default,notifications,heavy --sleep=3 --tries=3 --timeout=30 --max-jobs=500 --max-time=3600  # supervisord form
```

`--timeout=30` must stay under the supervisor `stopwaitsecs=35` so in-flight jobs finish before SIGKILL. Prefer `queue:work` in production (no per-job framework reload); bound with `--max-jobs/--max-time` against leaks.

## 4. Carried forward from v2.0.0 (unchanged)

Why-Redis analysis (§OTP/queue/session/rate-limit/general), performance tables (~30× OTP, blocking-pop queue, TTL sessions), C5 selection criteria (7+, password auth, TLS off-private-network, persistence, sizing, non-evicting queue keys, locality), TLS forms (`REDIS_SCHEME=tls` / `rediss://`), network isolation, data-sensitivity table, monitoring (`redis-cli INFO`, `LLEN bayanihan-database-queues:default`, `queue:monitor`, `queue:failed/retry/flush`), health checks, troubleshooting matrix, degraded-mode fallback to `database` drivers, always-on vs per-command-billed trade-offs, deployment checklist, and future optimisation sketches (dashboard/helpdesk/permission/chatbot caching, broadcasting).

## 5. Production posture note

Provision any Redis 7+ endpoint meeting C5; inject `REDIS_*` via the platform secret store. The current deploy payload runs cache+queue on `database` — moving the hosted environment to Redis is a payload + worker-topology change (re-prove the `--isolated` lock store and singleton scheduler), not an application change.

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 2.1.0 | 2026-09-15 | Status Planning → Implemented; queues corrected to `default,notifications,heavy` (supervisord) vs dev/Compose forms; WSL/Memurai/PHP-8.3-DLL block replaced with PHP 8.4 note; re-affirmed database sessions and deploy `database`-trio override. |
| 2.0.0 | 2026-07-27 | Platform-neutral provisioning rewrite (see its changelog). |
| 1.0.0 | 2026-07-13 | Previous revision (`REDIS_INTEGRATION.md`). |
