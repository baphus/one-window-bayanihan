# Infrastructure

## Docker Setup

### Services

| Service | Image | Purpose |
|---|---|---|
| `app` | Custom PHP 8.3 | Laravel application |
| `nginx` | nginx:alpine | Reverse proxy |
| `postgres` | postgres:17 | Database |
| `redis` | redis:alpine | Cache (optional) |

### Key Files

- `Dockerfile` — PHP-FPM application container
- `docker-compose.yml` — Multi-service orchestration
- `docker/nginx/conf.d/default.conf` — Nginx configuration
- `docker/nginx/docker-entrypoint.sh` — Nginx startup script
- `docker/php/docker-entrypoint.sh` — PHP startup script
- `docker/php/php.ini` — PHP configuration
- `docker/supervisord.conf` — Process manager

### Running Docker

```bash
# Build and start all services
docker-compose up -d

# View logs
docker-compose logs -f app

# Run artisan commands
docker-compose exec app php artisan <command>

# Stop services
docker-compose down
```

## Deployment

### Render (Production)

**Web Service:**
- PHP 8.3 runtime
- Build command: `composer install --no-dev && npm ci && npm run build`
- Start command: `php artisan serve --host=0.0.0.0 --port=$PORT`
- Environment: Production

**Cron Service:**
- Same build as web service
- Start command: `php artisan schedule:work`
- Also runs queue worker: `php artisan queue:work --tries=1 --timeout=0`

### Environment Variables

All configuration via environment variables:

```env
# App
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=your-user
DB_PASSWORD=your-password

# Cache/Queue/Session
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# Storage
FILESYSTEM_DISK=supabase
SUPABASE_URL=https://xxxxx.supabase.co
SUPABASE_KEY=your-service-role-key

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your-user
MAIL_PASSWORD=your-pass
MAIL_ENCRYPTION=tls

# Security
ALLOWED_IPS=127.0.0.1,192.168.1.0/24
```

## Testing

### PHP Tests (PHPUnit)

```bash
# Run all tests
composer run test

# Run specific file
php artisan test tests/Feature/CaseControllerTest.php

# Run specific method
php artisan test --filter test_case_manager_can_create_case

# Run with coverage
php artisan test --coverage
```

### Test Configuration

From `phpunit.xml`:
```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
<server name="CACHE_STORE" value="array"/>
<server name="QUEUE_CONNECTION" value="sync"/>
```

**Important:** Tests run on SQLite in-memory, NOT PostgreSQL. PostgreSQL-specific functions (`to_char`, `EXTRACT`, `age`) will fail in tests.

### Frontend Tests

```bash
# Vitest (unit/integration)
npm run test:run

# Vitest watch mode
npm test

# Playwright E2E (auto-starts server on port 8000)
npm run test:e2e
```

### Test Categories

| Type | Framework | Location | Coverage |
|---|---|---|---|
| Unit | PHPUnit | `tests/Unit/` | Services, models, helpers |
| Feature | PHPUnit | `tests/Feature/` | Controllers, middleware |
| Component | Vitest | `resources/js/test/` | React components |
| E2E | Playwright | `resources/js/test/e2e/` | Critical user paths |

### Known Test Limitations

| Limitation | Workaround |
|---|---|
| PostgreSQL functions fail on SQLite | Test auth guard + route existence only |
| UserFactory missing `role` | Always pass `['role' => '...']` |
| PasswordReset tests return 404 | Known pre-existing issue |

### Critical E2E Paths

1. Welcome → Login → OTP → Dashboard
2. Dashboard → Create Case → Fill intake → Publish
3. Case Detail → Create Referral → Select Agency → Submit
4. Login as Agency → View Referrals → Accept → Add Milestone
5. Login as DMW → Case Detail → Verify all referrals terminal → Close case
6. Public → OFW Tracking → Enter Tracker # → Receive OTP → View progress

## Queue System

- **Driver:** Database
- **Worker:** `php artisan queue:work --tries=1 --timeout=0`
- **Concurrency:** Single worker (database driver)
- **Retry:** Failed jobs go to `failed_jobs` table

### Queue Jobs

| Job | Purpose |
|---|---|
| Email delivery | OTP, notifications, alerts |
| PDF generation | Report exports |
| Data export | Excel/CSV generation |
| Audit logging | Async audit writes |

### Monitoring

```bash
# Check queue size
php artisan queue:monitor

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## Caching

- **Driver:** Database
- **Cache table:** `cache` (Laravel default)
- **Usage:** Session storage, query caching, config caching

```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## Logging

- **Channel:** Stack (daily + stderr)
- **Log files:** `storage/logs/laravel.log`
- **Rotation:** Daily, max 7 days
- **Sentry:** Optional error tracking via `sentry/sentry-laravel`

### Log Context

Every request gets a correlation ID (`X-Request-ID` header or auto-generated UUID) for tracing across log entries.

## Scheduled Tasks

```php
// In routes/console.php
Schedule::command('cleanup:logs')->daily();
Schedule::command('cleanup:orphaned-files')->weekly();
Schedule::command('prune:audit-logs')->monthly();
Schedule::command('prune:email-logs')->monthly();
Schedule::command('sync:failed-emails')->hourly();
```

## Scripts

| Script | Purpose |
|---|---|
| `scripts/backup.sh` | Database backup |
| `scripts/restore-test.sh` | Test restore from backup |
| `scripts/drill.ps1` | Disaster recovery drill |
| `scripts/load-test.sh` | Load testing |
| `scripts/sync-philippine-addresses.cjs` | PSGC address sync |

## Code Quality

### PHP

```bash
# Check style
./vendor/bin/pint --test

# Fix style
./vendor/bin/pint
```

### JavaScript

```bash
# Build check
npm run build

# Type check (if configured)
npx tsc --noEmit
```

## Security

### IP Whitelist

Admin routes are restricted to whitelisted IPs:
- Configured in `config/app.php` → `allowed_ips`
- Enforced by `IpWhitelist` middleware
- Supports CIDR notation (e.g., `192.168.1.0/24`)

### Content Security Policy

Custom CSP headers configured in `config/csp.php`:
- Script sources restricted
- Style sources restricted
- Image sources restricted
- Connect sources for API calls

### CSRF Protection

All state-changing routes use CSRF tokens:
- Inertia handles CSRF automatically
- Meta tag `_csrf_token` in HTML head
- Header `X-XSRF-TOKEN` for AJAX

### Rate Limiting

| Endpoint | Limit | Window |
|---|---|---|
| Login | 6 | 1 minute |
| OTP | 3 | 1 minute |
| Tracking | 5 | 1 minute |
| Chatbot | 30 | 1 minute |
| Reports | 60 | 1 minute |
| API Global | Varies | 1 minute |

### File Upload Security

- MIME type validation (not just extension)
- File size limits
- Malware scanning (ClamAV optional, NullScanner for dev)
- Signed expiring URLs for access
- Storage on Supabase (not local filesystem in production)
