# One Window Bayanihan

Laravel 13 + Inertia SPA case management system for DMW Region VII. PostgreSQL, React 18, Tailwind CSS 3, Vite 8.

## Commands

| Command | What it does |
|---------|-------------|
| `composer run dev` | Starts `php artisan serve` + `queue:listen` + `vite` via concurrently |
| `composer run setup` | Full project bootstrap: `composer install` → copy `.env` → `key:generate` → `migrate` → `npm install` → `npm run build` |
| `composer run test` | Runs `php artisan config:clear` then `php artisan test` (config clear is required before tests) |
| `php artisan test` | Runs PHPUnit test suite — uses PostgreSQL (`bayanihan_test` DB) |
| `php artisan migrate` | Run pending migrations (PostgreSQL) |
| `php artisan db:seed` | Seeds roles, agencies, services, demo data |
| `php artisan queue:listen` | Process database queue jobs (required for async) |
| `php artisan storage:link` | Required for local file upload testing |
| `npm run dev` | Vite dev server only |
| `npm run build` | Production Vite build |
| `npm test` | Vitest (watch mode) — JS unit tests in jsdom |
| `npm run test:run` | Vitest single run |
| `npm run test:e2e` | Playwright E2E tests (starts `php artisan serve` automatically) |

## Entrypoints & Layouts

- **App entry (Vite):** `resources/js/app.tsx`
- **Auth routes:** `routes/auth.php` — custom OTP 2FA login (`LoginOtpController`), not default Breeze
- **App routes:** `routes/web.php` — all authenticated pages. Includes inline `api/*` routes (session-authenticated, not Laravel API routes)
- **Main app layout:** `resources/js/Layouts/AppLayout.jsx` (sidebar). Also `AuthenticatedLayout.jsx` (top nav) and `GuestLayout.jsx` (auth pages)
- **Path alias:** `@/` → `resources/js/` (tsconfig.json + vite.config.js)

## Backend Architecture

- **Controllers** → **Services** (`app/Services/*`) → **Models** (UUID PKs, `UsesUuid` trait, `SoftDeleteFlag` flag-based soft deletes)
- Validation via Form Request classes (`app/Http/Requests/`)
- RBAC via `CheckRole` middleware — reads `users.role` column. Usage: `role:ADMIN` or `role:CASE_MANAGER,ADMIN` (comma-separated for multi-role)
- Roles: `CASE_MANAGER`, `AGENCY`, `ADMIN`
- Admin routes additionally gated by `ip.whitelist` middleware
- Model traits in `app/Models/Concerns/`: `UsesUuid`, `SoftDeleteFlag`, `HasAvatar`
- `route()` helper (Ziggy) available in JS — path alias `ziggy-js` in tsconfig.json
- App is wrapped in `<ErrorBoundary>` + `<QueryClientProvider>` (TanStack React Query) + `<OnboardingProvider>` (driver.js)

## Test Suite

| Layer | Tool | Config | Notes |
|-------|------|--------|-------|
| PHP (Feature/Unit) | PHPUnit 12 | `phpunit.xml` | PostgreSQL (`bayanihan_test`), cache=array, queue=sync, session=array |
| JS unit | Vitest 4 | `vitest.config.ts` | jsdom, setup: `resources/js/test-setup.ts` |
| E2E | Playwright | `playwright.config.ts` | `testDir: resources/js/test/e2e`, auto-starts serve on port 8000 |

**PHPUnit gotchas:** `phpunit.xml` overrides env for testing: `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`. Services using PostgreSQL functions (`to_char`, `EXTRACT`, `age` in `ReportsService`, `AnalyticsService`) require test data to exist in the test DB.

## Core Gotchas

| Gotcha | Detail |
|--------|--------|
| **Cache** | `CACHE_STORE=database` — OTPs and settings persist in the `cache` table, not Redis |
| **Queue** | `QUEUE_CONNECTION=database` — jobs table driven; run `queue:listen` for async work |
| **Sessions** | `SESSION_DRIVER=database` — sessions in `sessions` table |
| **Storage** | `FILESYSTEM_DISK=supabase` — files go to Supabase Storage (S3-compatible). Also Cloudinary for media (`CLOUDINARY_URL` in env) |
| **OTP** | 6-digit, 5-min TTL (`OtpService`). Debug mode in System Settings auto-fills OTP input |
| **Dev on Windows** | `php artisan pail` needs `pcntl` (Unix-only). Removed from `composer run dev`. Use `start-mailpit.ps1` for local SMTP |
| **DB** | PostgreSQL via Supabase pooler. `.env.example` shows Redis/Pusher/Supabase Storage defaults — actual `.env` uses database drivers |
| **Route bindings** | All PKs are UUIDs; implicit route model binding works with string IDs |
| **Blade** | Inertia SPA — root layout only (`resources/views/app.blade.php`). All rendering is JSX. Blade files under `resources/views/emails/`, `mail/`, `pdf/` for non-SPA output |
| **Vite `util` polyfill** | `resources/js/vendor-stubs/util-stub.js` stubs Node.js `util` module. Required because `object-inspect` (inertia dependency chain) accesses `require('util').inspect` which Vite externalizes |
| **Ziggy** | `route()` JS helper available. tsconfig.json maps `ziggy-js` → `./vendor/tightenco/ziggy` |
| **Toast/Flash** | Universal auto-toast via `HandleInertiaRequests.php` → `usePage().props.flash` → `FlashMessageWatcher`. Any `->with('success', '...')` works. DO NOT add `seenRef` — each navigation gives a new `props.flash` object, so `useEffect` fires once naturally. Files: `ToastProvider.jsx`, `useToast.jsx`, `HandleInertiaRequests.php` |
| **Unsaved Changes** | All form pages must use `useUnsavedChanges(dirty)` hook + `<UnsavedChangesModal>`. Hook in `resources/js/Hooks/useUnsavedChanges.jsx`, modal in `resources/js/Components/UnsavedChangesModal.jsx`. **CRITICAL: `router.on('before', handler)` receives a `CustomEvent` — access `event.detail.visit`, NOT the event directly. The callback must call `router.visit(visit.url, ...)` where `visit` comes from `event.detail.visit`.** |
| **Middleware stack** | Auth routes use `turnstile` (Cloudflare Turnstile) + named throttle: `login`, `otp`, `totp-challenge`, `recovery-code`, `api-global`, `tracking`. Chatbot uses `throttle:30,1` |

## Architecture Reference

Detailed docs in `ARCHITECTURE.md` (633 lines with Mermaid diagrams) and `docs/` directory (13 files covering data model, API contracts, testing strategy, deployment, security, UI patterns).

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **one-window-bayanihan**. Use GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/one-window-bayanihan/context` | Codebase overview, check index freshness |
| `gitnexus://repo/one-window-bayanihan/clusters` | All functional areas |
| `gitnexus://repo/one-window-bayanihan/processes` | All execution flows |
| `gitnexus://repo/one-window-bayanihan/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->

<!-- CODEGRAPH_START -->
## CodeGraph

In repositories indexed by CodeGraph (a `.codegraph/` directory exists at the repo root), reach for it BEFORE grep/find or reading files when you need to understand or locate code:

- **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the relevant symbols' verbatim source plus the call paths between them, including dynamic-dispatch hops grep can't follow. Name a file or symbol in the query to read its current line-numbered source. If it's listed but deferred, load it by name via tool search.
- **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same output.

If there is no `.codegraph/` directory, skip CodeGraph entirely — indexing is the user's decision.
<!-- CODEGRAPH_END -->
