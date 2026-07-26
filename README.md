# Bayanihan One Window

A centralized inter-agency case management system for distressed Overseas Filipino Workers (OFWs) in Region VII, built by the Department of Migrant Workers (DMW).

**"One OFW, One Entry"**

## What It Does

Bayanihan One Window coordinates case management across multiple government agencies (OWWA, DOLE, TESDA, DSWD, DOH, Law Center, and LGUs). DMW Case Managers create unified case files, then refer them to partner agencies — each agency works in their own lane while the system tracks progress, milestones, and closure.

### Key Features

- **Case Management** — Intake, drafting, publishing, and lifecycle tracking
- **Referral System** — Parallel referrals to multiple agencies with independent status tracking
- **Public OFW Tracking** — OTP-verified portal for case progress visibility
- **AI Chatbot** — Interactive helpdesk for OFW inquiries
- **Reporting & Analytics** — Dashboards, PDF/CSV exports, AI-powered insights
- **Helpdesk Knowledge Base** — Categorized articles with search and feedback
- **SERVQUAL Feedback** — Agency service quality measurement
- **Audit Trail** — Immutable append-only audit log with hash chain integrity

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | React 18, Inertia.js v2, Tailwind CSS 3 |
| Database | PostgreSQL 17 |
| File Storage | S3-compatible object storage |
| Build Tool | Vite 8 |
| Auth | Custom OTP + TOTP MFA |
| RBAC | Custom CheckRole middleware (`users.role` column) |
| Cache / Queue | Redis 7 |
| Packaging | OCI container image (Docker) — runs on any compliant runtime |

## Quick Start

### Prerequisites

- PHP 8.3+ (with `ext-redis` extension)
- Node.js 18+
- PostgreSQL 17 (local or networked)
- Redis 7+ (native, container, or a Windows-compatible Redis-protocol server)
- Composer

### Setup

```bash
# Clone and install
git clone <repo-url>
cd one-window-bayanihan
composer run setup
```

This runs: `composer install` → copy `.env` → keygen → migrate → `npm install` → build.

### Development

```bash
composer run dev
```

Starts three processes concurrently:
- **Laravel server** (`php artisan serve`)
- **Queue worker** (`php artisan queue:listen`)
- **Vite dev server** (`npm run dev` at `127.0.0.1:5173`)

### Environment Variables

Key `.env` settings:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1          # networked host in staging/production
DB_DATABASE=one_window
DB_USERNAME=your-user
DB_PASSWORD=your-password
DB_SSLMODE=prefer          # 'require' for any networked database

# Storage (S3-compatible object storage)
FILESYSTEM_DISK=object-storage
STORAGE_ENDPOINT=https://your-object-storage-endpoint
STORAGE_ACCESS_KEY=your-access-key
STORAGE_SECRET_KEY=your-secret-key
STORAGE_BUCKET=case-files
STORAGE_REGION=ap-southeast-1

# Cache/Queue/Session (Redis-backed — falls back to database if Redis unavailable)
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Mail
MAIL_MAILER=log  # or smtp in production
```

## Testing

```bash
# Run all tests (PostgreSQL required — bayanihan_test database)
composer run test

# Run specific test file
php artisan test tests/Feature/CaseControllerTest.php

# Run specific test method
php artisan test --filter test_case_manager_can_create_case

# Frontend tests
npm run test:run
```

## Project Structure

```
one-window-bayanihan/
├── app/
│   ├── Console/Commands/       # Artisan commands
│   ├── DTOs/                   # Data transfer objects
│   ├── Events/                 # Event classes
│   ├── Exceptions/             # Error codes, custom exceptions
│   ├── Helpers/                # Utility helpers
│   ├── Http/
│   │   ├── Controllers/        # Thin controllers → Services
│   │   ├── Middleware/          # Auth, RBAC, security, CSP
│   │   └── Requests/           # Form validation
│   ├── Listeners/              # Event subscribers
│   ├── Mail/                   # Mailable classes
│   ├── Models/                 # Eloquent models (UUID PKs)
│   ├── Notifications/          # Database notifications
│   ├── Observers/              # Audit logging
│   ├── Providers/              # Service providers
│   └── Services/               # Business logic layer
├── database/
│   ├── factories/              # Model factories
│   ├── migrations/             # Schema migrations
│   └── seeders/                # Data seeders
├── docs/                       # 📚 Project documentation (single source of truth)
├── resources/
│   ├── css/                    # Tailwind entry
│   └── js/
│       ├── Components/         # Reusable React components
│       ├── Hooks/              # Custom React hooks
│       ├── Layouts/            # Page layouts
│       ├── Pages/              # Inertia page components
│       ├── Schemas/            # Zod validation schemas
│       ├── Onboarding/         # Onboarding system
│       ├── lib/                # Utility functions
│       └── data/               # Static data (helpdesk articles, addresses)
├── routes/
│   ├── auth.php                # Authentication routes
│   └── web.php                 # Application routes
├── tests/
│   ├── Feature/                # Controller/middleware tests
│   └── Unit/                   # Service/model tests
└── docker/                     # Docker configuration
```

## Architecture

```
Browser → HTTPS → Laravel (Middleware) → Controller → Service → Model → PostgreSQL
         ← Inertia.js ← React Component ←
```

- **Middleware stack:** Session → Auth → CSRF → Role → IP Whitelist (admin)
- **RBAC:** `CASE_MANAGER`, `AGENCY`, `ADMIN` roles via `users.role` + `CheckRole` middleware
- **Lane isolation:** Agencies see only their referrals (application + RLS enforcement)
- **Audit:** Immutable append-only log with SHA-256 hash chain

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full system design.

## Key Conventions

| Area | Convention |
|---|---|
| Primary Keys | UUID v4 via `UsesUuid` trait |
| Soft Deletes | Flag-based: `is_deleted` + `deleted_at` + `deleted_by` |
| Controllers | Thin — delegate to `app/Services/*` |
| Validation | Form Request classes in `app/Http/Requests/` |
| Frontend | Default exports in PascalCase `.jsx` (or `.tsx` if existing) |
| Forms | Inertia `useForm()` + `useUnsavedChanges(dirty)` |
| Styling | Tailwind utilities only — no custom CSS |
| Icons | Material Symbols + lucide-react |
| State | Inertia props + local React state (no Redux) |

## Documentation

| Document | Description |
|---|---|
| [Architecture](docs/ARCHITECTURE_v2.1.0.md) | System design, middleware, deployment topology |
| [Project Rules](docs/PROJECT_RULES_v2.1.0.md) | Business rules, conventions, decisions |
| [Data Model](docs/DATA_MODEL.md) | Database schema — 31 tables, all columns |
| [API Contracts](docs/API_CONTRACTS.md) | All ~164 routes with middleware |
| [Testing Strategy](docs/TESTING_STRATEGY_v2.0.1.md) | Test approach, patterns, coverage |
| [Security](docs/SECURITY_REQUIREMENTS_v2.1.0.md) | Auth, RBAC, MFA, encryption, rate limiting |
| [Deployment Guide](docs/DEPLOYMENT_GUIDE_v3.0.0.md) | Platform capability contract, container/orchestrator/VM deployment, scaling, rollback |
| [CI/CD Guide](docs/CI_CD_GUIDE_v2.0.0.md) | Pipeline stages, deploy-trigger contract, branch protection |
| [Email Delivery](docs/EMAIL_DELIVERY_v2.0.0.md) | Domain, SPF/DKIM/DMARC, SMTP vs HTTPS-API transport |
| [Redis Integration](docs/REDIS_INTEGRATION_v2.0.0.md) | Redis setup, performance gains, architecture |
| [Audit Strategy](docs/AUDIT_STRATEGY_v2.2.0.md) | Audit log design and retention |

> Infrastructure is documented by **technology and capability**, not by hosting
> vendor. What a deployment target must provide is specified in
> [Deployment Guide §1](docs/DEPLOYMENT_GUIDE_v3.0.0.md); provider-specific
> values live only in environment variables and pipeline settings (§12).

## License

MIT
