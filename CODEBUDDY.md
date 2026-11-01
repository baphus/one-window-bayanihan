# CODEBUDDY.md This file provides guidance to CodeBuddy when working with code in this repository.

## Documentation authority

Start with `docs/system-analysis-and-design/README.md` for the main narrative. Code, schema, routes, and executable configuration settle current implementation claims; engineering guides provide detail. Preserve dated historical records rather than treating them as current instructions.

## Project Overview

Laravel 13 + Inertia/React 18 case-management system for DMW Region VII — a centralized inter-agency case management platform for distressed OFWs. "One OFW, One Entry." Coordinates case management across multiple government agencies (OWWA, DOLE, TESDA, DSWD, DOH, Law Center, LGUs) with parallel referrals, milestone tracking, and immutable audit logging.

PostgreSQL 17 in CI (`ci.yml:105`), PostgreSQL 15 in local Docker (`docker-compose.yml:268`), Redis 7, S3-compatible object storage, Tailwind CSS 3, Vite 8, PHP 8.4 (`>=8.4.1 <9.0`).

Documentation is platform-neutral: describe infrastructure by technology and capability, not by hosting or managed-service vendor. See `docs/DEPLOYMENT_GUIDE_v3.1.0.md` §1 (what a target must provide) and §12 (the only places a provider may be named).

## Commands

| Command | Use |
|---|---|
| `composer run setup` | Bootstrap: `composer install`, copy `.env`, keygen, migrate, `npm install --ignore-scripts`, build |
| `composer run dev` | Starts `php artisan serve`, `php artisan queue:listen --queue=default,notifications --tries=1 --timeout=0`, and Vite via `concurrently`. Omits `php artisan pail` (pcntl unavailable on Windows) |
| `composer run test` | Clears Laravel config, then runs `php artisan test` |
| `php artisan test tests/Feature/NameTest.php` | Focused PHP test file |
| `php artisan test --filter test_name` | Focused PHP test method/name |
| `./vendor/bin/pint --test` | PHP style check; run `./vendor/bin/pint` (or `--format agent`) to fix |
| `npm run dev` | Vite dev server only (`127.0.0.1:5173`) |
| `npm run build` | Production Vite build |
| `npm run typecheck` | `tsc --noEmit` type check |
| `npm test` | Vitest watch mode |
| `npm run test:run` | Vitest one-shot |
| `npm run addresses:sync` | Sync Philippine PSGC address data |
| `docker compose up -d` | Full local stack (nginx + app + queue + scheduler + Postgres + Redis) |
| `docker compose --profile migrate run migrate` | Run migrations against local Docker DB |
| `php artisan chatbot:index` | Pre-warm chatbot helpdesk corpus cache |

## Architecture

### Request lifecycle and routing

Browser → HTTPS → Laravel middleware stack → Controller → Service → Model → PostgreSQL. Inertia.js bridges server and client: controllers call `Inertia::render()` returning JSON page props, React renders SPA pages.

**Middleware pipeline** (`bootstrap/app.php`, not `app/Http/Kernel.php`):
- Global: `LogContext` (correlation ID), `SecurityHeaders`, `preventRequestForgery(originOnly: true)`, `trustProxies`
- Web group prepended: `ContentSecurityPolicy`
- Web group appended: `CheckUserActive`, `EnsureMfaSession`, `CheckMfaEnrolled`, `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`, `SetPostgresSession`
- Aliases: `role` → `CheckRole`, `turnstile` → `VerifyTurnstile`, `mfa.pending` → `EnsureMfaChallenge`, `pending.registration` → `EnsurePendingRegistration`
- Custom exception rendering: Inertia error pages for 404/403/429/405/503; 500 with Sentry incident ID; JSON 422 for API-path `ValidationException`; Sentry integration via `Integration::handles()`

**Route files:**
- `routes/web.php`: public pages, authenticated app routes, role-gated groups, session-authenticated `api/*` endpoints (NOT all API routes are in `routes/api.php`), chatbot, file serving
- `routes/auth.php`: login (email+password), TOTP MFA challenge, OFW self-registration (Turnstile + OTP), invite registration, password reset, email-change (OTP), throttled guest routes
- `routes/api.php`: unauthenticated, throttled — `/api/readyz` (deep readiness probe), `/api/csp/report`, `/api/webhooks/resend` (Svix-signed), email availability check
- `routes/console.php`: scheduler heartbeat (every minute, feeds readiness probe), 8 scheduled commands with `withoutOverlapping()` and Sentry failure reporting

**RBAC model**: `users.role` is a plain `string(50)` column (not an enum). The `role` middleware alias (`app/Http/Middleware/CheckRole.php`) does `in_array($request->user()->role, $roles)`. Four roles: `CASE_MANAGER`, `AGENCY`, `ADMIN`, `OFW`. `app/Enums/UserRole.php` provides `values()`, `staffValues()` (all non-OFW), `isStaff()`, `label()`.

**Auth and MFA flow**: Login is email+password via `AuthenticatedSessionController` → TOTP MFA via `MfaChallengeController`. Email OTP supports citizen registration review, email changes, and tracking/document-request access — not staff login. In production (`APP_ENV=production`), MFA is enforced for ALL roles, including OFW, regardless of config flags; MFA cannot be disabled in production (returns 403).

### Backend layering and patterns

**Thin controllers** (`app/Http/Controllers/`, 60 files) delegate to **services** (`app/Services/`, 84 files). Validation lives in `app/Http/Requests/` (34 Form Request classes).

**Key service areas:**
- Core: `CaseService` (63 KB), `ReferralService` (53 KB), `DashboardService`, `ReportsService`, `TrackingService`, `UserService`
- Chatbot: `Services/Chatbot/` — language-model agent with helpdesk search/read tools; retrieval uses weighted token matching over the cached corpus (no vector DB or SQLite FTS5). See `docs/CHATBOT_PIPELINE_v1.0.0.md`.
- Reports/Export: `Services/Reports/` (PDF via `barryvdh/laravel-dompdf`, Excel via `phpoffice/phpspreadsheet`, charts via `chart.js`)
- Malware scanning: `Services/Malware/` — `ClamAvScanner` or `NullScanner` (fail-open with logged warning), bound via `MalwareScannerInterface` in `AppServiceProvider`
- Referral: `Services/Referral/` — `ReferralStatusMachine`, `ReferralNotifier`, `ReferralStatsCache`

**Model traits** (`app/Models/Concerns/`):
- `UsesUuid` — UUID v4 PKs on business models (`AgencyThreadRead`, `Notification`, `SystemSetting` do not use it). Sets `incrementing=false`, `keyType=string`.
- `SoftDeleteFlag` — wraps Laravel `SoftDeletes`, adds `is_deleted` boolean + `deleted_by` FK via single-write `runSoftDelete()` override, clears flags on restore.
- `CascadeSoftDeletes` — cascades soft-delete/restore to child relationships via `$cascadeSoftDeletes` property. Uses a 2-second tolerance window to distinguish cascade-deleted children from independently-deleted ones; handles force-delete cascades.
- `HasAvatar` — signed temporary URL generation for avatars/logos via `StorageService`.

**Audit logging**: Service-layer `AuditLog::log(...)` calls produce immutable append-only entries with hash-chain integrity. Models declare `$auditExclude` and `getAuditModuleName()`. The `AuditObserver` is auto-registered from `config/audit.php`'s `observed_models` list (28 models). `AuditModelCoverageTest` asserts every listed model emits audit rows. Sensitive-value redaction via keys + patterns in `config/audit.php`. Audit lifecycle: monthly archive → prune (refuses rows not covered by a finalized bundle) → weekly chain verification.

**Cache invalidation**: `CacheInvalidationObserver` watches 10 models and invalidates cached reference data. `HandleInertiaRequests` provides cache invalidation helpers.

**Rate limiting**: `AppServiceProvider` defines 28+ named rate limiters. Each guest-facing limiter is named (not inline) to avoid shared throttle budgets. Email-accepting routes use dual-keyed limiting (email|IP).

**Sentry integration**: Both frontend (`@sentry/react`, `resources/js/sentry.ts`) and backend (`sentry/sentry-laravel`, `config/sentry.php`). `SentryBeforeSend::scrub` callable (non-closure, because closures break `config:cache`) scrubs sensitive data. `send_default_pii=false`.

### Frontend architecture

**Entry**: `resources/js/app.tsx` — wraps app in `ErrorBoundary` → `OnboardingProvider` → `ToastProvider` → Inertia App. Initializes Sentry. Resolves pages via `import.meta.glob('./Pages/**/*.{jsx,tsx}')`.

**Layouts** (4): `AppLayout` (authenticated), `GuestLayout` (unauthenticated), `HelpdeskLayout` (knowledge base), `OfwLayout` (OFW portal).

**Pages** (151 files in `resources/js/Pages/`): organized by domain — `Admin/`, `Agency/`, `Auth/`, `Case/`, `Client/`, `Referral/`, `Dashboard/`, `Reports/`, `OFW/`, `Helpdesk/`, `Survey/`, `Tracking/`, `Errors/`, `Legal/`, `Profile/`, `Notifications/`, `SystemSettings/`, `Contact/`, `Stakeholder/`.

**Components** (123 files): reusable UI primitives at root level, domain-specific in `Admin/`, `Dashboard/`, `Helpdesk/`, `landing/`, `Reports/`, `ui/`.

**Hooks** (13 files): `useUnsavedChanges`, `useToast`, `useLocalStorage`, `useLazyProp`, `useReportFilters`, etc.

**Zod schemas** (4 files): `adminSchemas.js`, `authSchemas.js`, `profileSchemas.js`, `referralSchema.js` — client-side validation.

**Conventions:**
- Default exports in PascalCase `.jsx` files unless an existing `.tsx` file owns the area.
- Use Inertia `useForm()` for mutations, `Link`/`router` for navigation, Ziggy `route()` for URLs.
- Tailwind utilities only; design tokens in `tailwind.config.js`. Material Symbols + lucide-react for icons.
- Flash messages from backend redirects auto-toast through shared `props.flash`; do not add `seenRef`/dedupe state.
- Form pages use `useUnsavedChanges(dirty)` + `UnsavedChangesModal`. Inertia `router.on('before')` receives a `CustomEvent`; read `event.detail.visit`.
- Path alias `@/` → `resources/js/` in `vite.config.js`, `vitest.config.ts`, `tsconfig.json`.

**Inertia shared props** (from `HandleInertiaRequests`): `request_id`, `auth.user`, `roles` (all UserRole values), `notifications.unread_count` (cached 60s), `intake_queue_count` (cached 120s), `onboarding`, `profile_incomplete`, `flash` (success/error/warning/info/status/mfa_recovery_codes_remaining), `chatbot` (config + suggestions), `turnstile` (config), `addresses.served_regions`, `passwordRules`. Lazy-evaluated via closures.

**Vite gotcha**: Custom pre-resolve `util`/`node:util` stub (`resources/js/vendor-stubs/util-stub.js`) for Rolldown/Vite 8 builds; do not remove it as "unused". A `dev-tunnel-headers` plugin sets `Access-Control-Allow-Private-Network`.

### Readiness vs health check

- `/up` (route at `bootstrap/app.php:41`): shallow health check, no database touch — used by deploy pipeline health-gating.
- `/api/readyz` (`routes/api.php:13`): deep readiness probe — checks database connectivity, scheduler heartbeat freshness, queue backlog, failed job count. Requires a monitoring token. Deliberately distinct to prevent restart loops during transient DB issues.

### Configuration

Custom config files (21 total in `config/`):
- `ai-chatbot.php` — provider (default: gemini), model, temperature, max_tokens, assistant name ("Bayani")
- `audit.php` — retention_days (365), archive_disk, chain_verified_from, export limits, 28 observed models, redaction policy
- `mfa.php` — pending_ttl (300s), max_attempts (5), window (1), replay_ttl (120s), production enforcement
- `csp.php` — CSP report URI (`/api/csp/report`)
- `turnstile.php` — Cloudflare Turnstile CAPTCHA config
- `file-uploads.php` — max_size (20480 KB = 20 MB), default mimes
- `monitoring.php` — readiness_token, thresholds (queue_backlog=100, failed_jobs=25, scheduler_stale_seconds=300)
- `reports.php` — export_row_cap (6000), pdf_preflight_max_rows (30000), chart_max_categories (15), suppression_threshold (5)
- `addresses.php` — served region scope (Region VII + NIR only)
- `sentry.php` — DSN, release, traces_sample_rate (0.2), `SentryBeforeSend::scrub` callable, `send_default_pii=false`

## Tests and environment gotchas

- PHPUnit uses PostgreSQL database `bayanihan_test` from `phpunit.xml`; ensure it exists before PHP test runs. `DB_SSLMODE=disable` in test config.
- `phpunit.xml` overrides queue/cache/session/storage to sync/array/local, including fake S3 credentials (set through legacy `SUPABASE_S3_*` alias keys that `config/filesystems.php` reads as fallbacks for canonical `STORAGE_*` vars). Storage fakes use the `object-storage` disk.
- Reports/dashboard code uses PostgreSQL functions (`to_char`, `EXTRACT`, `age`); tests need PostgreSQL-compatible data, not SQLite assumptions.
- `.npmrc` sets `ignore-scripts=true`; use npm and `package-lock.json`, not alternate package managers.
- Vitest tests live alongside components in `__tests__/` directories. Test setup in `resources/js/test-setup.ts`.
- Factory structure: 12 model factories. Seeders include `StagingSeeder` (58 KB deterministic demo dataset) and `TestingSeeder` (45 KB). `ProductionSeeder` is separate from `DatabaseSeeder`.

## CI/CD pipelines (`.github/workflows/`)

| Workflow | Trigger | What it does |
|---|---|---|
| `ci.yml` | PR to `main`, and push to `main` | `lint-and-audit`, `backend-tests`, `frontend-tests` — only workflow configuring runtime: PHP 8.4 (`ci.yml:38,124`), Node 24 (`ci.yml:45,130,228`), Postgres 17 service container (backend-tests only, `ci.yml:103-116`). Push-to-main runs because direct push/merge can land untested combinations |
| `build-image.yml` | Manual (`workflow_dispatch`) | Builds deployable OCI image, pushes to registry tagged with commit SHA. Manual to avoid per-push storage charges |
| `deploy.yml` | `workflow_call` (reusable) | Deploys built image, runs migrations INSIDE container (`RUN_MIGRATIONS=true`), health-gates `/up` before reporting success. Contains the only platform-specific step (AWS CLI) |
| `deploy-production.yml` | Manual — must type `PRODUCTION` | Gated caller: guard job compares input against `"PRODUCTION"`. No staging caller exists; `deploy.yml` has one caller always passing `environment: production` |

Deploy step uses OIDC-federated cloud credentials (`aws-actions/configure-aws-credentials@v5`), not repository secrets. The provider-named deploy command in the workflow file is the last remaining vendor binding — read the workflow file for the current command; do not re-introduce provider names into docs.

## Docs worth checking

- `docs/PROJECT_RULES_v2.1.0.md` — domain/business constraints, role rules, platform-neutrality rule.
- `docs/ARCHITECTURE_v2.2.0.md` — system flow and deployment topology.
- `docs/TESTING_STRATEGY_v2.1.0.md` — focused test commands and coverage expectations.
- `docs/API_CONTRACTS.md` — 234 application routes (238 with vendor) with middleware.
- `docs/DATA_MODEL.md` — complete schema (41 domain tables + 9 framework + `migrations` = 51 total).
- `docs/SECURITY_REQUIREMENTS_v2.2.0.md` — auth, RBAC, MFA, encryption.
- `docs/DEPLOYMENT_GUIDE_v3.1.0.md` — platform capability contract, env contract, scaling, migration policy.
- `docs/CI_CD_GUIDE_v2.1.0.md` — CI stages and deploy-trigger contract.
- `docs/README.md` (v2.4.0) — comprehensive index of all 99 docs with status (CURRENT/SUPERSEDED/HISTORICAL-ARCHIVE).
- Superseded unversioned copies kept as history; always read the highest version.

## Known stale file

`.cursorrules` is stale: references PHP 8.3 (should be 8.4), says `CACHE_STORE=database` (project uses Redis 7), says "Do NOT modify Laravel Breeze auth scaffolding" (project uses custom auth, not Breeze). Prefer this file and `AGENTS.md` over `.cursorrules`.

## Agent skills

- **Issue tracker**: GitHub Issues via `gh` CLI. See `docs/agents/issue-tracker.md`.
- **Triage labels**: Five-role vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.
- **Domain docs**: `CONTEXT.md` and `docs/adr/` do not currently exist. Derive domain vocabulary from `docs/PROJECT_RULES_v2.1.0.md` and `docs/README.md` until they do.
