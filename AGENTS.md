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
| **Toast/Flash** | Universal auto-toast via `HandleInertiaRequests.php` → `usePage().props.flash` → `FlashMessageWatcher` (in all 3 layouts). Any `->with('success', '...')` works. DO NOT add `seenRef` — each navigation gives a new `props.flash` object, so `useEffect` fires exactly once naturally. Files: `ToastProvider.jsx`, `useToast.jsx`, `HandleInertiaRequests.php` |
| **Unsaved Changes** | All form pages must use `useUnsavedChanges(dirty)` hook + `<UnsavedChangesModal>`. Hook in `resources/js/Hooks/useUnsavedChanges.jsx`, modal in `resources/js/Components/UnsavedChangesModal.jsx`. Pass a `dirty` boolean. For `useForm` pages, compare `data` against a `useRef` snapshot of initial values. For `useState` forms, compare each field. For inline modal forms (admin CRUD), guard on `showForm`. For pages with multiple form partials, lift dirty state to the parent. See existing pages for patterns. |

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **one-window-bayanihan** (1995 symbols, 3327 relationships, 18 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST add `useUnsavedChanges(dirty)` + `<UnsavedChangesModal>` to every new page with form fields.** See the Unsaved Changes gotcha for patterns per form type.
- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
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
