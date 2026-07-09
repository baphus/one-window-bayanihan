# One Window Bayanihan — Production Deployment Plan v2

> **Date:** 2026-07-09
> **Architecture:** Laravel 13 + Inertia/React 18 SPA
> **Target:** Render (Docker) + Supabase + Cloudinary

---

## 1. Architecture Decision Record

### Problem
Deploy a Laravel Inertia SPA for DMW Region VII. The proposed split (Render backend, Vercel frontend) needs evaluation.

### Decision: **Unified Render Docker deployment**

### Rationale

**Inertia is server-rendered.** Every page navigation hits a Laravel route that returns rendered HTML. The frontend is not a standalone SPA — it's a collection of React components rendered server-side through Inertia. Vite's output (`public/build/`) is just JS/CSS assets served by Nginx, not a deployable frontend.

Splitting Render + Vercel would require:
- Rewriting all routes from Inertia to REST/GraphQL
- Replacing `useForm()`, `router.get()`, `Link` with React Router + fetch
- Duplicating auth/session logic on both sides
- Months of work, no functional gain, higher latency

### What each service does

```
Browser
  │
  ├── Render (Docker) ─────────────► Laravel + Inertia SSR
  │     ├── Nginx (reverse proxy)
  │     ├── PHP-FPM (app server)
  │     ├── Queue Worker (async jobs)
  │     └── Scheduler (cron)
  │
  ├── Supabase ────────────────────► PostgreSQL 15 + Connection Pooler
  │
  ├── Supabase Storage ────────────► S3 bucket (case files, documents)
  │
  ├── Cloudinary ──────────────────► Image CDN (profile pictures)
  │
  └── SendGrid / SES ─────────────► SMTP (OTP, notifications)
```

---

## 2. Architecture Diagram

```
┌──────────────────────────────────────────────────────────┐
│                        Render                             │
│  ┌────────────────────────────────────────────────────┐  │
│  │              Docker Container                      │  │
│  │  ┌─────────┐  ┌──────────┐  ┌───────────┐        │  │
│  │  │ Nginx   │──│ PHP-FPM  │  │ Laravel   │        │  │
│  │  │ :8080   │  │ :9000    │  │ App       │        │  │
│  │  └────┬────┘  └──────────┘  └─────┬─────┘        │  │
│  │       │                           │              │  │
│  │  ┌────▼────┐              ┌───────▼──────┐      │  │
│  │  │ Vite    │              │ Queue Worker │      │  │
│  │  │ Assets  │              │ (supervisor) │      │  │
│  │  └─────────┘              └──────────────┘      │  │
│  │                         ┌──────────────────┐    │  │
│  │                         │ Scheduler (cron)  │    │  │
│  │                         └──────────────────┘    │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────────┬───────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
   ┌──────────┐  ┌────────────┐  ┌──────────┐
   │ Supabase │  │ Supabase   │  │Cloudinary│
   │PostgreSQL│  │Storage(S3) │  │ Image    │
   │  15 +    │  │ case-files │  │  CDN     │
   │ Pooler   │  │  bucket    │  │ avatars  │
   └──────────┘  └────────────┘  └──────────┘
```

---

## 3. Service Comparison

### Backend Hosting

| Feature | Render (Docker) | Fly.io | Railway | Laravel Vapor |
|---|---|---|---|---|
| **Docker support** | ✅ Native | ✅ Native | ✅ Native | ❌ Custom runtime |
| **PHP-FPM + Nginx** | ✅ Supervisor | ✅ Dockerfile | ✅ Dockerfile | ❌ Lambda-only |
| **Queue workers** | ✅ Background Worker (or same container via supervisor) | ✅ `fly deploy --process-group worker` | ✅ Worker service | ❌ Limited — queues timeout at 15 min |
| **CRON / Scheduler** | ✅ Cron Jobs (10 min min) | ✅ `fly cron` | ✅ Cron service | ❌ Must use external cron |
| **Auto-scaling** | ✅ Paid plans | ✅ Manual `fly scale` | ❌ Manual only | ✅ Lambda auto-scales |
| **Persistent storage** | ❌ Ephemeral disk | ✅ Volume mounts | ✅ Volume mounts | ❌ Lambda ephemeral |
| **Free tier** | ✅ (limited) | ✅ (3 VMs free) | ✅ ($5 credit) | ❌ $39/mo min |
| **Asia-Pacific region** | ✅ Singapore | ✅ Singapore, Tokyo | ✅ Singapore | ✅ ap-southeast-1 |
| **Gov compliance** | ❌ No PH-specific DC | ✅ Singapore | ✅ Singapore | ✅ Singapore |
| **Cold start** | ❌ None (always-on) | ❌ None (always-on) | ❌ None (always-on) | ⚠️ 2-5s cold start |
| **Monthly cost (est.)** | ~$15-25/mo | ~$10-20/mo | ~$10-20/mo | ~$49-79/mo |

**Winner: Render** — best balance of Laravel-native features (cron, queue, health checks, Docker) vs cost vs simplicity. The existing Dockerfile maps directly to Render's Docker runtime.

> **Runner-up:** Fly.io — cheaper, more global regions, same Docker workflow. Consider if Render's Asia-Pacific latency isn't acceptable.

### Database

| Feature | Supabase Pro | Render PostgreSQL | AWS RDS |
|---|---|---|---|
| **PostgreSQL version** | 15 (managed) | 16 (managed) | 16 (managed) |
| **Connection pooling** | ✅ Built-in PgBouncer | ❌ Requires self-host sidecar | ✅ RDS Proxy (extra $) |
| **Auto-backups** | ✅ Daily (7 day) + PITR | ✅ Daily (7 day) + PITR | ✅ Automated |
| **Storage (S3)** | ✅ Same project | ❌ Separate | ✅ S3 (extra $) |
| **Point-in-time recovery** | ✅ Pro plan | ✅ | ✅ |
| **APAC region** | ✅ Singapore | ✅ Singapore | ✅ Singapore |
| **Monthly cost** | ~$25/mo | ~$15/mo | ~$15/mo + $10 RDS Proxy |
| **Integration** | S3 storage + PostgreSQL in one dashboard | Separate services | Fully separate |

**Winner: Supabase** — the app already uses Supabase for both DB and S3 storage. Splitting them would mean two dashboards, two credential sets, two bills. Supabase's built-in connection pooling is critical for Render's connection-limited web service.

### CDN / Image Hosting

| Feature | Cloudinary | Supabase Storage (img) | AWS CloudFront + S3 |
|---|---|---|---|
| **Image transformations** | ✅ On-the-fly | ❌ Manual preprocessing | ✅ Lambda@Edge needed |
| **Profile pic integration** | ✅ Already coded | ❌ Would need rewrite | ❌ Would need rewrite |
| **PH compliance** | ✅ Global CDN | ✅ Singapore | ✅ Singapore |
| **Free tier** | ✅ 25GB storage, 25GB CDN | Included in Supabase plan | ❌ $0.085/GB |
| **Monthly cost** | ✅ Free tier sufficient | ✅ Included | ~$5-10/mo |

**Winner: Cloudinary** — already fully integrated via `CloudinaryAvatarService`, free tier is enough for gov profile pictures, on-the-fly image transformations are a bonus.

---

## 4. Cost Estimate (Monthly)

| Service | Plan | Cost |
|---|---|---|
| **Render** | Web Service (Starter, 512MB RAM, 1 CPU) | $7 |
| **Render** | Background Worker (same Docker image) | $7 |
| **Render** | Static Site (not needed) | $0 |
| **Supabase** | Pro (PostgreSQL + Storage + Auth) | $25 |
| **Cloudinary** | Free tier | $0 |
| **SendGrid** | Free tier (100 emails/day) | $0 |
| **Sentry** | Free tier (5k events/mo) | $0 |
| **Domain** | .gov.ph or .com | ~$10/yr |
| **Total** | | **~$39/mo** |

> 💡 **Pro tip:** For a lower budget, run the queue worker inside the same web container (Supervisor already does this in your Dockerfile), removing the $7/mo Background Worker. CPU/burst will suffer under load but works for low traffic.

---

## 5. Deployment Steps

### 5.1 Supabase Setup (one-time)

```bash
# 1. Create project in Supabase dashboard
#    Region: ap-southeast-1 (Singapore)
#    Database password: save securely

# 2. Enable connection pooling
#    Settings → Database → Connection Pooling → Enabled
#    Note the pooled connection string:
#    postgresql://project.xxxxx:password@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres

# 3. Create S3 storage bucket
#    Storage → New bucket → "case-files"
#    Allowed MIME: application/pdf,image/jpeg,image/png
#    Max file size: 10 MB
#    Public access: off (signed URLs)

# 4. Generate S3 credentials
#    Storage → Settings → S3 Connection → "Create new S3 Access Key"
#    Save these (shown once):
#      - Access Key
#      - Secret Key
#      - Endpoint: https://<project>.supabase.co/storage/v1/s3

# 5. Set up Row-Level Security (optional, for direct DB access)
#    SQL Editor → Run:
```

```sql
-- Enable RLS on key tables
ALTER TABLE cases ENABLE ROW LEVEL SECURITY;
ALTER TABLE referrals ENABLE ROW LEVEL SECURITY;
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;

-- Create RLS policy for agency data isolation
CREATE POLICY agency_isolation ON referrals
    FOR ALL
    USING (agcy_id = current_setting('app.agency_id')::uuid);
```

### 5.2 Cloudinary Setup (one-time)

```bash
# 1. Create free Cloudinary account
# 2. Get CLOUDINARY_URL from Dashboard
#    Example: cloudinary://api_key:api_secret@cloud_name
# 3. The app handles the rest via CloudinaryAvatarService
```

### 5.3 Render Setup

```bash
# Option A: Blueprint (render.yaml) — RECOMMENDED
# 1. Push code to GitHub
# 2. Connect repo to Render
# 3. Select "Blueprint" → point to render.yaml
# 4. Set manual secrets in Render Dashboard:
#    - SUPABASE_URL, SUPABASE_KEY, SUPABASE_SERVICE_KEY
#    - STORAGE_ACCESS_KEY, STORAGE_SECRET_KEY, STORAGE_ENDPOINT
#    - DB_USERNAME, DB_PASSWORD (Supabase pooled credentials)
#    - CLOUDINARY_URL
#    - APP_KEY (run `php artisan key:generate --show`)
#    - MAIL_USERNAME, MAIL_PASSWORD
#    - SENTRY_DSN (optional)

# Option B: Manual Web Service
# 1. New → Web Service → Docker
# 2. Name: owb-backend
# 3. Region: Singapore
# 4. Branch: main
# 5. Dockerfile Path: ./Dockerfile
# 6. Health Check Path: /up
# 7. Instance Type: Starter (512 MB)
```

### 5.4 Environment Variables

Set these in Render Dashboard (all, since Render Docker build doesn't use `.env`):

```ini
# ── App ──
APP_NAME=One Window Bayanihan
APP_ENV=production
APP_DEBUG=false
APP_KEY=[generate once: php artisan key:generate --show]

# ── Database (Supabase pooled) ──
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=project.xxxxxxxxxxxx
DB_PASSWORD=[your-supabase-password]
DB_SSLMODE=require

# ── Cache / Queue / Session (all database-driven — no Redis needed) ──
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# ── File Storage (Supabase S3) ──
FILESYSTEM_DISK=object-storage
STORAGE_ACCESS_KEY=[supabase-s3-access-key]
STORAGE_SECRET_KEY=[supabase-s3-secret-key]
STORAGE_REGION=ap-southeast-1
STORAGE_ENDPOINT=https://<project>.supabase.co/storage/v1/s3
STORAGE_BUCKET=case-files

# ── Cloudinary (avatars) ──
CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name

# ── Mail (SendGrid) ──
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=[sendgrid-api-key]
MAIL_FROM_ADDRESS=noreply@bayanihan-onewindow.gov.ph
MAIL_FROM_NAME="One Window Bayanihan"

# ── Monitoring ──
SENTRY_LARAVEL_DSN=[optional]

# ── AI (only if enabled) ──
AI_CHATBOT_ENABLED=false
```

### 5.5 First Deploy

```bash
# Render will:
# 1. Build Docker image (multi-stage: Node build → PHP runtime)
# 2. Start container with CMD: supervisord
#    This starts: Nginx (:8080) + PHP-FPM + Queue Worker + Scheduler
# 3. Run health check on /up

# Post-deploy (one-time via Render Shell or SSH):
php artisan migrate --force
php artisan db:seed --force
```

---

## 6. Dockerfile Optimizations

The existing `Dockerfile` is solid. These optimizations are already in place:

| Optimization | Status | Detail |
|---|---|---|
| Multi-stage build | ✅ | Node → PHP keeps image small |
| OPCache + JIT | ✅ | `docker/php/php.ini` |
| Nginx static asset caching | ✅ | 1-year immutable for build assets |
| Laravel caching on boot | ✅ | `config:cache`, `route:cache`, `view:cache`, `event:cache` |
| Health check | ✅ | `GET /up` (Laravel built-in) |
| Supervision | ✅ | Supervisor manages all 4 processes |
| Security hardening | ✅ | No dangerous PHP functions, `open_basedir` set, hidden files denied |

One improvement to consider:

```dockerfile
# Add this to Dockerfile after the COPY --from=node-build line
# ── Remove Vite dev dependencies from final image ──
# (already present: rm -rf node_modules resources/js resources/css ...)
```

Already there at line 93. No changes needed.

---

## 7. Performance Optimization Recommendations

### 7.1 Switch to Redis (Optional, Recommended for Production)

Current config uses `database` for cache, queue, and sessions — all hitting the same Supabase PostgreSQL, which adds DB load and keeps connections busy.

```ini
# Upgrade to Render Redis ($7/mo extra):
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=[render-redis-host]
REDIS_PORT=6379
```

**Benefit:** 10-20x faster cache lookups, queue processing, and session reads. Reduces Supabase connection pool pressure.

**Trade-off:** $7/mo extra. The app works fine with database driver for low traffic.

### 7.2 Content Delivery

| Asset | Served by | Optimization |
|---|---|---|
| Vite build (JS/CSS) | Render Nginx | Already immutable-cached (1y) |
| Images (profile pics) | Cloudinary CDN | Already configured |
| Case file downloads | Supabase Storage | Signed URLs (CDN-ready) |
| Fonts | Material Symbols (Google) | External, already cached |

### 7.3 Application-Level

| Tuning | Already done | Recommendation |
|---|---|---|
| OPCache JIT | ✅ `tracing` mode, 100MB buffer | Perfect for Laravel 13 |
| Config caching | ✅ On boot in entrypoint | Good |
| Route caching | ✅ On boot | Verify no closure routes |
| View caching | ✅ On boot | Good |
| Event caching | ✅ On boot | Good |
| DB connection pooler | ✅ Supabase pooler | Good |
| Rate limiting | ✅ In `AppServiceProvider` | 60 rpm API, 10 rpm auth |

---

## 8. Alternative: Cheaper/Lighter Stack

If budget is critical (< $20/mo):

| Service | Replacement | Saving |
|---|---|---|
| **Render Web** ($7) | Keep (required) | — |
| **Render Worker** ($7) | Remove; queue runs in web container (already done via Supervisor) | +$7/mo |
| **Supabase Pro** ($25) | Supabase Free (500MB DB, 1GB storage) | +$25/mo |
| **Cloudinary** ($0) | Keep free tier | $0 |
| **SendGrid** ($0) | Keep free tier | $0 |
| **Total** | **$7/mo** | **$32/mo saved** |

**Supabase Free limits:**
- 500 MB database (enough for this app for a while)
- 1 GB file storage
- 50,000 monthly active users
- 5 GB bandwidth
- No PITR (7-day backup only)

---

## 9. Rollback & Recovery

| Scenario | Action |
|---|---|
| **Bad deploy** | Render Dashboard → Deploy → Rollback to previous |
| **DB corruption** | Supabase → Database → Backups → Restore (PITR) |
| **Full disaster** | 1. Rollback Render deploy
2. Restore Supabase DB backup
3. Restore Storage from S3 backup |
| **Config mistake** | Edit env vars in Render Dashboard, service auto-restarts |

### Recovery Procedures

```
DB Corruption:
  Supabase Dashboard → Database → Backups → Choose timestamp → Restore
  RPO: 2 min (PITR on Pro), 24h (daily backup on Free)

Full Outage:
  1. git revert HEAD && git push
  2. Render auto-deploys previous working version
  3. If DB also affected: PITR restore
  4. Verify: health check + login flow

Storage Loss:
  Supabase Storage is S3-backed (99.999999999% durability)
  Enable cross-region replication if required by policy
```

---

## 10. Monitoring & Alerts

| What | How | Alert when |
|---|---|---|
| **App down** | Render health check | 3 consecutive failures |
| **5xx errors** | Sentry + Render logs | Spike > 1% of requests |
| **Failed jobs** | `failed_jobs` table + `php artisan queue:monitor` | Any failed job |
| **DB CPU** | Supabase Dashboard → Database → Monitoring | > 80% sustained |
| **Storage** | Supabase Dashboard → Storage | > 80% capacity |

---

## 11. CI/CD Pipeline (Optional)

Add `.github/workflows/deploy.yml` for automated deployment:

```yaml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: bayanihan_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: secret
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pgsql, mbstring, xml, gd
      - run: composer install --no-interaction
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan test

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to Render
        run: |
          curl -X POST \
            -H "Authorization: Bearer ${{ secrets.RENDER_DEPLOY_KEY }}" \
            https://api.render.com/v1/services/${{ secrets.RENDER_SERVICE_ID }}/deploys
```

---

## 12. Summary

| Decision | Choice | Cost | Rationale |
|---|---|---|---|
| **Backend** | Render Docker (Web + Supervisord) | $7/mo | Dockerfile-ready, supervisor runs 4 processes in one container |
| **Queue** | Same container (via Supervisor) | $0 | Web process includes queue worker already |
| **Database** | Supabase PostgreSQL + Pooler | $25/mo | Already integrated; pooler handles Render connections |
| **File Storage** | Supabase S3 bucket | Included | Already configured as `object-storage` disk |
| **Image CDN** | Cloudinary | $0 | Already coded in `CloudinaryAvatarService`, free tier |
| **Email** | SendGrid | $0 | 100 emails/day free |
| **Monitoring** | Sentry | $0 | 5k events/mo free |
| **Total** | | **~$32/mo** | |

### What NOT to do

❌ **Do not split Render + Vercel** — Inertia needs Laravel to render pages. The split would require a full rewrite to a separate SPA with no benefit.

❌ **Do not add a separate queue worker on Render** — Your Dockerfile's Supervisor already runs `queue:listen` and `schedule:work` alongside Nginx and PHP-FPM. One container does it all.

❌ **Do not switch to SQLite** — The app uses PostgreSQL functions (`to_char`, `EXTRACT`, `age()`) that don't exist in SQLite. Tests rely on Postgres.

✅ **Do add Redis later** when traffic grows — reduces DB load for sessions/cache/queue.
