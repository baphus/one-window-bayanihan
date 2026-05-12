# One Window Bayanihan

Laravel 13 + Inertia SPA case management system for DMW Region VII. PostgreSQL, React 18, Tailwind CSS 3, Vite 8.

## Commands

| Command | What it does |
|---------|-------------|
| `composer run dev` | Starts `php artisan serve` + `queue:listen` + `vite` via concurrently |
| `npm run build` | Production Vite build |
| `php artisan migrate` | Run pending migrations (PostgreSQL) |
| `php artisan test` | Runs tests via phpunit (SQLite in-memory, see gotchas) |
| `npm run dev` | Vite dev server only |
| `php artisan queue:listen` | Process database queue jobs |
| `php artisan db:seed` | Seeds roles, agencies, services, demo data |

## Entrypoints & Layouts

- **App entry (Vite):** `resources/js/app.tsx`
- **Auth routes:** `routes/auth.php` — custom OTP 2FA login (`LoginOtpController`), not default Breeze
- **App routes:** `routes/web.php` — all authenticated pages
- **Main app layout:** `resources/js/Layouts/AppLayout.jsx` (sidebar). Also `AuthenticatedLayout.jsx` (top nav) and `GuestLayout.jsx` (auth pages)
- **Path alias:** `@/` → `resources/js/` (tsconfig.json)

## Backend Architecture

- **Controllers** → **Services** (`app/Services/*`) → **Models** (UUID PKs, `UsesUuid` trait, `SoftDeleteFlag` flag-based soft deletes)
- Validation via Form Request classes (`app/Http/Requests/`)
- Admin routes gated by `role:ADMIN` middleware (spatie/laravel-permission)
- Roles: `CASE_MANAGER`, `AGENCY`, `ADMIN`
- All services: Analytics, Case, Dashboard, Otp, Referral, Reports, Tracking

## Core Gotchas

| Gotcha | Detail |
|--------|--------|
| **Cache** | `CACHE_STORE=database` — OTPs and settings live in the `cache` table, not Redis |
| **Queue** | `QUEUE_CONNECTION=database` — jobs table driven; run `queue:listen` for async |
| **OTP** | 6-digit, 5-min TTL (OtpService). Debug mode toggled in System Settings (auto-fills OTP input) |
| **Tests** | `phpunit.xml` overrides: SQLite in-memory, cache=array, queue=sync. Services using PostgreSQL functions (`to_char`, `EXTRACT`, `age` in `ReportsService`, `AnalyticsService`) **will fail** in tests |
| **User factory** | `database/factories/UserFactory.php` does not set `role` — auth tests fail with NOT NULL constraint |
| **Dev on Windows** | `php artisan pail` needs `pcntl` (Unix-only). Removed from `composer run dev` |
| **Storage** | `php artisan storage:link` required for referral file uploads |
| **DB** | PostgreSQL. `.env.example` shows Redis/Pusher/Cloudinary defaults — actual `.env` uses database drivers |
| **Route bindings** | All PKs are UUIDs; implicit route model binding works with string IDs |
| **Blade** | Inertia SPA — no Blade views except the root layout; all rendering is JSX |
