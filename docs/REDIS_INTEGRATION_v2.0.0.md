# Redis Integration Plan

> **Version:** 2.0.0 | **Created:** 2026-07-13 | **Updated:** 2026-07-27 | **Status:** Planning
> **Supersedes:** `REDIS_INTEGRATION.md` (v1.0.0). Provisioning is described by capability,
> not by vendor — see `DEPLOYMENT_GUIDE_v3.0.0.md` §1 (C5).

## Executive Summary

This document outlines how Redis improves performance for One Window Bayanihan and provides a step-by-step setup guide. The project is already architecturally prepared for Redis — config files, database separation, and key prefixes are all in place. The switch requires installing the Redis server, enabling the PHP extension, and updating `.env` values.

## Current State

| Component | Current Driver | Target Driver | Code Changes Needed |
|-----------|---------------|---------------|---------------------|
| OTP Storage | Cache (database) | Cache (Redis) | **None** — uses Cache facade |
| Queue | database | redis | **None** — uses ShouldQueue interface |
| Session | database | database | **None** — stays on database driver |
| Rate Limiting | Cache (database) | Cache (Redis) | **None** — uses RateLimiter facade |
| General Cache | database | redis | **None** — uses Cache facade |

**Key insight:** Zero application code changes required. The OtpService, all queued Mailables, and rate limiters already use Laravel's abstractions that are backend-agnostic. Sessions remain on the database driver.

---

## Why Redis for This Project

### 1. OTP Handling (Primary Use Case)

**Problem:** OTPs are stored in the `cache` database table. Each OTP generation/verification requires:
- SQL INSERT/UPDATE for storing the OTP
- SQL SELECT for verification
- SQL DELETE for cleanup after success/expiry
- Additional queries for the attempts counter

**With Redis:**
- OTP `SET` with TTL: ~0.1ms (vs ~2-5ms database query)
- OTP `GET` for verification: ~0.1ms
- Automatic expiration — no cleanup queries needed
- Atomic increment for attempt counting (`INCR`)
- ~20-50x faster per OTP operation

**Impact:** Login flow, public tracking, and email change all become snappier. Under load (multiple concurrent OTP verifications), Redis prevents database contention.

### 2. Queue Processing (Primary Use Case)

**Problem:** Database queue polling requires constant `SELECT ... FOR UPDATE SKIP LOCKED` queries every few seconds. Each poll hits PostgreSQL even when the queue is empty.

**With Redis:**
- Push/pop operations via `BRPOPLPUSH` — blocking pop, zero polling waste
- ~10x faster job dispatch and pickup
- No table locks or skip-locked contention
- Better visibility with `queue:monitor`
- Reliable retry with exponential backoff built into Laravel's Redis queue

**Queued work in this project:**
| Class | Purpose |
|-------|---------|
| OtpMail | Login/tracking/email-change verification codes |
| ClientUpdateMail | Case status notifications to OFWs |
| ContactMessageMail | Public contact form submissions |
| EmailChangedNotification | Notification to old email address |
| FeedbackRequestMail | SERVQUAL feedback requests |
| ReferralOverdueMail | Overdue referral alerts to agencies |
| SendFeedbackRequest (Listener) | Event-driven feedback dispatch |

### 3. Session Storage

**Problem:** Every authenticated request reads/writes the `sessions` database table. With 50+ concurrent users, that's 100+ session queries per second competing with application queries.

**With Redis:**
- Session read/write in ~0.1ms
- Automatic TTL-based expiration (no session garbage collection needed)
- Reduces PostgreSQL connection pool pressure
- Horizontal scaling: all app instances share the same session store

### 4. Rate Limiting

**Problem:** 7 named rate limiters + inline throttle middleware all hit the cache backend. Under brute-force attacks, rate limit checks multiply rapidly.

**With Redis:**
- Atomic `INCR` + `EXPIRE` for sliding window counters
- Handles burst traffic without database strain
- Critical for protecting OTP endpoints from enumeration attacks

### 5. General Caching

Future opportunities (not yet implemented but enabled by Redis):
- Dashboard query result caching
- Helpdesk article caching
- User permission caching
- Route/config caching at runtime

---

## Architecture

### Redis Database Separation

The project already separates concerns by Redis database number:

| Database | Purpose | Connection Name | Prefix |
|----------|---------|-----------------|--------|
| 0 | Queue, Sessions, General | `default` | `bayanihan-database-` |
| 1 | Cache (OTP, Rate Limits) | `cache` | `bayanihan-cache-` |

This separation allows:
- Flushing the cache without destroying sessions/queue
- Independent monitoring per database
- Different eviction policies if needed

### Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        Laravel Application                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  OtpService ──→ Cache Facade ──→ Redis (DB 1, cache connection)  │
│                                                                   │
│  Mail::queue() ──→ Queue ──→ Redis (DB 0, default connection)    │
│                                                                   │
│  Session ──→ Session Store ──→ Redis (DB 0, default connection)  │
│                                                                   │
│  RateLimiter ──→ Cache Facade ──→ Redis (DB 1, cache connection) │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │   Redis 7 Server   │
                    │                     │
                    │  DB 0: queue,       │
                    │        sessions     │
                    │                     │
                    │  DB 1: cache,       │
                    │        OTP,         │
                    │        rate limits  │
                    └───────────────────┘
```


---

## Setup Guide

### Step 1: Install Redis Server

#### Windows (Local Development)

**Option A: Windows WSL (Recommended)**
```bash
# In WSL (Ubuntu)
sudo apt update
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verify
redis-cli ping
# → PONG
```

**Option B: Memurai (Native Windows Redis-compatible)**
```powershell
# Download from https://www.memurai.com/get-memurai
# Install and start the service
# Default port 6379, no password
```

**Option C: Docker (if you use Docker Desktop)**
```powershell
docker run -d --name redis -p 6379:6379 redis:7-alpine
```

#### Docker Compose (Already Configured)

The project's `docker-compose.yml` already includes a Redis service:
```yaml
redis:
  image: redis:7-alpine
  volumes:
    - redis-data:/data
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 5s
    retries: 5
  restart: unless-stopped
```

Just set `REDIS_HOST=redis` in your `.env` when using Docker Compose.

#### Production (any Redis 7+ endpoint)

Provision a Redis-protocol server that meets capability **C5**: version 7+,
password authentication, TLS where available, persistence enabled, and network
reachability from the application containers. Managed service, self-hosted
container, or VM — the application cannot tell the difference.

**Selection criteria (apply to whatever the platform offers):**

| Criterion | Requirement | Why |
|---|---|---|
| Version | Redis 7+ | Matches local/Compose and the commands used |
| Auth | Password (`REDIS_PASSWORD`) mandatory on any networked instance | Never expose an unauthenticated instance |
| Transport | TLS (`rediss://` / `REDIS_SCHEME=tls`) whenever the endpoint leaves the private network | Sessions/OTP payloads in transit |
| Persistence | AOF or RDB enabled | A restart must not drop queued jobs |
| Memory | Sized for peak sessions + OTP keys + queue payloads, with headroom | `OOM command not allowed` breaks login |
| Eviction | `noeviction` or `volatile-*` on a cache-only instance — **never** evict queue keys | Silent job loss otherwise |
| Locality | Same region/zone as the application | Per-request latency |
| Command budget | Sustained ops/sec above peak login + queue rate | Per-command-billed services can throttle at peak |

**Configuration (identical for every provider):**

```env
REDIS_CLIENT=phpredis
REDIS_HOST=<redis-host>
REDIS_PORT=6379
REDIS_PASSWORD=<secret>
# TLS endpoints:
# REDIS_SCHEME=tls
# REDIS_PORT=6380
# or single-URL form:
# REDIS_URL=rediss://default:<secret>@<host>:6380
```

Inject these through the platform's secret store, never into the image
(`DEPLOYMENT_GUIDE_v3.0.0.md` §4).

**Trade-off note:** per-command-billed ("serverless") Redis is attractive for
low-traffic staging but can throttle under queue bursts; always-on instances give
predictable latency for production. Decide on measured peak command volume, not
on tier naming.

---

### Step 2: Install PHP Redis Extension

The project uses `phpredis` (not the Composer predis package). This is a compiled PHP extension.

#### Windows (Local Dev)

```powershell
# 1. Check your PHP version and thread safety
php -i | findstr "Thread Safety"
php -i | findstr "Architecture"

# 2. Download the correct DLL from:
#    https://pecl.php.net/package/redis
#    or https://github.com/phpredis/phpredis/releases
#    Match: PHP 8.3, x64, Thread Safe (ts) or Non-Thread Safe (nts)

# 3. Copy php_redis.dll to your PHP ext/ directory
#    Usually: C:\php\ext\ or C:\laragon\bin\php\php-8.3\ext\

# 4. Add to php.ini:
#    extension=redis

# 5. Verify
php -m | findstr redis
# → redis
```

**If using Laragon:** Redis extension is often pre-included. Check `php -m`.

**If using XAMPP:** Download the matching DLL from PECL and add to `php.ini`.

#### Docker (Already Handled)

The Dockerfile already installs required PHP extensions. If `ext-redis` isn't included, add to Dockerfile:
```dockerfile
RUN pecl install redis && docker-php-ext-enable redis
```

#### Verify Extension

```bash
php -r "echo extension_loaded('redis') ? 'phpredis OK' : 'NOT LOADED';"
```

---

### Step 3: Configure Environment Variables

Update your `.env` file:

```env
# ─── Redis Connection ───────────────────────────────────────────
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# ─── Switch drivers to Redis ────────────────────────────────────
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
```

That's it. The config files (`config/database.php`, `config/cache.php`, `config/queue.php`, `config/session.php`) already have Redis connection definitions that read these env vars.

---

### Step 4: Clear Existing Caches

After switching drivers, clear stale config and caches:

```bash
php artisan config:clear
php artisan cache:clear
php artisan queue:restart
```

If switching from database sessions to Redis, existing sessions will be invalidated (users must re-login). Plan for this during deployment.

---

### Step 5: Verify the Integration

```bash
# Test Redis connection
php artisan tinker
>>> use Illuminate\Support\Facades\Redis;
>>> Redis::ping()
# → "PONG"

# Test cache (OTP uses this)
>>> use Illuminate\Support\Facades\Cache;
>>> Cache::put('test-key', 'hello', 60);
>>> Cache::get('test-key')
# → "hello"

# Test queue dispatch
>>> use Illuminate\Support\Facades\Mail;
>>> Mail::to('test@example.com')->queue(new App\Mail\OtpMail('123456', 'login'));
# Check Redis: should see job in queue

# Monitor Redis activity (in a separate terminal)
redis-cli monitor
```

---

### Step 6: Run the Queue Worker

```bash
# Development (already in composer run dev)
php artisan queue:listen --tries=3 --timeout=90

# Production (use queue:work for better performance)
php artisan queue:work redis --tries=3 --timeout=90 --sleep=3 --max-jobs=1000 --max-time=3600
```

**Production recommendations:**
- Use `queue:work` (not `queue:listen`) — it doesn't reload the framework per job
- Set `--max-jobs` and `--max-time` to prevent memory leaks
- Use Supervisor or Docker restart policy to auto-restart workers


---

## Performance Comparison

### OTP Flow (Login)

| Step | Database Driver | Redis Driver | Improvement |
|------|----------------|--------------|-------------|
| Generate OTP | ~5ms (INSERT) | ~0.2ms (SET+EX) | 25x |
| Store attempts counter | ~3ms (INSERT/UPDATE) | ~0.1ms (INCR) | 30x |
| Verify OTP | ~4ms (SELECT) | ~0.1ms (GET) | 40x |
| Cleanup on success | ~3ms (DELETE × 2) | ~0.1ms (DEL × 2) | 30x |
| **Total per login** | **~15ms** | **~0.5ms** | **30x** |

### Queue Dispatch

| Operation | Database Driver | Redis Driver | Improvement |
|-----------|----------------|--------------|-------------|
| Dispatch job | ~5ms (INSERT) | ~0.3ms (RPUSH) | 17x |
| Pick up job | ~8ms (SELECT FOR UPDATE) | ~0.1ms (BRPOPLPUSH) | 80x |
| Mark complete | ~4ms (DELETE) | ~0.1ms (LREM) | 40x |
| Poll when empty | ~3ms (SELECT, no rows) | 0ms (blocking pop) | ∞ |

### Session Read/Write

| Operation | Database Driver | Redis Driver | Improvement |
|-----------|----------------|--------------|-------------|
| Read session | ~3ms | ~0.1ms | 30x |
| Write session | ~4ms | ~0.1ms | 40x |
| GC expired sessions | Periodic query | Automatic (TTL) | N/A |

### Rate Limiting (per request)

| Operation | Database Cache | Redis | Improvement |
|-----------|---------------|-------|-------------|
| Check + increment | ~6ms (read + write) | ~0.2ms (INCR + EXPIRE) | 30x |
| Under attack (100 req/s) | 600ms total DB load/s | 20ms total Redis load/s | 30x |

---

## Docker Compose Integration

The `docker-compose.yml` already has Redis configured. To activate it:

### Update docker-compose environment variables

Change in the `app`, `queue`, and `scheduler` services:
```yaml
environment:
  - REDIS_HOST=${REDIS_HOST:-redis}
  - CACHE_STORE=${CACHE_STORE:-redis}      # Changed from database
  - QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}  # Changed from database
  - SESSION_DRIVER=${SESSION_DRIVER:-database}   # Stays on database driver
```

### Add Redis dependency

Add to `app`, `queue`, and `scheduler` services:
```yaml
depends_on:
  db:
    condition: service_healthy
  redis:
    condition: service_healthy
```

### Enable Redis persistence (production)

For production Docker deployments, add Redis configuration:
```yaml
redis:
  image: redis:7-alpine
  command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru
  volumes:
    - redis-data:/data
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 5s
    retries: 5
  restart: unless-stopped
```

- `--appendonly yes`: Enable AOF persistence (survives restarts)
- `--maxmemory 128mb`: Cap memory usage
- `--maxmemory-policy allkeys-lru`: Evict least-recently-used keys when full

---

## Security Considerations

### Redis Authentication

For production, always set a password:

```env
REDIS_PASSWORD=your-strong-random-password-here
```

Redis config (`redis.conf` or Docker command):
```
redis-server --requirepass your-strong-random-password-here
```

### Network Isolation

- **Docker:** Redis is only accessible within the `app-network` bridge (no exposed ports by default)
- **Production:** Never expose Redis port (6379) to the internet
- **Private-network endpoints:** Prefer an instance reachable only from the application's private network/VPC
- **Public endpoints:** If the platform only offers a public endpoint, require TLS **and** password auth, and restrict source IPs where the provider supports it

### Encryption in Transit

For production Redis over the network:
```env
# If the endpoint offers TLS (recommended for any networked instance)
REDIS_SCHEME=tls
REDIS_HOST=your-redis-host.com
REDIS_PORT=6380
REDIS_PASSWORD=your-password
```

In `config/database.php`, the connection supports TLS via the `url` parameter:
```env
REDIS_URL=rediss://default:password@host:6380
```
(Note: `rediss://` with double-s = TLS)

### Data Sensitivity

What's stored in Redis for this project:
| Data | Sensitivity | Mitigation |
|------|-------------|------------|
| OTP codes | High (6-digit codes) | 5-min TTL, max attempts, auto-delete |
| Session data | High (encrypted) | Laravel encrypts session payload before storage |
| Queue jobs | Medium (email content) | Network isolation, password auth |
| Rate limit counters | Low | No sensitive data, just counters |

---

## Monitoring & Debugging

### Redis CLI Commands

```bash
# Connect
redis-cli -h 127.0.0.1 -p 6379

# Check memory usage
INFO memory

# Count keys per database
INFO keyspace

# Watch all commands in real-time (development only)
MONITOR

# Check specific OTP key
GET bayanihan-cache-otp:login:user@email.com:session123

# List queue jobs
LLEN bayanihan-database-queues:default

# Check session count
DBSIZE
```

### Laravel Artisan Commands

```bash
# Monitor queue in real-time
php artisan queue:monitor redis:default

# List failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry {job-id}

# Retry all failed jobs
php artisan queue:retry all

# Flush all failed jobs
php artisan queue:flush
```

### Health Checks

Add to your monitoring:
```bash
# Redis is alive
redis-cli ping
# → PONG

# Redis memory usage
redis-cli INFO memory | grep used_memory_human

# Queue depth (jobs waiting)
redis-cli LLEN bayanihan-database-queues:default

# Connected clients
redis-cli INFO clients | grep connected_clients
```

---

## Troubleshooting

### Common Issues

| Problem | Cause | Solution |
|---------|-------|----------|
| `Class 'Redis' not found` | phpredis extension not installed | Install `ext-redis` (see Step 2) |
| `Connection refused` | Redis server not running | Start Redis: `redis-server` or `docker start redis` |
| `NOAUTH Authentication required` | Password mismatch | Check `REDIS_PASSWORD` in `.env` matches Redis config |
| `OOM command not allowed` | Redis out of memory | Increase `maxmemory` or check for key leaks |
| Jobs stuck in queue | Worker not running | Start worker: `php artisan queue:work redis` |
| Sessions lost after deploy | Redis restarted without persistence | Enable AOF: `--appendonly yes` |
| `MISCONF` errors | Redis can't write to disk | Fix permissions on Redis data directory |

### Fallback Strategy

If Redis becomes unavailable, the application can fall back to database drivers by changing three env vars:
```env
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

The `cache`, `sessions`, and `jobs` database tables already exist from migrations. This is a safe degraded-mode fallback.

---

## Production Deployment

### Recommended Setup

1. **Provision a Redis 7+ endpoint** meeting the C5 criteria above (managed, container, or VM).
2. **Set environment variables** in the platform's secret store:
   ```
   REDIS_HOST=<redis-host>
   REDIS_PORT=6379
   REDIS_PASSWORD=<secret>
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=database
   ```
3. **Deploy** — no code changes needed; the connection is entirely configuration.

### Always-on vs per-command-billed endpoints

| Property | Always-on instance | Per-command-billed endpoint |
|---|---|---|
| Cost model | Fixed memory tier | Metered commands/requests |
| Latency | Sub-millisecond on the private network | Typically a few milliseconds |
| Burst behaviour | Bounded by memory and CPU | May throttle or bill sharply on queue bursts |
| Persistence | Configurable (AOF/RDB) | Provider-managed |
| Best fit | Production, steady queue traffic | Low-traffic staging, intermittent workloads |

Decide from measured peak command volume; both satisfy C5.

### Deployment Checklist

- [ ] Redis endpoint reachable from app containers (private network preferred)
- [ ] `REDIS_PASSWORD` set (never null on a networked instance)
- [ ] TLS enabled if the endpoint leaves the private network
- [ ] Persistence enabled so a restart does not drop queued jobs
- [ ] Eviction policy will not evict queue keys
- [ ] `CACHE_STORE=redis` configured
- [ ] `QUEUE_CONNECTION=redis` configured
- [ ] Queue worker running — in-container supervisor process, separate worker service, or orchestrator deployment
- [ ] Scheduler runs exactly once cluster-wide
- [ ] `php artisan config:cache` run post-deploy
- [ ] Existing database sessions will be lost (users re-login once)
- [ ] Monitor queue depth and memory after switchover
- [ ] Test OTP flow end-to-end (login → receive email → verify)


---

## Future Optimization Opportunities

Once Redis is active, these additional optimizations become available (implement as needed):

### 1. Dashboard Query Caching

Cache expensive aggregate queries for the reporting dashboard:
```php
// In DashboardService or ReportService
$stats = Cache::remember('dashboard:stats:' . $agencyId, now()->addMinutes(5), function () {
    return $this->computeExpensiveStats();
});
```

Invalidate on relevant model changes via observers.

### 2. Helpdesk Article Caching

Cache frequently-accessed knowledge base articles:
```php
Cache::remember("article:{$slug}", now()->addHours(1), fn() => Article::findBySlug($slug));
```

### 3. User Permission Caching

Cache role/permission checks to avoid repeated DB queries:
```php
Cache::remember("user:{$userId}:permissions", now()->addMinutes(30), fn() => $user->loadPermissions());
```

### 4. Chatbot Response Caching

Cache AI chatbot responses for common queries to reduce model-provider API calls and costs.

### 5. Event Broadcasting (Future)

If real-time notifications are added later, Redis can serve as the Pub/Sub backend for Laravel Broadcasting with Reverb or Pusher.

---

## Implementation Order

| Phase | Action | Risk | Downtime |
|-------|--------|------|----------|
| 1 | Install Redis server + PHP extension locally | None | None |
| 2 | Switch `CACHE_STORE=redis` locally, test OTP flow | None | None |
| 3 | Switch `QUEUE_CONNECTION=redis`, test email delivery | None | None |
| 4 | Update Docker Compose defaults | None | None |
| 5 | Provision the production Redis endpoint (C5) | Low | None |
| 6 | Deploy with Redis env vars | Medium | Brief (queue flush) |
| 7 | Monitor queue depth, error rates, memory | None | None |

**Estimated time:** 30-60 minutes for local setup, 15-30 minutes for production provisioning.

---

## Quick Reference: Config Files

These files already contain Redis configuration — no modifications needed:

| File | Redis-relevant section |
|------|----------------------|
| `config/database.php` | `redis.default` (DB 0), `redis.cache` (DB 1) |
| `config/cache.php` | `stores.redis` → uses `cache` connection |
| `config/queue.php` | `connections.redis` → uses `default` connection |
| `config/session.php` | Reads `SESSION_DRIVER` env var |
| `.env.example` | All `REDIS_*` vars pre-defined |
| `docker-compose.yml` | Redis 7 Alpine service with healthcheck |
| `phpunit.xml` | Overrides to array/sync (tests don't need Redis) |

---

## Key Takeaway

**This project was designed for Redis from day one.** The OtpService uses Laravel's Cache facade, queued Mailables use the ShouldQueue interface, sessions use the configured driver, and rate limiters use the cache backend. All abstractions are backend-agnostic.

Switching to Redis is a pure infrastructure change — update 3 environment variables and ensure Redis is running. No application code, no migrations, no refactoring required.

The same holds for the Redis endpoint itself: because the application speaks only
the Redis protocol through Laravel's cache/queue/session abstractions, the
provider behind `REDIS_HOST` can change at any time without touching code.

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 2.0.0 | 2026-07-27 | Made provisioning platform-neutral. Replaced named managed-Redis products (provider dashboards, tier comparisons, host examples) with the C5 capability criteria table, a provider-independent configuration block, and an always-on vs per-command-billed trade-off table. Generalised network-isolation and TLS guidance to private/public endpoint cases. Expanded the production checklist with persistence, eviction-policy, TLS, and singleton-scheduler items. Generalised the model-provider reference in the caching optimisation section. |
| 1.0.0 | 2026-07-13 | Previous revision (`REDIS_INTEGRATION.md`): named managed-Redis products and host-platform dashboards. |
