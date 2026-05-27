# Bayanihan One Window — System Architecture

> **Source:** SRS v1.2 (May 19, 2026) — §2 Overall Description, §3 External Interface Requirements
> **Last Updated:** 2026-05-28

---

## 1. Architecture Overview

Bayanihan One Window is a **modular, cloud-hosted web application** following a **mediated architecture** in which all protected data access passes through controlled server-side application logic. There is no direct client-to-database communication.

### High-Level Topology

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
│  Cloudinary                       │
│  • Document/Media Storage         │
│  • Signed expiring URLs           │
│  • CDN delivery                   │
└──────────────────────────────────┘
```

---

## 2. Technology Stack (SRS §3.3.1, §2.4)

### 2.1 Backend — Laravel (PHP)

| Aspect | Detail |
|---|---|
| Version | Laravel 11.x |
| Role | Authoritative application control layer |
| Responsibilities | Request validation, auth enforcement, business rule execution, case workflow management, audit logging coordination, secure database access mediation, document access governance |
| Service Layer | `app/Services/*` — all business logic extracted from controllers |
| Validation | Form Request classes (`app/Http/Requests/`) |
| Authentication | Custom OTP 2FA via `OtpService` (not Breeze/Jetstream) |

### 2.2 Frontend — React + Inertia + Tailwind (SRS §3.1)

| Aspect | Detail |
|---|---|
| Frontend Framework | React 18 |
| Integration | Inertia.js (no separate API gateway) |
| Styling | Tailwind CSS 3 |
| State | Inertia props + local React state (no Redux/Zustand) |
| Entry Point | `resources/js/app.tsx` |

### 2.3 Database — PostgreSQL (Supabase) (SRS §3.3.2)

| Aspect | Detail |
|---|---|
| Version | PostgreSQL 17 |
| Hosting | Supabase Managed |
| Key Features | ACID transactions, RLS lane isolation, JSONB for audit logs, encryption at rest (AES-256) |
| Constraints | Check constraints for enum fields, FK constraints for referential integrity |

### 2.4 Cloud Infrastructure (SRS §3.3.3)

| Service | Role | Provider |
|---|---|---|
| App Hosting | Laravel runtime | Render |
| Database | PostgreSQL hosting | Supabase |
| Media Storage | Document storage + CDN | Cloudinary |
| Email | OTP & notifications | SMTP (MAIL_MAILER=log for dev) |
| AI Chatbot | OFW inquiry assistance | OpenAI-compatible (if enabled) |

---

## 3. Module Architecture (SRS §4)

### 3.1 Module Map

```
┌─────────────────────────────────────────────────────────────────────┐
│                       BAYANIHAN ONE WINDOW                            │
├───────────────────┬───────────────────┬─────────────────────────────┤
│   Case Management  │   Referral Mgmt   │   Admin & Config            │
│   (SRS §4.3)       │   (SRS §4.5)      │   (SRS §4.2)                │
│                   │                   │                             │
│ • Intake/Creation  │ • Create/Route    │ • User Management           │
│ • Client Profile   │ • Accept/Reject   │ • Agency Registry           │
│ • Case Summary     │ • Status Track    │ • Service Categories        │
│ • Draft/Publish    │ • Milestones      │ • System Settings           │
│ • Archive          │ • Attachments     │ • Case Statuses             │
│                   │ • Comments         │ • Helpdesk CMS              │
├───────────────────┼───────────────────┼─────────────────────────────┤
│   Monitoring       │   Public Portal   │   Analytics                 │
│   (SRS §4.6)       │   (SRS §4.7)      │   (SRS §4.9)                │
│                   │                   │                             │
│ • Case Timeline    │ • OTP Tracking    │ • Dashboard KPIs            │
│ • Referral Status  │ • Milestone View  │ • Reports (PDF/CSV)         │
│ • Progress Flags   │ • Feedback        │ • Anonymized Analytics      │
│ • Closure Valid.   │   Submission      │ • Case Trends               │
├───────────────────┼───────────────────┼─────────────────────────────┤
│   Auth & Security  │   Audit           │   AI / Helpdesk             │
│   (SRS §4.1)       │   (SRS §4.10)     │   (SRS §4.8)                │
│                   │                   │                             │
│ • OTP MFA Login    │ • Immutable Log   │ • Chatbot (if enabled)      │
│ • IP Whitelist     │ • Action Audit    │ • Helpdesk Knowledge Base   │
│ • Role/Lane Access │ • VIEW Tracking   │ • Article Management        │
└───────────────────┴───────────────────┴─────────────────────────────┘
```

### 3.2 Request Flow (Typical Authenticated Page)

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

### 3.3 Authorization Flow

```
Request ─► Authenticate (session/OTP) ─► Role Check (Spatie)
  │
  ├── DMW CASE MANAGER  ─► Case CRUD, Referral Create, Case Close
  ├── AGENCY FOCAL       ─► Lane-restricted: their referrals only
  ├── ADMIN              ─► Users, Agencies, Settings, Audit
  └── OFW (Public)       ─► Tracker # + OTP → Read-only progress
```

---

## 4. Database Architecture (SRS §3.3.2, §6.2)

39 tables across the following domains:

| Domain | Core Tables | Purpose |
|---|---|---|
| **Case Management** | `cases`, `clients`, `client_addresses`, `client_employments`, `next_of_kin` | OFW case records |
| **Referral System** | `referrals`, `referral_attachments`, `referral_comments`, `milestones`, `referral_attachments` versioning | Inter-agency referrals |
| **Agency/Service** | `agencies`, `services`, `agency_service`, `service_requirements` | Partner network |
| **Auth/Users** | `users`, `permissions`, `roles`, `model_has_roles`, `model_has_permissions` | RBAC |
| **Audit** | `audit_logs` | Immutable action log |
| **System** | `system_settings`, `cache`, `jobs`, `notifications`, `sessions` | Operational |
| **Feedback** | `feedback`, `feedback_servqual_responses`, `servqual_configs` | SERVQUAL evaluation |
| **Helpdesk** | `helpdesk_articles`, `helpdesk_categories`, `helpdesk_tags`, `helpdesk_article_revisions`, `helpdesk_article_feedback` | Knowledge base |
| **Workflow** | `case_statuses`, `case_documents` | Case lifecycle |
| **Misc** | `agencies` (extended), `personal_access_tokens` | Supporting |

---

## 5. Key Design Decisions

### 5.1 Lane-Based Data Isolation (SRS BR-005)

Each partner agency has a dedicated "lane" — they can only see referrals assigned to their agency. Enforcement occurs at **two layers**:

1. **Application Layer:** Service methods filter by `agcy_id` based on authenticated user
2. **Database Layer:** PostgreSQL Row-Level Security (RLS) policies

### 5.2 Append-Only Audit (SRS §4.10, BR-007)

- Milestones: INSERT only, no UPDATE/DELETE in application code
- Audit Logs: INSERT only via model event observers
- Corrections: New entry referencing the previous event, never in-place edit

### 5.3 Parallel Referrals (SRS BR-003)

A single case may have multiple referral records, each to a different agency. Each referral has its own independent status lifecycle. Case-level status is computed from the aggregate state of all linked referrals.

### 5.4 Centralized Closure (SRS BR-008)

Only DMW CASE MANAGER can close a case. The system validates that **all** linked referrals are in terminal states (Completed, Rejected, or Unable to Proceed) before allowing closure.

### 5.5 Privacy-Safe Public Tracking (SRS BR-011)

The public OFW tracking portal:
- Requires Tracker Number + OTP verification
- Shows high-level case progress only
- Hides internal agency comments, case notes, and sensitive attachments

---

## 6. Security Architecture (SRS §5.3)

### 6.1 Defense in Depth Layers

```
Layer 1: Network           HTTPS/TLS 1.2+, IP whitelist (admin)
Layer 2: Authentication    OTP MFA for all admin users
Layer 3: Authorization     Spatie RBAC + Lane isolation
Layer 4: Application       CSRF, XSS, SQL injection protections
Layer 5: Database          RLS, encryption at rest, TLS connections
Layer 6: Audit             Immutable action log, VIEW tracking
```

### 6.2 Rate Limiting

| Endpoint | Limit | Middleware |
|---|---|---|
| Login | 6 attempts/minute | `throttle:login` |
| OTP Verification | 3 attempts/minute | `throttle:otp` |
| Tracking OTP | 5 attempts/minute | `throttle:tracking` |

---

## 7. External Integrations

| Integration | Purpose | Protocol |
|---|---|---|
| Cloudinary API | Document upload, versioning, CDN delivery | REST HTTPS + signed auth |
| SMTP | OTP delivery, referral alerts, notifications | SMTP/TLS |
| OpenAI API (optional) | AI chatbot responses | REST HTTPS |

---

## 8. Queue Architecture

- **Driver:** Database (`QUEUE_CONNECTION=database`)
- **Jobs Table:** `jobs` table in PostgreSQL
- **Processor:** `php artisan queue:listen` (required for async operations)
- **Use Cases:** Notification dispatch, PDF generation, email delivery

---

## 9. Deployment Topology (SRS §2.4)

```
┌─────────────────────────────────────────────────────────────┐
│                        Internet                                │
├──────────────────────────┬──────────────────────────────────┤
│   Render Web Service      │   Render Cron Service             │
│   • Laravel App           │   • Scheduled tasks               │
│   • Vite Dev/Build        │   • Queue worker                  │
│   • Horizon metrics       │   • Backup coordination           │
├──────────────────────────┼──────────────────────────────────┤
│   Supabase                │   Cloudinary                      │
│   • PostgreSQL 17         │   • Document storage              │
│   • Auto-backups          │   • Image optimization            │
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
| `FILESYSTEM_DISK` | cloudinary |

---

## 10. Scalability & Future Roadmap (SRS §6.3)

| Aspect | Current (v1.0) | Future |
|---|---|---|
| Agency Integration | Manual milestone entry | API-based direct integration |
| OFW Profiles | Case-centric | Persistent reusable OFW profiles |
| Notifications | Email only | SMS, push notifications |
| Replication | Single region | Multi-region DMW rollout |
| Real-Time | DB-driven sync | WebSocket/Pusher live updates |
