# Bayanihan One Window — Deployment Guide

> **Source:** SRS v1.2 (May 19, 2026) — §2.4 Operating Environment, §3.3 Cloud Hosting
> **Last Updated:** 2026-05-28

---

## 1. Infrastructure Overview

| Service | Provider | Purpose | Plan |
|---|---|---|---|
| Application Hosting | Render | Laravel + Vite runtime | Web Service (paid) |
| Database | Supabase | PostgreSQL 17 | Pro plan (paid) |
| Media Storage | Cloudinary | Document uploads + CDN | Free / Paid |
| Email/SMTP | SendGrid / SMTP | OTP + notifications | Free tier sufficient |
| AI Chatbot (optional) | OpenAI | Chatbot service | Pay-as-you-go |

---

## 2. Prerequisites

### 2.1 Local Development

- PHP 8.2+
- Composer 2.x
- Node.js 18+
- npm 9+
- PostgreSQL 15+ (local, or connect to Supabase remote)

### 2.2 Production

- Render account (with billing configured)
- Supabase account (with project created)
- Cloudinary account
- SMTP provider (SendGrid, Mailgun, or any SMTP)
- Domain name (optional, Render provides `*.onrender.com`)

---

## 3. Environment Configuration

### 3.1 `.env` Key Variables

```ini
APP_NAME="Bayanihan One Window"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.onrender.com

# Database (Supabase)
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=project.XXXXXXXXXX
DB_PASSWORD=your-supabase-password

# Cache / Queue / Session (all database-driven)
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# Cloudinary
CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_FROM_ADDRESS=noreply@bayanihan-onewindow.gov.ph
MAIL_FROM_NAME="Bayanihan One Window"

# OTP
OTP_EXPIRY_MINUTES=5
OTP_DEBUG_MODE=false

# IP Whitelist (comma-separated CIDR)
ALLOWED_IPS=203.0.113.0/24,198.51.100.0/24

# App Key (generate via: php artisan key:generate)
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxx
```

---

## 4. Build & Deploy

### 4.1 Local Build

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Create storage link (for local file serving)
php artisan storage:link

# Seed database
php artisan db:seed
```

### 4.2 Render Deployment

**Web Service Configuration:**

| Setting | Value |
|---|---|
| Build Command | `composer install --no-dev --optimize-autoloader && npm install && npm run build && php artisan key:generate` |
| Start Command | `php artisan serve --host=0.0.0.0 --port=$PORT` |
| Health Check Path | `/` |
| Environment Variables | All `.env` values set via Render Dashboard |

**Cron Job Service:**

| Setting | Value |
|---|---|
| Command | `php artisan schedule:run` |
| Schedule | Every 10 minutes (Render cron minimum) |

**Queue Worker (Background Worker):**

| Setting | Value |
|---|---|
| Command | `php artisan queue:listen --tries=3 --sleep=3` |
| Start Command | `php artisan queue:listen --tries=3 --sleep=3` |
| Replicas | 1 (adjust based on queue volume) |

### 4.3 Supabase Configuration

| Setting | Value |
|---|---|
| Region | ap-southeast-1 (Singapore) |
| SSL Mode | Required |
| Connection Pooling | Enabled (for Render connections) |
| Auto-backups | Enabled (daily, 7-day retention) |
| Point-in-time recovery | Enabled (pro plan) |

**Post-Deployment SQL:**

```sql
-- Enable Row-Level Security on key tables
ALTER TABLE cases ENABLE ROW LEVEL SECURITY;
ALTER TABLE referrals ENABLE ROW LEVEL SECURITY;
ALTER TABLE milestones ENABLE ROW LEVEL SECURITY;
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;

-- Create RLS policies (per agency isolation)
CREATE POLICY agency_lane_isolation ON referrals
    FOR ALL
    USING (agcy_id = current_setting('app.agency_id')::uuid);
```

### 4.4 Cloudinary Setup

| Setting | Value |
|---|---|
| Upload preset | Signed (authenticated) |
| Allowed file types | PDF, DOC, DOCX, JPG, PNG, JPEG |
| Max file size | 10 MB |
| Delivery type | Authenticated (signed URLs) |
| Transformation | None (raw document storage) |

---

## 5. Deployment Checklist

### Pre-Deployment

- [ ] All migrations run successfully against PostgreSQL
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] App key generated (`php artisan key:generate`)
- [ ] Rate limiting configured in `bootstrap/app.php`
- [ ] IP whitelist configured (admin routes)
- [ ] OTP debug mode disabled
- [ ] Storage linked (`php artisan storage:link`)
- [ ] Queue table migrated (`php artisan queue:table`)
- [ ] Cache table migrated (`php artisan cache:table`)
- [ ] Sessions table migrated (`php artisan session:table`)
- [ ] Tests pass (`php artisan test`)

### Post-Deployment

- [ ] `php artisan migrate` run on production DB
- [ ] `php artisan db:seed` run (roles, agencies, services, system settings)
- [ ] Queue worker running
- [ ] Cron worker running
- [ ] Health check endpoint responds 200
- [ ] Login flow works (email + OTP)
- [ ] Public pages accessible
- [ ] Admin routes accessible from whitelisted IP
- [ ] OTP emails delivered
- [ ] Document uploads working (Cloudinary)
- [ ] SSL certificate valid (Render auto-provisions)

---

## 6. Production Gotchas

| Gotcha | Detail |
|---|---|
| Cache/store | `CACHE_STORE=database` — no Redis dependency in v1.0 |
| Queue | `QUEUE_CONNECTION=database` — queue worker MUST be running for async notifications |
| OTP delivery | Requires working SMTP configuration — test with `MAIL_MAILER=log` first |
| Storage link | Required for local file serving — Cloudinary handles production storage |
| Windows dev | `php artisan pail` requires `pcntl` (Unix-only) — use `error_log` or file logging |
| Route caching | `php artisan route:cache` — only if no closure-based routes; current codebase has closures in web.php |
| Config caching | `php artisan config:cache` — run after every `.env` change |

---

## 7. Scaling Considerations

| Bottleneck | Solution |
|---|---|
| DB connections | Supabase connection pooling + Render connection limits |
| Queue throughput | Increase queue worker replicas |
| Media delivery | Cloudinary CDN handles scaling automatically |
| App concurrency | Render auto-scaling (enabled on paid plans) |
| Session storage | Database driver — add session table index if slow |

---

## 8. Monitoring & Logging

| Aspect | Tool | Notes |
|---|---|---|
| App logs | Render Dashboard | `stdout`/`stderr` capture |
| DB performance | Supabase Query Performance | Slow query identification |
| Error tracking | Laravel log (`storage/logs/`) | Configure daily rotation |
| Uptime | Render status page | `status.render.com` |
| Audit events | App AuditLog table | For security review |
| Queue health | `php artisan queue:monitor` | Alert on failed jobs |

---

## 9. Backup & Recovery

| Asset | Backup Method | Recovery |
|---|---|---|
| PostgreSQL | Supabase auto-backup (daily) | Point-in-time recovery |
| Application code | Git repository | `git clone && deploy` |
| Uploaded documents | Cloudinary auto-backup | Cloudinary media library |
| Environment config | Render environment variables | Export before changes |

### Recovery Procedure

1. **Application failure:** Restart Render web service
2. **Database corruption:** Restore from Supabase backup (PITR)
3. **Full disaster:** Deploy from Git, restore DB, restore Cloudinary assets
4. **Queue data:** `jobs` and `failed_jobs` tables backed up with DB
