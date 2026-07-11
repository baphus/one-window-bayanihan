# Bayanihan One Window — Documentation

Welcome to the Bayanihan One Window technical documentation. This wiki covers the architecture, modules, data layer, and infrastructure of the system.

## What Is Bayanihan One Window?

A centralized inter-agency case management system for distressed Overseas Filipino Workers (OFWs) in Region VII, built by the Department of Migrant Workers (DMW). It coordinates case handling across OWWA, DOLE, TESDA, DSWD, DOH, Law Center, and LGUs.

## Documentation Index

| Document | What It Covers |
|---|---|
| [Architecture](architecture.md) | System design, deployment topology, security layers, request flow |
| [Backend](backend.md) | Controllers, services, authentication, RBAC, middleware |
| [Frontend](frontend.md) | React/Inertia pages, components, hooks, layouts, conventions |
| [Data Model](data-model.md) | Database schema, 39 tables, relationships, migrations |
| [Infrastructure](infrastructure.md) | Docker, testing, deployment, queue, caching |

## Quick Reference

### Actors

| Actor | Access | Responsibilities |
|---|---|---|
| **System Admin** | Full (IP-restricted) | User/agency management, system config, audit review |
| **DMW Case Manager** | Cases + referrals | Intake, referral dispatch, monitoring, closure |
| **Agency Focal** | Lane-restricted | Accept/reject referrals, record milestones |
| **OFW (Public)** | OTP-verified | View case progress, submit feedback |

### Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | React 18, Inertia.js, Tailwind CSS 3 |
| Database | PostgreSQL 17 (Supabase) |
| Build | Vite 8 |
| Auth | Custom OTP MFA |
| RBAC | Spatie Laravel Permission |
| Queue | Database-driven |
| Hosting | Render |

### Key Routes

| Route | Access | Purpose |
|---|---|---|
| `/dashboard` | Authenticated | Role-based dashboard |
| `/cases` | Case Manager, Admin | Case management |
| `/referrals` | All roles | Referral management |
| `/reports` | All roles | Analytics and exports |
| `/track` | Public (OTP) | OFW case tracking |
| `/help` | Public | Helpdesk knowledge base |
| `/admin/*` | Admin (IP-restricted) | System administration |

## Source Documents

The `docs/` directory contains the original project documentation:

- `docs/ARCHITECTURE.md` — Full system architecture (SRS-based)
- `docs/PROJECT_RULES.md` — Business rules and coding conventions
- `docs/DATA_MODEL.md` — Detailed table definitions
- `docs/TESTING_STRATEGY.md` — Test approach and patterns
- `docs/API_CONTRACTS.md` — Route definitions and request/response shapes
- `docs/SECURITY_REQUIREMENTS.md` — Security controls
- `docs/DEPLOYMENT_GUIDE.md` — Build and deploy procedures
