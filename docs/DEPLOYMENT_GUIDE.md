# Deployment Guide

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Source:** `Dockerfile`, `docker-compose.yml`, `composer.json`

## Deployment Options

| Environment | Method | Database |
|-------------|--------|----------|
| Production | Render (Docker) | Supabase PostgreSQL 17 |
| Staging | Docker Compose | Local PostgreSQL 15 |
| Local Dev | `composer run dev` | Local PostgreSQL |

---

## 1. Local Development Setup

### Prerequisites

- PHP 8.3+
- Node.js 22+ (for Vite build)
- PostgreSQL (local or Supabase)
- Redis 7+ (local, Docker, or Memurai on Windows)
- Composer 2

### Quick Start

```bash
# Clone and install
git clone <repo-url>
cd one-window-bayanihan

# Full setup (install, env, keygen, migrate, npm install, build)
composer run setup
```

The `setup` script runs:
1. `composer install`
2. Copy `.env.example` → `.env`
3. `php artisan key:generate`
4. `php artisan migrate --force` (with the migration credential; never from the web process)
5. `npm install --ignore-scripts`
6. `npm run build`

### Development Server

```bash
composer run dev
```

Starts three concurrent processes:
- **Laravel server:** `php artisan serve` (port 8000)
- **Queue worker:** `php artisan queue:listen --tries=1 --timeout=0`
- **Vite dev server:** `npm run dev` (port 5173)

### Environment Variables (.env)

```env
# Application
APP_NAME="One Window Bayanihan"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (PostgreSQL required)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=one_window
DB_USERNAME=postgres
DB_PASSWORD=secret

# RLS roles (the web credential is not the database owner)
DB_RUNTIME_ROLE=bayanihan_runtime
DB_MAINTENANCE_ROLE=bayanihan_maintenance
CASE_DRAFT_MAINT_DB_URL=postgresql://bayanihan_maintenance:***@db.example/one_window?sslmode=require

# Storage (Supabase S3-compatible)
FILESYSTEM_DISK=supabase
SUPABASE_URL=your-supabase-url
SUPABASE_KEY=your-service-role-key
SUPABASE_S3_ACCESS_KEY=your-access-key
SUPABASE_S3_SECRET_KEY=your-secret-key
SUPABASE_S3_REGION=ap-southeast-1
SUPABASE_S3_BUCKET=your-bucket
SUPABASE_S3_ENDPOINT=https://your-project.supabase.co/storage/v1/s3

# Cache/Queue (Redis-backed for performance; sessions use database)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=log        # Use 'smtp' in production
MAIL_HOST=smtp.example.com
MAIL_PORT=587

# Security
TURNSTILE_SECRET_KEY=your-turnstile-secret
TURNSTILE_SITE_KEY=your-turnstile-site-key

# AI (OpenAI)
OPENAI_API_KEY=your-key
OPENAI_MODEL=gpt-4o-mini

# Cloudinary (avatars)
CLOUDINARY_URL=cloudinary://key:secret@cloud-name

# Sentry (error tracking)
SENTRY_LARAVEL_DSN=your-sentry-dsn

# Trusted Proxies
TRUSTED_PROXIES=10.0.0.0/8
```

---

## 2. Docker Deployment

### PostgreSQL roles and migration operations

Provision three separate credentials before enabling `FEATURE_CASE_DRAFTS`:

* `DB_URL`/`DB_USERNAME`: the non-owner `DB_RUNTIME_ROLE` used by web and queue processes.
* `CASE_DRAFT_MAINT_DB_URL`: the non-owner `DB_MAINTENANCE_ROLE` used only by
  `case-drafts:expire-stale` and `case-drafts:reencrypt`.
* A separately protected migration/owner credential used only by the release
  migration job (`php artisan migrate --force --no-interaction`).

The runtime and maintenance roles must be granted the policies and table
privileges by migration `2026_07_17_000005_add_staged_case_draft_rls_roles.php`.
Neither role may own the tables or use the owner/default connection as a
fallback. Verify `select current_user` for both URLs before rollout. Web and
worker containers do not auto-migrate; `RUN_MIGRATIONS` is intentionally ignored.
Keep `FEATURE_CASE_DRAFTS=false` until migrations, role grants, transaction
context verification, and the dedicated maintenance process are all live.

### Architecture

```
docker-compose.yml
  ├── nginx (1.27-alpine) ─── port 80 → reverse proxy to app:9000
  ├── app (PHP 8.4-fpm) ──── Laravel + queue worker + scheduler
  ├── db (postgres:15-alpine) ── local database
  └── redis (7-alpine) ──── cache, queue, OTP storage
```

### Build & Run

```bash
# Build and start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Seed database
docker compose exec app php artisan db:seed

# View logs
docker compose logs -f app
```

### Dockerfile (Multi-stage)

1. **Stage 1 (node-build):** Node.js 22 — runs `npm ci && npm run build` for Vite assets
2. **Stage 2 (app):** PHP 8.4-fpm + nginx + supervisord

Production container includes:
- PHP extensions: pdo_pgsql, bcmath, gd, intl, opcache, pcntl, exif, mbstring, zip
- Nginx reverse proxy (port 8080 internal)
- Supervisord managing nginx + php-fpm + queue worker
- Healthcheck: `curl http://127.0.0.1:8080/up`

### Container Security

- `security_opt: no-new-privileges`
- `cap_drop: ALL` + minimal `cap_add: FOWNER, SETGID, SETUID`
- `www-data` user for PHP-FPM
- Build-time `composer audit` for dependency vulnerability scanning
- Source files (JS, CSS, configs) removed from production image

### Docker Entrypoint

The `docker/php/docker-entrypoint.sh` handles:
1. Storage directory permissions
2. Config/route/view caching
3. Migration execution (if enabled)
4. Starting supervisord

---

## 3. Render Deployment (Production)

### Setup

1. **Web Service:** Docker deployment from GitHub repo
2. **Build Command:** Docker image build (Dockerfile handles everything)
3. **Start Command:** Handled by supervisord in container
4. **Port:** 8080 (exposed by container)
5. **Health Check:** `GET /up`

### Environment Variables (Render Dashboard)

Set all variables from `.env.example` plus:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.onrender.com`
- Database credentials pointing to Supabase

### Deployment Flow

```
GitHub push → Render detects → Docker build → Health check → Route traffic
```

### Scaling

- **Horizontal:** Render supports multiple instances (stateless app)
- **Database:** Supabase handles connection pooling
- **Queue:** Each container runs its own queue worker
- **Sessions:** Database-backed (shared across instances)

---

## 4. Supabase Configuration

### Database (PostgreSQL 17)

1. Create project in Supabase dashboard
2. Note connection string from Settings → Database
3. Enable required extensions: `pgcrypto`, `pg_trgm`
4. Run migrations: `php artisan migrate`

### Storage (S3-compatible)

1. Create bucket in Supabase Storage
2. Set bucket policies (authenticated access)
3. Get S3 credentials from Settings → Storage → S3
4. Configure in `.env`:
   ```
   FILESYSTEM_DISK=supabase
   SUPABASE_S3_ENDPOINT=https://project.supabase.co/storage/v1/s3
   SUPABASE_S3_ACCESS_KEY=...
   SUPABASE_S3_SECRET_KEY=...
   SUPABASE_S3_BUCKET=uploads
   SUPABASE_S3_REGION=ap-southeast-1
   ```

---

## 4.1 Case-category pivot migration

`2026_07_17_000001_create_case_category_pivot_table.php` is an undeployed migration. Deploy it in this order. This is a coordinated maintenance step, not a rolling migration:

1. Take and verify a PostgreSQL/Supabase backup or snapshot, and confirm the migration is pending (`php artisan migrate:status`).
2. Quiesce the application: stop/drain all old web instances and stop all queue workers (including schedulers or other processes that can write cases). Keep the old web/queue writers stopped until the migration, deployment, and initial reconciliation are complete; do not run this migration while an old writer can create or change category assignments.
3. Run the migration against the target database (`php artisan migrate --force`). It creates `case_category`, adds its foreign keys, indexes, and pair uniqueness constraint, then backfills one pivot row for each existing non-null `cases.category_id`.
4. Deploy the application release that reads and writes the pivot. Keep `cases.category_id` available as the compatibility mirror; do not treat it as the canonical assignment store.
5. Before restarting any writers, reconcile the pivot row count and sampled assignments against the non-null legacy mirror. Keep the old web/queue writers stopped while performing this initial reconciliation and assignment sampling.
6. Start the new web/queue processes, then verify category reads, writes, and filters before declaring the rollout complete.

The migration's rollback only drops `case_category`; it does not restore pivot assignments or provide a reverse backfill. Do not use `migrate:rollback` as an application rollback after data has been written to the pivot. If rollback is required, stop the incompatible release and restore the database snapshot (or otherwise recreate the pivot and assignments) before redeploying the previous application version.

---

## 5. Build Process

### Production Build

```bash
# Frontend (Vite)
npm ci --ignore-scripts
npm run build
# Outputs to public/build/

# Backend (Composer)
composer install --no-dev --optimize-autoloader

# Laravel optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Asset Fingerprinting

Vite automatically fingerprints all assets (CSS, JS) for cache busting. The `public/build/manifest.json` maps logical names to hashed filenames.

---

## 6. Operations

### Maintenance Mode

```bash
# Enable (via admin UI or CLI)
php artisan down --secret="bypass-token"

# Disable
php artisan up
```

Admin UI: `/admin/system/maintenance` (toggle button)

### Log Management

- **Storage:** `storage/logs/laravel.log`
- **Rotation:** Daily (default Laravel)
- **Admin UI:** `/admin/system/logs` (viewer + download)
- **Sentry:** Automatic exception reporting in production

### Queue Monitoring

```bash
# Check queue status
php artisan queue:monitor redis:default

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Database Backup

Scripts available in `scripts/`:
- `scripts/backup.sh` — pg_dump with timestamped filename
- `scripts/restore-test.sh` — Restore from backup file
- `scripts/load-test.sh` — Load testing utility

### Artisan Commands

| Command | Purpose |
|---------|---------|
| `php artisan migrate` | Run pending migrations |
| `php artisan db:seed` | Seed reference data |
| `php artisan config:cache` | Cache configuration |
| `php artisan route:cache` | Cache routes |
| `php artisan queue:listen` | Start queue worker |
| `php artisan chatbot:index` | Build chatbot search index |

---

## 7. Monitoring Checklist

- [ ] Sentry DSN configured and receiving errors
- [ ] Health check endpoint (`/up`) responding
- [ ] Redis connected (`redis-cli ping` → PONG)
- [ ] Queue worker running (check queue depth with `php artisan queue:monitor redis:default`)
- [ ] Email delivery working (check `email_logs` table)
- [ ] Storage accessible (test file upload)
- [ ] Database connections healthy
- [ ] SSL certificate valid
- [ ] Turnstile CAPTCHA functional on login

---

## 8. Rollback Procedure

1. Render: Deploy previous Docker image from deployment history
2. Database: If migration is destructive, restore from Supabase point-in-time recovery
3. Verify health check passes
4. Monitor error rates in Sentry
