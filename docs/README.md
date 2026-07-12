# One Window Bayanihan — Documentation

> **Version:** 2.0.0  
> **Last Updated:** 2026-07-11  
> **Source of Truth:** This `docs/` folder is the single authoritative documentation source.

## Overview

Bayanihan One Window is a centralized inter-agency case management system for distressed Overseas Filipino Workers (OFWs) in Region VII, built by the Department of Migrant Workers (DMW).

**"One OFW, One Entry"** — DMW Case Managers create unified case files, then refer them to partner agencies (OWWA, DOLE, TESDA, DSWD, DOH, Law Center, LGUs). Each agency works in their own lane while the system tracks progress, milestones, and closure.

## Documentation Index

### Core Architecture & Design

| Document | Description |
|----------|-------------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | System design, middleware stack, deployment topology, data flow |
| [DATA_MODEL.md](DATA_MODEL.md) | Complete database schema — all tables, columns, relationships, indexes |
| [API_CONTRACTS.md](API_CONTRACTS.md) | All HTTP routes, methods, middleware, request/response shapes |

### Development & Conventions

| Document | Description |
|----------|-------------|
| [PROJECT_RULES.md](PROJECT_RULES.md) | Coding conventions, business rules, naming patterns, decisions |
| [FRONTEND.md](FRONTEND.md) | React/Inertia pages, components, hooks, state management |
| [TESTING_STRATEGY.md](TESTING_STRATEGY.md) | Test approach, commands, patterns, coverage |

### Operations & Security

| Document | Description |
|----------|-------------|
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | Build, deploy, Docker, Render, environment configuration |
| [EMAIL_DOMAIN_RESEND.md](EMAIL_DOMAIN_RESEND.md) | MVP requirement for domain purchase and Resend API-based email delivery |
| [SECURITY_REQUIREMENTS.md](SECURITY_REQUIREMENTS.md) | Auth flow, RBAC, MFA, CSP, rate limiting, encryption |
| [AUDIT_STRATEGY.md](AUDIT_STRATEGY.md) | Audit log design, append-only enforcement, hash chain |

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
| Database | PostgreSQL | 17 (Supabase) / 15 (Docker local) |
| File Storage | Supabase Storage (S3-compatible) |
| Auth | Custom OTP + TOTP MFA (email-based OTP, Google Authenticator TOTP) |
| RBAC | Custom `CheckRole` middleware (`users.role` column) |
| CAPTCHA | Cloudflare Turnstile |
| Queue | Database-driven (Laravel queue) |
| PDF Reports | DomPDF |
| Excel Export | PhpSpreadsheet |
| AI/Chatbot | OpenAI GPT API |
| Error Tracking | Sentry |
| Image Storage | Cloudinary (avatars) |
| Hosting | Render (production), Docker (local/staging) |

## Roles

| Role | Slug | Description |
|------|------|-------------|
| Case Manager | `CASE_MANAGER` | DMW staff — creates cases, sends referrals, manages clients |
| Agency Focal | `AGENCY` | Partner agency staff — processes referrals, adds milestones |
| Administrator | `ADMIN` | System admin — manages users, agencies, settings (IP-whitelisted) |

## Changelog

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
