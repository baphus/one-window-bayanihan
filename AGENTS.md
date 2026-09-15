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
- AI chatbot uses in-memory weighted token match over the cached parsed helpdesk corpus (no vector DB, no SQLite FTS5 — retired); pre-warm via `php artisan chatbot:index`.

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

## Agent skills

### Issue tracker

GitHub Issues via `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context — one `CONTEXT.md` at repo root, ADRs in `docs/adr/`. See `docs/agents/domain.md`.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
