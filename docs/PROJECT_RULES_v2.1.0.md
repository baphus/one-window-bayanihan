# Project Rules

> **Version:** 2.2.0 | **Updated:** 2026-11-01 | **Verified against:** current source, routes, and configuration (filename retained)
> **Supersedes:** `PROJECT_RULES.md` (v2.0.0). Architecture decisions fix *technologies*;
> hosting and managed-service providers are deployment choices, not architecture —
> see `DEPLOYMENT_GUIDE_v3.1.0.md` §1.

---

## 1. Project Identity

**Product:** Bayanihan One Window  
**Domain:** Inter-agency case coordination for distressed Overseas Filipino Workers (OFWs)  
**Authority:** Department of Migrant Workers (DMW) Region VII  
**Motto:** "One OFW, One Entry"  
**Legal Basis:** RA 11641 (DMW Act), RA 10173 (Data Privacy Act), DICT Cloud First Policy  

The system is a centralized inter-agency coordination platform — not a replacement for participating agencies' internal systems. DMW Region VII creates Unified Master Case Files shared with authorized partner agencies (OWWA, DOLE, TESDA, DSWD, DOH, Law Center Inc., and LGUs in Bohol, Cebu, Siquijor).

---

## 2. Immutable Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Backend Framework | Laravel 13, PHP >=8.4.1 <9.0 | Application control layer |
| Frontend Framework | React 18 | Dynamic UI rendering |
| Frontend Integration | Inertia.js v2 | SPA without API gateway |
| Styling | Tailwind CSS 3 | Utility-first, no custom CSS |
| Database | PostgreSQL 17 | ACID-compliant RDBMS; managed or self-hosted, provider-agnostic |
| Application Packaging | OCI container image (Docker) | One artefact runs on any compliant runtime |
| Media Storage | S3-compatible object storage | Secure object storage; any conforming implementation |
| Auth | Email/password + TOTP MFA | Email codes for registration/tracking/email changes; authenticator codes for login MFA |
| RBAC | Custom `CheckRole` middleware | `users.role` column, NOT Spatie |
| CAPTCHA | Bot-protection verify API | Login protection (`TURNSTILE_*` keys) |
| Queue | Redis 7 canonical driver | Configurable queue connections; not database-only |
| Cache | Redis 7 canonical store | Configurable stores; not database-only |
| Session | Database-driven | `sessions` table |
| Build Tool | Vite 8 | Fast HMR, ESM native |

---

## 3. Coding Conventions

### 3.1 PHP / Laravel

| Area | Convention |
|------|-----------|
| Primary Keys | Most domain models use UUIDs via `UsesUuid`; inspect model exceptions rather than assuming all models |
| Soft Deletes | Flag-based: `is_deleted` + `deleted_at` + `deleted_by` (via `SoftDeleteFlag` trait) |
| Controllers | Thin — delegate ALL business logic to `app/Services/*` |
| Validation | Form Request classes in `app/Http/Requests/` |
| Service Layer | `app/Services/` — one service per domain (CaseService, ReferralService, etc.) |
| Audit Logging | Service layer calls `AuditLog::log(...)` or via `AuditObserver` on model events |
| Route Binding | Route model binding with UUID strings — no explicit `where('id', ...)` |
| Middleware | Custom middleware in `app/Http/Middleware/`, registered in `bootstrap/app.php` |
| RBAC | `CheckRole` middleware: `role:CASE_MANAGER,ADMIN` checks `users.role` column |
| Date Functions | PostgreSQL functions: `to_char`, `EXTRACT`, `age` — NOT SQLite-compatible |
| Encryption | PII uses `EncryptedString` / `EncryptedDate` casts (Laravel encrypted at-rest) |
| Naming | PascalCase services, snake_case DB columns, camelCase PHP variables |

### 3.2 React / Frontend

| Area | Convention |
|------|-----------|
| Components | Default exports, PascalCase filenames, `.jsx` extension (unless existing `.tsx`) |
| Path Alias | `@/` → `resources/js/` (configured in vite.config.js, tsconfig.json, vitest.config.ts) |
| Forms | `useForm()` from `@inertiajs/react` for all mutations |
| Navigation | `Link` / `router` from Inertia; `route()` helper (Ziggy) for URL generation |
| Styling | Tailwind utility classes ONLY — no CSS modules, no styled-components |
| Icons | Material Symbols (primary) + lucide-react (supplementary) |
| State | Inertia props and local React state; no TanStack Query layer |
| Flash Messages | Backend sends `redirect()->with('flash', [...])` → auto-toasted by `FlashMessageWatcher` |
| Unsaved Changes | `useUnsavedChanges(dirty)` + `UnsavedChangesModal` on form pages |
| Charts | Chart.js + react-chartjs-2 |
| Validation | Zod schemas in `resources/js/Schemas/` for client-side validation |

### 3.3 File Organization

```
app/
├── Http/Controllers/       # Thin controllers (route → service → response)
├── Http/Middleware/         # Auth, RBAC, security, CSP
├── Http/Requests/           # Form validation classes
├── Services/               # Business logic (the "fat" layer)
├── Models/                 # Eloquent models (UUID PKs, flag soft-deletes)
├── Observers/              # AuditObserver for automatic audit logging
├── Events/ + Listeners/    # Async events (email, notifications)
├── DTOs/                   # Data transfer objects
├── Mail/                   # Mailable classes
├── Notifications/          # Database notification classes
└── Helpers/                # Utility functions

resources/js/
├── Pages/                  # Inertia page components (resolved from routes)
├── Components/             # Reusable React components
├── Hooks/                  # Custom React hooks
├── Layouts/                # AppLayout, HelpdeskLayout, GuestLayout
├── Schemas/                # Zod validation schemas
├── Onboarding/             # Getting-started checklist/profile prompts (guided tour retired)
├── lib/                    # Utility functions (dates, addresses, etc.)
└── data/                   # Static data (countries, addresses, helpdesk)
```

---

## 4. Business Rules

### 4.1 Case Lifecycle

1. **PENDING_REVIEW** → staff or signed-in citizen files a case; no draft editor/autosave lifecycle exists.
2. **OPEN** → Case Manager or Administrator approves the pending case; reviewer/time are recorded without replacing the original filer's `user_id`.
3. **REJECTED** → reviewer rejects with a reason. Current `rejectCase()` changes status and notifies the client but does not call soft-delete or set `deleted_at`.
4. **CLOSED** → staff close an open case only when all referrals are terminal; reopening returns it to open.
5. **ARCHIVED** → only closed cases can be archived; unarchiving returns them to closed. Archived cases may separately move to trash with a reason, be restored, or be purged after retention.

- Each case has unique generated `case_number` and random client-facing `tracker_number`.
- Cases belong to one client; a client can have multiple cases. Citizen intake has no active-case duplicate guard.
- Normal Case Manager access is office-wide; trash listing and restoration are owner-scoped.
- Case-detail updates accept category and issue/concern, not a general draft/profile save.
- Cases can have multiple referrals to different agencies.

### 4.2 Referral Lifecycle

```
PENDING → PROCESSING → FOR_COMPLIANCE → COMPLETED
                    → REJECTED (terminal)
```

- One referral = one case + one agency
- Agency can ACCEPT (→ PROCESSING) or REJECT (→ REJECTED)
- `FOR_COMPLIANCE` = agency requested additional documents
- `COMPLETED` triggers `ReferralCompleted` event → sends feedback invitation
- Milestones track progress within a referral
- Comments support threaded replies with visibility control
- Attachments support versioning (replaces_id chain)

### 4.3 Tracking Portal (Public)

- OFW enters `tracker_number` + email on `/track`
- System sends OTP to registered email
- After OTP verification, OFW sees case status, referral progress, milestones
- No authentication required (token-based session)

### 4.4 Feedback surveys

- Eligible referral completion triggers an emailed invitation when feedback is enabled, client email exists, and the agency has an active form.
- Agencies build their own survey forms; clients submit through valid private invitations.
- One invitation per case+agency+referral; one response per invitation.
- Legacy fixed SERVQUAL models/tables are retired, not a parallel live workflow.

### 4.5 Role Rules

| Rule | CASE_MANAGER | AGENCY | ADMIN |
|------|:---:|:---:|:---:|
| Create cases | ✅ | ❌ | ✅ |
| View all cases | ✅ | ❌ (only cases with own referral) | ✅ |
| Edit/update all cases | ✅ | ❌ | ✅ |
| Create referrals | ✅ | ❌ | ✅ |
| View all referrals | ✅ | ❌ (own agency only) | ✅ |
| Accept/reject referrals | ❌ | ✅ (own agency) | ❌ |
| Add milestones | ✅ (service/state rules apply) | ✅ (own referral) | ✅ (service/state rules apply) |
| View all clients | ✅ | ❌ (only clients with own referral) | ✅ |
| Manage agencies/users | ❌ | ❌ | ✅ |
| View full audit logs | ❌ | ❌ | ✅ |
| View operational activity | ✅ (scoped) | ✅ (scoped) | ✅ |
| Export data | ✅ | ✅ (own data) | ✅ |

---

## 5. Security Principles

| Principle | Implementation |
|-----------|----------------|
| Defense in depth | Global middleware → route middleware → controller auth → service checks |
| Least privilege | Role-based routes + lane isolation for agencies |
| PII encryption | Client data encrypted at-rest via Laravel encrypted casts |
| Immutable audit | Append-only audit_logs with PostgreSQL trigger blocking UPDATE/DELETE |
| Hash chain integrity | SHA-256 prev_hash links audit entries |
| Rate limiting | Per-endpoint limits on auth, OTP, API, reports |
| CSP | Dynamic Content-Security-Policy with nonces |
| MFA | Required for all roles, including OFW, in production; outside production enforcement is configurable and defaults to staff |
| Admin network policy | Former `IpWhitelist` middleware removed; do not promise application IP restrictions |
| Session security | Database sessions, PostgreSQL RLS context (context set per-request; enforced only on the 7 forced tables) |

---

## 6. Testing Requirements

| Area | Tool | Database |
|------|------|----------|
| PHP Feature Tests | PHPUnit 12 | PostgreSQL (`bayanihan_test`) |
| PHP Unit Tests | PHPUnit 12 | PostgreSQL (`bayanihan_test`) |
| Frontend Unit Tests | Vitest + Testing Library | JSDOM |
| E2E Tests | Playwright | Live server (port 8000) |

**Important:** Tests use PostgreSQL, NOT SQLite. The `phpunit.xml` explicitly sets `DB_CONNECTION=pgsql` and `DB_DATABASE=bayanihan_test`. PostgreSQL-specific functions (`to_char`, `EXTRACT`, `age`) are used throughout.

---

## 7. Naming Conventions

| Entity | Pattern | Example |
|--------|---------|---------|
| Controller | `PascalCaseController` | `CaseController`, `AdminUserController` |
| Service | `PascalCaseService` | `CaseService`, `ReportsService` |
| Model | `PascalCase` (singular) | `CaseFile`, `Referral`, `Agency` |
| Migration | `yyyy_mm_dd_HHMMSS_description` | `2026_06_01_000001_create_core_reference_tables` |
| Route Name | `dot.notation` | `cases.index`, `admin.users.store` |
| React Page | `PascalCase/PascalCase.jsx` | `Case/Index.jsx`, `Admin/User/Show.jsx` |
| React Component | `PascalCase.jsx` | `StatusBadge.jsx`, `UnifiedTable.jsx` |
| Custom Hook | `useCamelCase.js` | `useUnsavedChanges.jsx`, `useToast.jsx` |
| DB Table | `snake_case` (plural) | `cases`, `referral_attachments` |
| DB Column | `snake_case` | `created_at`, `agcy_id`, `is_deleted` |
| API Route | `kebab-case` | `/referrals/export-excel`, `/mark-all-read` |

---

## 8. Key Gotchas

1. **No Spatie package** — RBAC is a simple `CheckRole` middleware reading `users.role`, not the Spatie laravel-permission package
2. **No SQLite** — All tests require PostgreSQL; PostgreSQL-specific SQL is used everywhere
3. **No Kernel.php** — Middleware is configured in `bootstrap/app.php` (Laravel 13 style)
4. **Layered soft deletion** — `SoftDeleteFlag` uses Laravel `SoftDeletes` and adds `is_deleted`/`deleted_by`; use the project's trait where that contract applies
5. **UUID route binding** — Domain routes generally use UUID strings; verify model exceptions
6. **Redis queue/cache** — Canonical queue and cache use Redis; sessions remain database-backed. Drivers are configurable
7. **Inertia events** — `router.on('before')` receives `CustomEvent`; read `event.detail.visit`
8. **Flash deduplication** — Do NOT add seenRef/dedupe state; `FlashMessageWatcher` handles it
9. **Vite util stub** — `resources/js/vendor-stubs/util-stub.js` is required for Vite 8 builds
10. **Windows dev** — `composer run dev` omits `php artisan pail` because `pcntl` is unavailable

---

## 9. Platform neutrality rule

Hosting, managed-database, object-storage, cache, mail, and CDN **providers are
not architecture decisions** and must not be named in project documentation.
Fix the technology (PostgreSQL 17, S3-compatible storage, Redis 7, OCI
container, SMTP/HTTPS mail) and express everything provider-specific as
environment values or pipeline settings.

- Requirements for a deployment target: `DEPLOYMENT_GUIDE_v3.1.0.md` §1
- The only places a provider may appear: `DEPLOYMENT_GUIDE_v3.1.0.md` §12
- Application code must not import a provider SDK for any of the above; use
  standard protocol clients only.

---

## 10. Changelog

| Version | Date | Change |
|---|---|---|
| 2.2.0 | 2026-11-01 | Reconciled lifecycle, review, visibility, MFA, Redis, frontend state, surveys, retired screens, and operational distinctions against current source. Filename retained. |
| 2.1.0 | 2026-07-27 | Replaced vendor-named rows in §2 Immutable Architecture Decisions with technology-level decisions (PostgreSQL 17, OCI container image, S3-compatible object storage, RFC 6238 TOTP, bot-protection verify API) and dropped the hosting-provider row, which is a deployment choice rather than an architecture decision. Added §9 platform-neutrality rule. |
| 2.0.0 | 2026-07-11 | Previous revision (`PROJECT_RULES.md`): named hosting, managed-database, storage, and CAPTCHA vendors as immutable architecture. |

> **Current operational distinctions:** intake submission queues receipt/review email, not an in-app notice; approval records a timeline event without a separate notification dispatch. Overdue reminders are manually requested, not scheduled. Reports and referral lists are staff-gated; broad client picker endpoints are Case Manager/Administrator-only. Agency message service checks enforce participation even without dedicated route-role middleware. Agency attachment mutation routes accept `AGENCY`, but no agency upload/replace/remove UI is exposed. Application-log and resend screens and the full-workbook workflow are retired; administrator Monitoring pages cover system health, email logs, failed jobs, and the security log. The retained report job has no application dispatch caller. Chat uses a language-model agent with keyword-ranked helpdesk search/read tools and is disabled by default. Report PDF/Excel exports exist; report CSV does not. List case/client/referral exports log before generation without a separate outcome; report exports record attempt and outcome.
