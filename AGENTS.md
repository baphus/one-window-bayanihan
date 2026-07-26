# One Window Bayanihan

Laravel 13 + Inertia/React 18 case-management system for DMW Region VII. PostgreSQL 17, Redis 7, S3-compatible object storage, Tailwind CSS 3, Vite 8, PHP 8.4 (Docker) / 8.3+ (local).

Documentation is platform-neutral: describe infrastructure by technology and capability, not by hosting or managed-service vendor. See `docs/DEPLOYMENT_GUIDE_v3.0.0.md` §1 (what a target must provide) and §12 (the only places a provider may be named).

## Commands

| Command | Use |
|---|---|
| `composer run setup` | Bootstrap: `composer install`, copy `.env`, keygen, migrate, `npm install --ignore-scripts`, build |
| `composer run dev` | Starts `php artisan serve`, `php artisan queue:listen --tries=1 --timeout=0`, and Vite via `concurrently` |
| `composer run test` | Clears Laravel config, then runs `php artisan test` |
| `php artisan test tests/Feature/NameTest.php` | Focused PHP test file |
| `php artisan test --filter test_name` | Focused PHP test method/name |
| `./vendor/bin/pint --test` | PHP style check; run `./vendor/bin/pint` to fix |
| `npm run dev` | Vite dev server only (`127.0.0.1:5173`) |
| `npm run build` | Production Vite build |
| `npm test` | Vitest watch mode |
| `npm run test:run` | Vitest one-shot |
| `npm run addresses:sync` | Sync Philippine PSGC address data |
| `docker compose up -d` | Full local stack (nginx + app + queue + scheduler + Postgres + Redis) |
| `docker compose --profile migrate run migrate` | Run migrations against local Docker DB |

## Entrypoints and routing

- SPA entry is `resources/js/app.tsx`; Inertia resolves pages from `resources/js/Pages/**/*.{jsx,tsx}`.
- Vite input is `resources/js/app.tsx`; alias `@/` points to `resources/js` in `vite.config.js`, `vitest.config.ts`, and `tsconfig.json`.
- Auth routes live in `routes/auth.php`; login is custom OTP/MFA via `LoginOtpController`, not default Breeze login flow.
- Authenticated app routes live in `routes/web.php`. Some session-authenticated `api/*` endpoints are defined there, so do not assume every API-looking route is in `routes/api.php`.
- Public API routes in `routes/api.php` are only PSGC address lookup and CSP report endpoints, throttled and unauthenticated.
- Middleware, aliases, routing, and exception rendering (custom 404/403/500 Inertia pages) are configured in `bootstrap/app.php`, not `app/Http/Kernel.php`.

## CI/CD pipelines (`.github/workflows/`)

| Workflow | Trigger | What it does |
|---|---|---|
| `ci.yml` | PR to `main` | Pint lint, Composer audit, NPM audit, build, backend tests (PG 17), frontend tests |
| `deploy-staging.yml` | Push to `main` + manual | Tests, `npx tsc --noEmit` (continue-on-error), staging rollout, health gate, chat notify |
| `deploy-production.yml` | Manual (must type "deploy-production") | Tests, production rollout, health gate, chat notify |
| `reset-staging-data.yml` | Daily 2AM UTC + manual | Trigger a staging rollout with cache clear to reseed the staging DB |

- CI uses PHP 8.4, Node 24, Postgres 17 service container.
- Staging deploy includes TypeScript check (`npx tsc --noEmit`) but failures don't block.
- Production requires explicit `workflow_dispatch` with confirmation string.
- The deploy step calls the hosting platform's deploy REST API with credentials from repository secrets, then health-gates `/up`. This API call is the **only** platform-specific step in the pipeline — the provider-named secrets in the workflow files are the last remaining vendor binding in the repo (`docs/CI_CD_GUIDE_v2.0.0.md` §4). Read the workflow file for the current endpoint; do not re-introduce provider names into docs.

## Backend conventions

- Keep controllers thin: Controller → Service (`app/Services/*`) → Model. Put validation in `app/Http/Requests/*`.
- Models use UUID primary keys via `App\Models\Concerns\UsesUuid`; route model binding expects string UUIDs.
- Soft deletion is flag-based (`SoftDeleteFlag`, `is_deleted`, `deleted_at`, `deleted_by`), not Laravel's `SoftDeletes` trait.
- Audit logging belongs in the service layer with `AuditLog::log(...)`; models may define `$auditExclude` and `getAuditModuleName()`.
- RBAC uses `users.role` through `role` middleware (`CASE_MANAGER`, `AGENCY`, `ADMIN`). Admin areas also use `ip.whitelist`.
- Global/web middleware includes PostgreSQL session context, log context, security headers, CSP, active-user/MFA checks, and Inertia shared props.
- AI chatbot uses SQLite FTS5 for retrieval (no vector DB); refresh index via `php artisan chatbot:index`.

## Frontend conventions

- Components are default exports in PascalCase `.jsx` files unless an existing `.tsx` file already owns the area.
- Use Inertia `useForm()` for mutations, `Link`/`router` for navigation, and Ziggy `route()` for URLs.
- Tailwind utilities only; design tokens are in `tailwind.config.js`. Material Symbols are the primary icon style.
- The app is wrapped in `ErrorBoundary`, TanStack `QueryClientProvider` (5 minute stale time, 30 min gcTime, 1 retry, no refetch on focus), `ToastProvider`, and `OnboardingProvider`.
- Flash messages from backend redirects auto-toast through shared `props.flash`; do not add `seenRef`/dedupe state that suppresses normal navigation flash.
- Form pages should use `useUnsavedChanges(dirty)` plus `UnsavedChangesModal`. Inertia `router.on('before')` receives a `CustomEvent`; read `event.detail.visit`.

## Tests and environment gotchas

- PHPUnit uses PostgreSQL database `bayanihan_test` from `phpunit.xml`; ensure it exists before PHP test runs. `DB_SSLMODE=disable` in test config.
- `phpunit.xml` overrides queue/cache/session/storage to sync/array/local, including fake S3 credentials (set through the legacy `SUPABASE_S3_*` alias keys that `config/filesystems.php` still reads as fallbacks for the canonical `STORAGE_*` vars). Storage fakes in tests use the `object-storage` disk.
- Reports/dashboard code uses PostgreSQL functions (`to_char`, `EXTRACT`, `age`); tests need PostgreSQL-compatible data, not SQLite assumptions.
- `.npmrc` sets `ignore-scripts=true`; use npm and `package-lock.json`, not alternate package managers.
- `composer run dev` intentionally omits `php artisan pail` because `pcntl` is unavailable on Windows.
- Vite has a custom pre-resolve `util`/`node:util` stub (`resources/js/vendor-stubs/util-stub.js`) for Rolldown/Vite 8 builds; do not remove it as "unused".
- `.env.example` is generic, but local deployments commonly use database-backed cache, queue, and sessions; verify the active `.env` before changing async/cache behavior.

## Docs worth checking

- `docs/PROJECT_RULES_v2.1.0.md` for domain/business constraints, role rules, and the platform-neutrality rule.
- `docs/ARCHITECTURE_v2.1.0.md` for system flow and deployment topology.
- `docs/TESTING_STRATEGY_v2.0.1.md` for focused test commands and coverage expectations.
- `docs/API_CONTRACTS.md` for all ~164 routes with middleware.
- `docs/DATA_MODEL.md` for complete database schema (31 tables).
- `docs/SECURITY_REQUIREMENTS_v2.1.0.md` for auth, RBAC, MFA, encryption details.
- `docs/DEPLOYMENT_GUIDE_v3.0.0.md` for the platform capability contract, env contract, scaling, and migration policy.
- `docs/CI_CD_GUIDE_v2.0.0.md` for CI stages and the deploy-trigger contract.
- Superseded unversioned copies of the docs above are kept as history; always read the highest version.
- `instructions.md` is stale Copilot-era guidance; prefer executable config and current `docs/` files.
