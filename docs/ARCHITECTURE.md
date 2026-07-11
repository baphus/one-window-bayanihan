# Architecture

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Status:** Verified against source code

## 1. System Overview

```
Browser (React 18 + Inertia.js)
    │
    ▼ HTTPS
Laravel 13 Application
    ├─ Global Middleware (SetPostgresSession → LogContext → SecurityHeaders)
    ├─ Web Middleware (CheckUserActive → CheckMfaEnrolled → CSP → HandleInertiaRequests → LinkHeaders)
    ├─ Route Middleware (role, ip.whitelist, turnstile, throttle)
    │
    ▼ Controller → Service → Model
PostgreSQL 17 (Supabase)
    ├─ Supabase Storage (S3-compatible file uploads)
    ├─ Cloudinary (avatar images)
    └─ Database Queue (jobs, failed_jobs tables)
```

## 2. Tech Stack

| Component | Technology | Version | Notes |
|-----------|-----------|---------|-------|
| Framework | Laravel | ^13.7 | PHP ^8.3, Docker uses 8.4-fpm |
| Frontend | React + Inertia.js | 18.2 + 2.0 | SPA without client-side router |
| CSS | Tailwind CSS | ^3.2 | @tailwindcss/forms plugin |
| Build | Vite | ^8.0 | laravel-vite-plugin ^3.1 |
| Database | PostgreSQL | 17 (Supabase) | UUID PKs, JSONB, pg_trgm |
| Queue | Database driver | — | jobs/failed_jobs tables |
| Cache | Database driver | — | cache table |
| Session | Database driver | — | sessions table |
| PDF | DomPDF | ^3.1 | barryvdh/laravel-dompdf |
| Excel | PhpSpreadsheet | 5.8.0 | phpoffice/phpspreadsheet |
| AI | OpenAI API | ^0.19.2 | openai-php/client |
| MFA | Google2FA | 3.0.1 | pragmarx/google2fa-laravel |
| CAPTCHA | Cloudflare Turnstile | — | Custom VerifyTurnstile middleware |
| Error Tracking | Sentry | ^4.8 | sentry/sentry-laravel |
| File Storage | AWS S3 (Supabase-compatible) | — | league/flysystem-aws-s3-v3 |
| Images | Cloudinary | 3.1.3 | Avatar upload/management |
| URL Generation | Ziggy | ^2.0 | Route names in JS |
| Charts | Chart.js + react-chartjs-2 | ^4.5.1 | Reports visualizations |
| Search | Fuse.js | ^7.4.2 | Client-side helpdesk search |
| Onboarding | driver.js | ^1.4.0 | Step-by-step guided tours |
| Markdown | @uiw/react-md-editor | ^4.1.0 | Article content rendering |
| Validation | Zod | ^4.4.3 | Client-side schema validation |
| Data Fetching | TanStack Query | ^5.101.0 | Notifications, lazy data |

## 3. Architecture Pattern

```
Controller (thin) → Service (business logic) → Model (data access) → PostgreSQL
```

### Layer Responsibilities

| Layer | Location | Responsibility |
|-------|----------|----------------|
| Controller | `app/Http/Controllers/` | Request routing, auth checks, call service, return Inertia response |
| Form Request | `app/Http/Requests/` | Input validation rules |
| Service | `app/Services/` | Business logic, orchestration, audit logging |
| Model | `app/Models/` | Eloquent ORM, relationships, scopes, casts |
| Observer | `app/Observers/` | Automatic audit logging on model events |
| Event/Listener | `app/Events/`, `app/Listeners/` | Async side-effects (emails, notifications) |
| DTO | `app/DTOs/` | Structured data transfer between layers |

## 4. Middleware Stack

### Global Middleware (all requests)

| Order | Middleware | Purpose |
|-------|-----------|---------|
| 1 | `SetPostgresSession` | Sets `app.current_user_id` session variable for RLS |
| 2 | `LogContext` | Adds correlation ID + user context to all log entries |
| 3 | `SecurityHeaders` | X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |

### Web Middleware (appended to web group)

| Order | Middleware | Purpose |
|-------|-----------|---------|
| 4 | `CheckUserActive` | Blocks deactivated users, forces logout |
| 5 | `CheckMfaEnrolled` | Redirects to MFA setup if policy requires enrollment |
| 6 | `ContentSecurityPolicy` | Dynamic CSP header with nonces for inline scripts |
| 7 | `HandleInertiaRequests` | Shares auth, flash, onboarding props to all pages |
| 8 | `AddLinkHeadersForPreloadedAssets` | HTTP/2 preload hints |

### Route Middleware Aliases

| Alias | Class | Usage |
|-------|-------|-------|
| `role` | `CheckRole` | `role:CASE_MANAGER,ADMIN` — checks `users.role` column |
| `ip.whitelist` | `IpWhitelist` | Admin routes — restricts by IP |
| `turnstile` | `VerifyTurnstile` | Login — validates Cloudflare Turnstile token |

### Rate Limiters

| Name | Limit | Scope |
|------|-------|-------|
| `login` | 5/min | Per email |
| `otp` | 3/min | Per session |
| `totp-challenge` | 5/min | Per session |
| `recovery-code` | 3/min | Per session |
| `tracking` | 5/min | Per IP |
| `api-global` | 60/min | Per user |
| Reports page | 60/min | Per route |
| AI insight | 10/min | Per route |
| Chatbot | 30/min | Per route |

## 5. Authentication Flow

```
1. POST /login (email + password + Turnstile token)
   → VerifyTurnstile middleware validates CAPTCHA
   → LoginOtpController::init() validates credentials
   → Generates 6-digit OTP, emails it
   → Returns step='otp' to frontend

2. POST /login/verify-otp (otp_code)
   → LoginOtpController::verifyOtp() validates OTP
   → If MFA enabled: returns step='totp'
   → If no MFA: authenticates, redirects to /dashboard

3. POST /login/verify-totp (totp_code) [if MFA enabled]
   → LoginOtpController::verifyTotp() validates TOTP
   → Authenticates, redirects to /dashboard

Alternative: POST /login/verify-recovery-code (recovery_code)
   → Consumes one recovery code, authenticates
```

### MFA (TOTP) Enrollment

- Users can enable TOTP via Profile → MFA Setup
- `POST /profile/mfa/generate` → returns QR code URI
- `POST /profile/mfa/verify` → validates first TOTP code, enables MFA
- 8 recovery codes generated on enrollment
- `POST /profile/mfa/recovery-codes/regenerate` → new set of codes

## 6. RBAC Model

Simple role-based access via `users.role` column + `CheckRole` middleware.

| Role | Access | Additional Restrictions |
|------|--------|------------------------|
| `CASE_MANAGER` | Cases, Clients, Referrals, Reports, Audit Logs, Stakeholders | — |
| `AGENCY` | Referrals (own agency only), Feedback, Services (own), Cases (with active referral) | Lane isolation |
| `ADMIN` | Everything + Admin panel | IP whitelist required for admin routes |

### Lane Isolation (Agency)

- Agencies see only referrals assigned to their agency (`agcy_id` filter)
- Can view case details only if they have an active referral on that case
- Cannot see other agencies' referrals or milestones on shared cases

## 7. Frontend Architecture

### App Bootstrap (`resources/js/app.tsx`)

```
ErrorBoundary
  └─ OnboardingProvider
       └─ QueryClientProvider (staleTime: 5min, gcTime: 30min, retry: 1)
            └─ ToastProvider
                 └─ Inertia Page Component
```

### State Management

| Type | Mechanism | Usage |
|------|-----------|-------|
| Server State | Inertia page props | Primary data source — passed from Laravel controllers |
| Form State | `useForm()` hook | All mutations (create/update/delete) |
| Async State | TanStack Query | Notifications polling, lazy-loaded sections |
| Local State | React `useState` | UI toggles, modals, filters |
| URL State | `router.get()` with `preserveState` | Report filters, table pagination |
| Persistent | `localStorage` | Draft case backup (useLocalStorageDraft) |

### Layouts

| Layout | Used By | Features |
|--------|---------|----------|
| `AppLayout` | All authenticated pages | Sidebar, ChatBot, Onboarding, Flash toasts |
| `HelpdeskLayout` | Help center pages | Public header, category nav, search |
| `GuestLayout` | Register, verify email | Centered card, minimal |

### Data Flow

```
Laravel Controller → Inertia::render('Page', $props)
    → React page receives props
    → useForm() for mutations → POST/PUT/PATCH
    → Backend returns redirect + flash message
    → FlashMessageWatcher auto-toasts
```

## 8. Key Services

| Service | Size | Responsibility |
|---------|------|----------------|
| `CaseService` | 46KB | Case CRUD, drafts, publishing, archiving, status management |
| `ReportsService` | 55KB | Analytics queries, KPI calculations, AI insights |
| `DashboardService` | 37KB | Role-specific dashboard data aggregation |
| `ReferralService` | 24KB | Referral lifecycle, milestones, comments, attachments |
| `TrackingService` | 26KB | Public case tracking, OTP verification |
| `ChatbotService` | — | AI chatbot orchestration |
| `AuditLogService` | — | Audit log queries, filtering |
| `FeedbackService` | — | SERVQUAL feedback, invitations, analytics |
| `ExportService` | — | PDF/Excel generation |

## 9. Deployment Topology

### Production (Render)

```
Render Web Service
  └─ Docker Container
       ├─ nginx (reverse proxy, static files)
       ├─ php-fpm 8.4 (Laravel application)
       └─ supervisord (manages nginx + php-fpm + queue worker)

Supabase (managed)
  ├─ PostgreSQL 17 (primary database)
  └─ Storage (S3-compatible file uploads)

Cloudinary (CDN)
  └─ Avatar images

Sentry (SaaS)
  └─ Error tracking & performance monitoring
```

### Local Development (Docker)

```
docker-compose.yml
  ├─ app (PHP 8.4-fpm + nginx + supervisor)
  ├─ postgres (PostgreSQL 15-alpine)
  ├─ redis (optional caching)
  └─ mailpit (email testing)
```

## 10. Module Map

### Backend Controllers (30+)

| Module | Controllers | Purpose |
|--------|------------|---------|
| Auth | `LoginOtpController`, `RegisteredUserController`, `PasswordResetLinkController`, `NewPasswordController`, `EmailChangeController`, `MfaController` | Authentication & MFA |
| Cases | `CaseController`, `CaseDocumentController`, `CaseIssueController` | Case lifecycle management |
| Referrals | `ReferralController` | Referral lifecycle, milestones, comments, attachments |
| Clients | `ClientController`, `ClientSelectController` | Client profiles & search |
| Feedback | `FeedbackController`, `PublicFeedbackController`, `AdminFeedbackController`, `AgencyServqualConfigController` | SERVQUAL feedback system |
| Reports | `ReportsController` | Analytics, exports, AI insights |
| Tracking | `TrackController` | Public OFW case tracking portal |
| Chatbot | `ChatbotController` | AI helpdesk chatbot |
| Admin | `AdminUserController`, `AdminAgencyController`, `AdminServiceController`, `AdminCaseCategoryController`, `AdminCaseStatusController`, `AdminCaseIssueController` | Admin CRUD |
| Admin System | `LogViewerController`, `MaintenanceController`, `SecuritySettingsController`, `ActiveSessionsController`, `EmailLogController`, `DataExportController`, `OverdueReferralController` | System administration |
| Other | `DashboardController`, `ProfileController`, `NotificationController`, `StakeholderController`, `OnboardingController`, `SystemSettingsController`, `AuditLogController`, `AgencyServiceController` | Supporting features |

## 11. Event System

| Event | Listener | Trigger |
|-------|----------|---------|
| `ReferralCompleted` | `SendFeedbackRequest` (queued) | Referral status → COMPLETED |
| Login success | `LogSuccessfulLogin` | Successful authentication |
| Various mail events | `EmailEventSubscriber` | Email sent/failed logging |

## 12. Queue & Background Jobs

- **Driver:** Database (`jobs` table)
- **Worker command:** `php artisan queue:listen --tries=1 --timeout=0`
- **Queued work:**
  - Feedback invitation emails (after referral completion)
  - Email notifications (case updates, referral assignments)
  - Email event logging

## 13. Caching Strategy

- **Driver:** Database (`cache` table)
- **Cached data:** Config, routes, views (production)
- **No application-level caching** of business data currently
- **TanStack Query** client-side: 5-minute stale time for notifications

## 14. Error Handling

- **Production:** Incident ID generated, logged, shown to user; reported to Sentry
- **Development:** Full stack trace via Whoops; Inertia requests force full reload (409) for debug page
- **API routes:** JSON error responses with appropriate status codes
- **Error pages:** Custom React components for 403, 404, 429, 500
