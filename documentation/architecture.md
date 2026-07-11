# Architecture & System Design

## High-Level Topology

```
┌─────────────────────────────────────────────────────────────────────┐
│                         End Users (Browser)                          │
│  DMW Case Managers | Agency Focal Persons | Admins | OFWs           │
└──────────────────┬──────────────────────────────────┬────────────────┘
                   │ HTTPS / TLS 1.2+                 │ HTTPS / TLS 1.2+
                   ▼                                  ▼
┌──────────────────────────────────┐    ┌──────────────────────────────┐
│     Render (Laravel Hosting)      │    │  Public Routes (No Auth)     │
│  ┌────────────────────────────┐   │    │  • Welcome / Landing         │
│  │   Laravel Application      │   │    │  • OFW Tracking Portal       │
│  │   • Auth (OTP MFA)         │   │    │  • AI Chatbot                │
│  │   • Business Logic (Services│   │    │  • Helpdesk / Public Pages   │
│  │   • RBAC Enforcement        │   │    └──────────────────────────────┘
│  │   • Inertia.js SSR          │   │
│  │   • Queue (DB-driven)       │   │
│  │   • IP Whitelist Middleware │   │
│  └──────────┬─────────────────┘   │
└─────────────┼─────────────────────┘
              │ TLS PostgreSQL
              ▼
┌──────────────────────────────────┐
│  Supabase (Managed PostgreSQL)    │
│  • ACID-compliant transactions    │
│  • Row-Level Security (RLS)       │
│  • Encryption at rest (AES-256)   │
│  • Encrypted backups              │
└──────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────┐
│  Supabase Storage                  │
│  • Document/Media Storage         │
│  • Signed expiring URLs           │
│  • CDN delivery                   │
└──────────────────────────────────┘
```

## Request Flow

Every authenticated request follows this path:

```
Browser ─HTTPS─► Laravel Router ─► Middleware Stack
  ├─ StartSession
  ├─ Authenticate (checks auth guard)
  ├─ VerifyCsrfToken (state-changing)
  ├─ Role Middleware (role:CASE_MANAGER | AGENCY | ADMIN)
  └─ IP Whitelist (admin routes only)

  ─► Controller ─► Service ─► Model ─► PostgreSQL
  ─► Inertia::render('Page', $data) ─► React Component
```

### Middleware Order

1. **Session** — Database-backed sessions
2. **Authenticate** — Checks authentication guard
3. **VerifyCsrfToken** — CSRF protection on state-changing routes
4. **CheckRole** — RBAC enforcement (`CASE_MANAGER`, `AGENCY`, `ADMIN`)
5. **IpWhitelist** — IP restriction for admin routes only
6. **ContentSecurityPolicy** — CSP headers
7. **SecurityHeaders** — X-Frame-Options, HSTS, etc.
8. **LogContext** — Correlation ID for request tracing
9. **SetPostgresSession** — Sets PostgreSQL session context
10. **CheckUserActive** — Ensures user account is active
11. **CheckMfaEnrolled** — Ensures MFA is set up

## Security Architecture

### Defense in Depth

```
Layer 1: Network           HTTPS/TLS 1.2+, IP whitelist (admin)
Layer 2: Authentication    OTP MFA for all admin users
Layer 3: Authorization     Spatie RBAC + Lane isolation
Layer 4: Application       CSRF, XSS, SQL injection protections
Layer 5: Database          RLS, encryption at rest, TLS connections
Layer 6: Audit             Immutable action log, VIEW tracking
```

### Rate Limiting

| Endpoint | Limit | Middleware |
|---|---|---|
| Login | 6 attempts/minute | `throttle:login` |
| OTP Verification | 3 attempts/minute | `throttle:otp` |
| Tracking OTP | 5 attempts/minute | `throttle:tracking` |
| Chatbot | 30 requests/minute | `throttle:30,1` |
| Reports | 60 requests/minute | `throttle:60,1` |

### Authentication Flow

```
Login Request
  │
  ├─ Turnstile CAPTCHA verification
  ├─ Rate limit check (6/min)
  ├─ Email + Password validation
  │
  └─► OTP Email Sent (6-digit, 5-min TTL)
       │
       ├─ OTP Verification (3/min limit)
       │   └─► Session created, audit logged
       │
       ├─ TOTP Verification (if enrolled)
       │   └─► Session created
       │
       └─ Recovery Code Verification
           └─► Session created
```

## RBAC (Role-Based Access Control)

### Role Definitions

| Role | Scope | Key Permissions |
|---|---|---|
| `ADMIN` | System-wide | User/agency CRUD, system settings, audit logs, data export |
| `CASE_MANAGER` | Cases they own | Case CRUD, referral creation, case closure, reports |
| `AGENCY` | Their agency's referrals | View assigned referrals, accept/reject, add milestones |

### Lane Isolation

Each agency has a dedicated "lane" — they can only see referrals assigned to their agency. Enforcement at two layers:

1. **Application Layer:** Service methods filter by `agcy_id` based on authenticated user
2. **Database Layer:** PostgreSQL Row-Level Security (RLS) policies

### Route Access Matrix

| Route | Admin | Case Manager | Agency | Public |
|---|---|---|---|---|
| `/dashboard` | ✅ | ✅ | ✅ | ❌ |
| `/cases/*` | ✅ | ✅ | ⚠️ Show only | ❌ |
| `/referrals/*` | ✅ | ✅ | ✅ (lane) | ❌ |
| `/reports` | ✅ | ✅ | ✅ | ❌ |
| `/admin/*` | ✅ (+IP) | ❌ | ❌ | ❌ |
| `/track` | ❌ | ❌ | ❌ | ✅ (OTP) |
| `/help` | ❌ | ❌ | ❌ | ✅ |
| `/partners` | ❌ | ❌ | ❌ | ✅ |

## Deployment Topology

```
┌─────────────────────────────────────────────────────────────┐
│                        Internet                                │
├──────────────────────────┬──────────────────────────────────┤
│   Render Web Service      │   Render Cron Service             │
│   • Laravel App           │   • Scheduled tasks               │
│   • Vite Dev/Build        │   • Queue worker                  │
│   • Horizon metrics       │   • Backup coordination           │
├──────────────────────────┼──────────────────────────────────┤
│   Supabase                │   Supabase Storage                │
│   • PostgreSQL 17         │   • Document storage              │
│   • Auto-backups          │   • S3-compatible object storage  │
│   • TLS connections       │   • Signed URL delivery           │
└──────────────────────────┴──────────────────────────────────┘
```

### Environment Configuration

| Config | Value |
|---|---|
| `APP_ENV` | local / staging / production |
| `CACHE_STORE` | database |
| `QUEUE_CONNECTION` | database |
| `SESSION_DRIVER` | database |
| `MAIL_MAILER` | log (dev) / smtp (prod) |
| `FILESYSTEM_DISK` | supabase |

## External Integrations

| Integration | Purpose | Protocol |
|---|---|---|
| Supabase Storage | Document upload, versioning, CDN delivery | S3-compatible REST API + signed URLs |
| SMTP | OTP delivery, referral alerts, notifications | SMTP/TLS |
| OpenAI API (optional) | AI chatbot responses | REST HTTPS |
| Cloudinary | Avatar storage and transformation | REST API |

## Queue Architecture

- **Driver:** Database (`QUEUE_CONNECTION=database`)
- **Jobs Table:** `jobs` table in PostgreSQL
- **Processor:** `php artisan queue:listen` (required for async operations)
- **Use Cases:** Notification dispatch, PDF generation, email delivery, report exports
