# One Window Bayanihan — Documentation

> **Version:** 2.1.0  
> **Last Updated:** 2026-07-27  
> **Source of Truth:** This `docs/` folder is the single authoritative documentation source.
> **Platform neutrality:** Infrastructure is documented by technology and capability, never by
> hosting or managed-service vendor. See [DEPLOYMENT_GUIDE_v3.0.0.md](DEPLOYMENT_GUIDE_v3.0.0.md) §1
> for what a deployment target must provide and §12 for the only places a provider may be named.

## Overview

Bayanihan One Window is a centralized inter-agency case management system for distressed Overseas Filipino Workers (OFWs) in Region VII, built by the Department of Migrant Workers (DMW).

**"One OFW, One Entry"** — DMW Case Managers create unified case files, then refer them to partner agencies (OWWA, DOLE, TESDA, DSWD, DOH, Law Center, LGUs). Each agency works in their own lane while the system tracks progress, milestones, and closure.

## Documentation Index

### Core Architecture & Design

| Document | Description |
|----------|-------------|
| [ARCHITECTURE_v2.1.0.md](ARCHITECTURE_v2.1.0.md) | System design, middleware stack, deployment topology, data flow |
| [DATA_MODEL.md](DATA_MODEL.md) | Complete database schema — all tables, columns, relationships, indexes |
| [API_CONTRACTS.md](API_CONTRACTS.md) | All HTTP routes, methods, middleware, request/response shapes |

### Development & Conventions

| Document | Description |
|----------|-------------|
| [PROJECT_RULES_v2.1.0.md](PROJECT_RULES_v2.1.0.md) | Coding conventions, business rules, naming patterns, decisions |
| [TESTING_STRATEGY_v2.0.1.md](TESTING_STRATEGY_v2.0.1.md) | Test approach, commands, patterns, coverage |

### Operations & Security

| Document | Description |
|----------|-------------|
| [DEPLOYMENT_GUIDE_v3.0.0.md](DEPLOYMENT_GUIDE_v3.0.0.md) | Platform capability contract, environment matrix, container/orchestrator/VM deployment, migrations, scaling, rollback |
| [CI_CD_GUIDE_v2.0.0.md](CI_CD_GUIDE_v2.0.0.md) | Pipeline stages, provider-agnostic deploy-trigger contract, branch protection |
| [EMAIL_DELIVERY_v2.0.0.md](EMAIL_DELIVERY_v2.0.0.md) | MVP domain requirement, SPF/DKIM/DMARC, SMTP vs HTTPS-API transport selection |
| [REDIS_INTEGRATION_v2.0.0.md](REDIS_INTEGRATION_v2.0.0.md) | Cache/queue/OTP backend, provisioning criteria, rollout order |
| [SECURITY_REQUIREMENTS_v2.1.0.md](SECURITY_REQUIREMENTS_v2.1.0.md) | Auth flow, RBAC, MFA, CSP, rate limiting, encryption |
| [AUDIT_STRATEGY_v2.2.0.md](AUDIT_STRATEGY_v2.2.0.md) | Audit log design, append-only enforcement, hash chain |

### Supplementary

| Document | Description |
|----------|-------------|
| [UI_PATTERNS.md](UI_PATTERNS.md) | Design system, component library, layout patterns |
| [ACCESSIBILITY_REQUIREMENTS.md](ACCESSIBILITY_REQUIREMENTS.md) | WCAG 2.1 AA compliance matrix |

## Tech Stack (Verified)

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend Framework | Laravel | 13.7+ |
| Language | PHP | ^8.3 (Docker: 8.4) |
| Frontend | React | 18.2 |
| SPA Bridge | Inertia.js | 2.0 |
| Styling | Tailwind CSS | 3.2 |
| Build Tool | Vite | 8.0 |
| Database | PostgreSQL | 17 (production) / 15 (Docker local) |
| File Storage | S3-compatible object storage |
| Auth | Custom OTP + TOTP MFA (email OTP, RFC 6238 authenticator app) |
| RBAC | Custom `CheckRole` middleware (`users.role` column) |
| CAPTCHA | Bot-protection verify API (`TURNSTILE_*` keys) |
| Cache / Queue | Redis 7 (database driver as degraded fallback) |
| PDF Reports | DomPDF |
| Excel Export | PhpSpreadsheet |
| AI/Chatbot | LLM API via configurable provider |
| Error Tracking | Sentry SDK → any compatible ingest endpoint |
| Image Storage | Image CDN for avatars (optional; falls back to object storage) |
| Packaging | OCI container image (Docker) — production, staging, and local |

## Roles

| Role | Slug | Description |
|------|------|-------------|
| Case Manager | `CASE_MANAGER` | DMW staff — creates cases, sends referrals, manages clients |
| Agency Focal | `AGENCY` | Partner agency staff — processes referrals, adds milestones |
| Administrator | `ADMIN` | System admin — manages users, agencies, settings (IP-whitelisted) |

## Changelog

### v2.1.0 (2026-07-27)
- Generalised all deployment documentation: infrastructure is now described by technology and capability (PostgreSQL, S3-compatible object storage, Redis, OCI container, SMTP/HTTPS mail), never by hosting or managed-service vendor
- Added: `DEPLOYMENT_GUIDE_v3.0.0.md` (platform capability contract, environment matrix, four deployment models, migration policy, scaling model, platform-binding inventory, standards-readiness check)
- Added: `CI_CD_GUIDE_v2.0.0.md` (provider-agnostic deploy-trigger contract, CI-portability guide)
- Added: `EMAIL_DELIVERY_v2.0.0.md` — supersedes `EMAIL_DOMAIN_RESEND.md` (transport selection by platform egress instead of a named provider)
- Added: `ARCHITECTURE_v2.1.0.md`, `SECURITY_REQUIREMENTS_v2.1.0.md`, `PROJECT_RULES_v2.1.0.md`, `REDIS_INTEGRATION_v2.0.0.md`, `TESTING_STRATEGY_v2.0.1.md`
- Added: platform-neutrality rule as a project rule (`PROJECT_RULES_v2.1.0.md` §9)
- Fixed: storage configuration documented as `FILESYSTEM_DISK=object-storage` + `STORAGE_*` (the canonical names in `config/filesystems.php`); legacy `SUPABASE_S3_*` keys noted as fallbacks
- Fixed: index links now point at the current versioned documents; removed the link to the non-existent `FRONTEND.md`
- Note: superseded unversioned files are retained as history and still contain vendor names; the compliance artefacts under `docs/compliance/` and `docs/management/` intentionally keep named suppliers as audit evidence

### v2.0.0 (2026-07-11)
- Consolidated from `docs/` + `documentation/` into single source of truth
- Fixed: Laravel version 11 → 13, PHP 8.2 → 8.3/8.4
- Fixed: RBAC from "Spatie laravel-permission" → custom CheckRole middleware
- Fixed: Test database from SQLite → PostgreSQL
- Added: Turnstile CAPTCHA documentation
- Added: TOTP MFA documentation
- Added: Docker deployment documentation
- Added: Onboarding system documentation
- Added: FRONTEND.md (comprehensive frontend architecture)
- Removed: `documentation/` folder (merged into `docs/`)
- Removed: Root `ARCHITECTURE.md` (merged into `docs/ARCHITECTURE.md`)

### v1.0.0 (2026-05-28)
- Initial documentation derived from SRS document
