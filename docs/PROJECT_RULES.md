# Project Rules

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Verified against:** actual source code

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
| Backend Framework | Laravel 13, PHP ^8.3 | Application control layer |
| Frontend Framework | React 18 | Dynamic UI rendering |
| Frontend Integration | Inertia.js v2 | SPA without API gateway |
| Styling | Tailwind CSS 3 | Utility-first, no custom CSS |
| Database | PostgreSQL 17 (Supabase) | Managed ACID-compliant RDBMS |
| Application Hosting | Render (Docker) | Managed cloud hosting |
| Media Storage | Supabase Storage (S3-compatible) | Secure object storage |
| Auth | Custom OTP + TOTP MFA | Email OTP + Google Authenticator |
| RBAC | Custom `CheckRole` middleware | `users.role` column, NOT Spatie |
| CAPTCHA | Cloudflare Turnstile | Login protection |
| Queue | Database-driven | No Redis dependency |
| Cache | Database-driven | No Redis dependency |
| Session | Database-driven | `sessions` table |
| Build Tool | Vite 8 | Fast HMR, ESM native |

---

## 3. Coding Conventions

### 3.1 PHP / Laravel

| Area | Convention |
|------|-----------|
| Primary Keys | UUID v4 via `UsesUuid` trait on all models |
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
| State | Inertia props (primary), React useState (UI), TanStack Query (async/polling) |
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
├── Onboarding/             # Tour system (OnboardingProvider, TourManager)
├── lib/                    # Utility functions (dates, addresses, etc.)
└── data/                   # Static data (countries, addresses, helpdesk)
```

---

## 4. Business Rules

### 4.1 Case Lifecycle

1. **Draft** → Case Manager creates case with client data (can auto-save)
2. **Published** → Case is active, referrals can be created
3. **Open** → Active case with at least one referral in progress
4. **Closed** → All referrals completed or manually closed
5. **Archived** → Soft-removed from active views

- Each case has a unique `case_number` (auto-generated) and `tracker_number` (public)
- Cases belong to one `client` (OFW profile)
- Cases have one `case_manager` (user_id)
- Cases can have multiple referrals to different agencies

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

### 4.4 Feedback (SERVQUAL)

- When referral → COMPLETED, invitation is auto-emailed to client
- Token-based form (no auth needed)
- SERVQUAL questionnaire: expectation vs. perception (1-7 scale) per dimension
- Each agency can configure their own questionnaire
- One feedback per case+agency+referral (unique constraint)

### 4.5 Role Rules

| Rule | CASE_MANAGER | AGENCY | ADMIN |
|------|:---:|:---:|:---:|
| Create cases | ✅ | ❌ | ✅ |
| View all cases | ✅ | ❌ (only cases with own referral) | ✅ |
| Edit/update all cases | ✅ | ❌ | ✅ |
| Create referrals | ✅ | ❌ | ✅ |
| View all referrals | ✅ | ❌ (own agency only) | ✅ |
| Accept/reject referrals | ❌ | ✅ (own agency) | ❌ |
| Add milestones | ❌ | ✅ (own referral) | ❌ |
| View all clients | ✅ | ❌ (only clients with own referral) | ✅ |
| Manage agencies/users | ❌ | ❌ | ✅ |
| View audit logs | ✅ | ❌ | ✅ |
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
| MFA | Optional TOTP (can be policy-enforced) |
| IP Whitelist | Admin routes restricted to configured IPs |
| Session security | Database sessions, PostgreSQL RLS context |

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
4. **No SoftDeletes trait** — Use `SoftDeleteFlag` trait instead
5. **UUID route binding** — All model route parameters are UUID strings
6. **Database for everything** — Queue, cache, and sessions all use the database driver
7. **Inertia events** — `router.on('before')` receives `CustomEvent`; read `event.detail.visit`
8. **Flash deduplication** — Do NOT add seenRef/dedupe state; `FlashMessageWatcher` handles it
9. **Vite util stub** — `resources/js/vendor-stubs/util-stub.js` is required for Vite 8 builds
10. **Windows dev** — `composer run dev` omits `php artisan pail` because `pcntl` is unavailable
