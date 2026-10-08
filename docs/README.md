# One Window Bayanihan â€” Documentation

> **Version:** 2.4.0  
> **Last Updated:** 2026-10-02  
> **Source of Truth:** This `docs/` folder is the single authoritative documentation source.
> **Platform neutrality:** Infrastructure is documented by technology and capability, never by
> hosting or managed-service vendor. See [DEPLOYMENT_GUIDE_v3.1.0.md](DEPLOYMENT_GUIDE_v3.1.0.md) Â§1
> for what a deployment target must provide and Â§12 for the only places a provider may be named.
> Named suppliers still appear deliberately in `docs/compliance/`, `docs/management/`, and the
> provider-named deployment/costing runbooks, as audit evidence.

## Overview

Bayanihan One Window is a centralized inter-agency case management system for distressed Overseas Filipino Workers (OFWs) in Region VII, built by the Department of Migrant Workers (DMW).

**"One OFW, One Entry"** â€” DMW Case Managers create unified case files, then refer them to partner agencies (OWWA, DOLE, TESDA, DSWD, DOH, Law Center, LGUs). Each agency works in their own lane while the system tracks progress, milestones, and closure.

## How to read this index

Every file under `docs/` is listed exactly once below. Where a filename carries a version that
disagrees with the version printed inside the file, both are shown (e.g. `DATA_MODEL.md` (v2.2.0)).
Companion `.docx` files are noted inline next to their Markdown twin.

- **CURRENT** â€” the authoritative document on that topic; use this one.
- **SUPERSEDED** â€” an explicitly higher version replaces it; retained under the versioning policy.
- **HISTORICAL-ARCHIVE** â€” dated plans, specs, findings, and records kept for provenance; not
  current guidance.

**Versioning policy:** a revised document is written as `NAME_vX.Y.Z.md` and the previous file is
kept in place, marked SUPERSEDED here. Unversioned legacy copies were removed 2026-10-08; only numbered versions are retained. The highest version always wins.

## Documentation Index

### Getting started & conventions

| Document | Status | Description |
|----------|--------|-------------|
| [PROJECT_RULES_v2.1.0.md](PROJECT_RULES_v2.1.0.md) | CURRENT | Domain/business constraints, role rules, coding conventions, and the platform-neutrality rule |
| Document | Status | Description |
|----------|--------|-------------|
| [UI_PATTERNS.md](UI_PATTERNS.md) | CURRENT | Design system, component library, and layout patterns derived from SRS §A3.1/A6.5 (2026-05-28) |
| [STAGING_DATA_v1.0.0.md](STAGING_DATA_v1.0.0.md) | CURRENT | Deterministic six-month staging demo dataset — how `StagingSeeder` builds, guards, and verifies it |
| [agents/domain.md](agents/domain.md) | CURRENT | Agent guidance for deriving domain vocabulary; notes that `CONTEXT.md` and `docs/adr/` are not yet present |
| [agents/issue-tracker.md](agents/issue-tracker.md) | CURRENT | GitHub Issues workflow for agents via the `gh` CLI |
| [agents/triage-labels.md](agents/triage-labels.md) | CURRENT | Five-role triage label vocabulary (`needs-triage` … `wontfix`) |

### Architecture

| Document | Status | Description |
|----------|--------|-------------|
| [ARCHITECTURE_v2.2.0.md](ARCHITECTURE_v2.2.0.md) | CURRENT | System design â€” request flow, middleware stack, service/controller layering, deployment topology, data flow |
| [ARCHITECTURE_v2.1.0.md](ARCHITECTURE_v2.1.0.md) | SUPERSEDED | Previous revision of the architecture document |
| [ARCHITECTURE_v2.1.0.md](ARCHITECTURE_v2.1.0.md) | SUPERSEDED | Previous revision of the architecture document |
| [ARCHITECTURE_DIAGRAM_v9.6.0.md](ARCHITECTURE_DIAGRAM_v9.6.0.md) | CURRENT | High-level system architecture diagram for reviewers â€” the single current copy (earlier drafts removed) |
| [FRONTEND_ARCHITECTURE_v1.0.0.md](FRONTEND_ARCHITECTURE_v1.0.0.md) | CURRENT | React/Inertia app shell, providers, page inventory, and frontend module rules |

### Data

| Document | Status | Description |
|----------|--------|-------------|
| [DATA_MODEL.md](DATA_MODEL.md) (v2.2.0) | CURRENT | Complete database schema â€” 41 domain tables plus framework tables: columns, relationships, indexes, retention notes |
| [PSGC_ADDRESSES_v1.0.0.md](PSGC_ADDRESSES_v1.0.0.md) (content v1.1.0) | CURRENT | Philippine PSGC address dataset, lookup endpoints, and address name-resolution on write |

### API

| Document | Status | Description |
|----------|--------|-------------|
| [API_CONTRACTS.md](API_CONTRACTS.md) (v2.1.0) | CURRENT | All HTTP routes with methods, middleware, request/response shapes, and the named-throttle table (234 application + 4 vendor routes) |

### Security & authentication

| Document | Status | Description |
|----------|--------|-------------|
| [SECURITY_REQUIREMENTS_v2.2.0.md](SECURITY_REQUIREMENTS_v2.2.0.md) | CURRENT | Auth flow, RBAC, MFA, CSP, rate limiting (reconciled to code truth), and encryption requirements |
| [SECURITY_REQUIREMENTS_v2.1.0.md](SECURITY_REQUIREMENTS_v2.1.0.md) | SUPERSEDED | Previous revision of the security requirements |
| [SECURITY_REQUIREMENTS_v2.1.0.md](SECURITY_REQUIREMENTS_v2.1.0.md) | SUPERSEDED | Previous revision of the security requirements |
| [AUDIT_STRATEGY_v2.2.0.md](AUDIT_STRATEGY_v2.2.0.md) | CURRENT | Audit log design â€” append-only enforcement, hash chain, categories, export self-logging, retention and archive |
| [AUDIT_STRATEGY_v2.1.0.md](AUDIT_STRATEGY_v2.1.0.md) | SUPERSEDED | Previous revision of the audit strategy |
| [AUDIT_STRATEGY_v2.1.0.md](AUDIT_STRATEGY_v2.1.0.md) | SUPERSEDED | Previous revision of the audit strategy |
| [ROLES_AND_PERMISSIONS_v1.0.0.md](ROLES_AND_PERMISSIONS_v1.0.0.md) | CURRENT | Role model, `CheckRole`/`IpWhitelist`/MFA gates, and the route matrix â€” verified against source code |
| [MFA_LOGIN_CHALLENGE.md](MFA_LOGIN_CHALLENGE.md) | CURRENT | TOTP/recovery MFA challenge flow at `/login/mfa`. Corrected: there is no `MFA_LOGIN_CHALLENGE_ENABLED` flag â€” the challenge runs for any enrolled user whose role passes `User::isInMfaEnforcedRole()` (all roles in production); enrollment is enforced separately via `config/mfa.php` |

### Testing

| Document | Status | Description |
|----------|--------|-------------|
| [TESTING_STRATEGY_v2.1.0.md](TESTING_STRATEGY_v2.1.0.md) | CURRENT | Test approach, focused commands, and coverage expectations (234 PHPUnit test files: 216 Feature / 18 Unit) |
| [TESTING_STRATEGY_v2.0.1.md](TESTING_STRATEGY_v2.0.1.md) | SUPERSEDED | Previous revision of the testing strategy |
| [TESTING_STRATEGY_v2.0.1.md](TESTING_STRATEGY_v2.0.1.md) | SUPERSEDED | Previous revision of the testing strategy |
| [MANUAL_QA_TEST_CASES_EXPORTS_v1.0.0.md](MANUAL_QA_TEST_CASES_EXPORTS_v1.0.0.md) | CURRENT | Addendum covering the rebuilt report exports â€” limits, small-cell suppression, and the export audit trail |

### Deployment

| Document | Status | Description |
|----------|--------|-------------|
| [DEPLOYMENT_GUIDE_v3.1.0.md](DEPLOYMENT_GUIDE_v3.1.0.md) | CURRENT | Platform capability contract, environment matrix, deployment models, migration policy, scaling, rollback |
| [DEPLOYMENT_GUIDE_v3.0.0.md](DEPLOYMENT_GUIDE_v3.0.0.md) | SUPERSEDED | Previous revision of the deployment guide |
| [CI_CD_GUIDE_v2.1.0.md](CI_CD_GUIDE_v2.1.0.md) | CURRENT | CI stages and the provider-agnostic deploy-trigger contract, verified against the four workflows |
| [CI_CD_GUIDE_v2.0.0.md](CI_CD_GUIDE_v2.0.0.md) | SUPERSEDED | Previous revision of the CI/CD guide |
| [CI_CD_GUIDE.md](CI_CD_GUIDE.md) | SUPERSEDED | Unversioned original; retained as history |
| [DEPLOYMENT_PRODUCTION_AWS_v1.6.0.md](DEPLOYMENT_PRODUCTION_AWS_v1.6.0.md) | CURRENT | Provider-named production runbook (a provider may be named only in the places allowed by `DEPLOYMENT_GUIDE_v3.1.0.md` Â§12) |
| [DEPLOYMENT_PRODUCTION_AWS_v1.5.0.md](DEPLOYMENT_PRODUCTION_AWS_v1.5.0.md) | SUPERSEDED | Previous revision of the production runbook |
| [DEPLOYMENT_STAGING_AWS_v1.3.0.md](DEPLOYMENT_STAGING_AWS_v1.3.0.md) | CURRENT | Provider-named staging environment runbook |
| [DEPLOYMENT_STAGING_AWS_v1.2.0.md](DEPLOYMENT_STAGING_AWS_v1.2.0.md) | SUPERSEDED | Previous revision of the staging runbook |
| [DEPLOYMENT_STAGING_AWS_v1.0.0.md](DEPLOYMENT_STAGING_AWS_v1.0.0.md) | SUPERSEDED | Original staging runbook |
| [DEPLOYMENT_COSTING_v2.0.0.md](DEPLOYMENT_COSTING_v2.0.0.md) | CURRENT | Provider-named deployment cost baseline (point-in-time pricing snapshot, not a live quote). Supersedes v1.0.0: corrects the AI-chatbot row â€” the chatbot uses tool-based article reading, not pgvector retrieval (`2026_09_16_000001_drop_chatbot_embeddings_table.php`, `.env.example:185`) |
| [DEPLOYMENT_COSTING_v1.0.0.md](DEPLOYMENT_COSTING_v1.0.0.md) (companion `DEPLOYMENT_COSTING_v1.0.0.docx`) | SUPERSEDED | Superseded by v2.0.0 â€” its pgvector claim is wrong; figures otherwise unchanged |

### Compliance, quality & requirements evidence

All entries are CURRENT. Named suppliers appear here deliberately as audit evidence.

| Document | Status | Description |
|----------|--------|-------------|
| [compliance/system-and-service-profile-v1.0.0.md](compliance/system-and-service-profile-v1.0.0.md) | CURRENT | Scope statement â€” modules, interfaces, user types, and environments covered by the standards effort |
| [compliance/iso-9001-gap-assessment-v1.0.0.md](compliance/iso-9001-gap-assessment-v1.0.0.md) | CURRENT | Gap assessment against ISO 9001 QMS clauses |
| [compliance/iso-27001-gap-assessment-v1.0.0.md](compliance/iso-27001-gap-assessment-v1.0.0.md) | CURRENT | Gap assessment against ISO/IEC 27001 controls |
| [compliance/iso-27002-control-assessment-v1.0.0.md](compliance/iso-27002-control-assessment-v1.0.0.md) | CURRENT | Control-by-control implementation status against ISO/IEC 27002 |
| [compliance/iso-20000-1-gap-assessment-v1.0.0.md](compliance/iso-20000-1-gap-assessment-v1.0.0.md) | CURRENT | Gap assessment against ISO/IEC 20000-1 service management clauses |
| [compliance/iso-consolidated-control-matrix-v1.0.0.md](compliance/iso-consolidated-control-matrix-v1.0.0.md) | CURRENT | Single matrix mapping every control across 9001/27001/27002/20000-1 with status, risk, and action |
| [compliance/iso-alignment-executive-summary-v1.0.0.md](compliance/iso-alignment-executive-summary-v1.0.0.md) | CURRENT | Executive summary of standards-alignment posture and top findings |
| [compliance/iso-remediation-roadmap-v1.0.0.md](compliance/iso-remediation-roadmap-v1.0.0.md) | CURRENT | Prioritized remediation roadmap for the standards gap findings, with owners and validation |
| [compliance/technical-security-and-quality-findings-v1.0.0.md](compliance/technical-security-and-quality-findings-v1.0.0.md) | CURRENT | `TECH-*` findings register from the security/quality review with remediation and validation status |
| [compliance/information-risk-register-v1.0.0.md](compliance/information-risk-register-v1.0.0.md) | CURRENT | Information security risk register â€” risks, controls, owners, and treatment status |
| [compliance/external-evidence-required-v1.0.0.md](compliance/external-evidence-required-v1.0.0.md) | CURRENT | Evidence records each control expects to produce for external audit |
| [management/quality-policy.md](management/quality-policy.md) | CURRENT | Quality policy statement for the QMS |
| [management/quality-objectives.md](management/quality-objectives.md) | CURRENT | Measurable quality objectives and targets |
| [management/service-catalogue.md](management/service-catalogue.md) | CURRENT | Catalogue of services the system supports, with descriptions and ownership |
| [management/capa-register.md](management/capa-register.md) | CURRENT | Corrective and preventive action register for audit findings |
| [management/access-review-procedure.md](management/access-review-procedure.md) | CURRENT | Periodic user access review procedure â€” participants, evidence, and cadence |
| [management/bcp-dr-plan.md](management/bcp-dr-plan.md) | CURRENT | Business continuity and disaster recovery plan with per-module RTO/RPO |
| [management/capacity-plan.md](management/capacity-plan.md) | CURRENT | Capacity planning for compute, storage, database, and queue growth |
| [procedures/change-management.md](procedures/change-management.md) | CURRENT | Change management procedure from request through release and rollback |
| [procedures/incident-response.md](procedures/incident-response.md) | CURRENT | Security and operational incident response procedure â€” triage, containment, reporting |
| [REQUIREMENTS_TRACEABILITY.md](REQUIREMENTS_TRACEABILITY.md) | CURRENT | Every SRS requirement (FR/NFR/BR/LEGAL/DB/ACC/COM) mapped to implementation and verification evidence (2026-05-28) |
| [IMPLEMENTATION_BACKLOG.md](IMPLEMENTATION_BACKLOG.md) | CURRENT | Backlog of SRS requirement gaps from the traceability matrix (status snapshot 2026-05-28 â€” re-verify counts before quoting) |
| [ACCESSIBILITY_REQUIREMENTS.md](ACCESSIBILITY_REQUIREMENTS.md) | CURRENT | WCAG 2.1 AA compliance matrix derived from SRS Â§A6.5 (2026-05-28) |

### Features & subsystems

| Document | Status | Description |
|----------|--------|-------------|
| [CHATBOT_PIPELINE_v1.0.0.md](CHATBOT_PIPELINE_v1.0.0.md) | CURRENT | Chatbot request pipeline and its eleven services, configuration surface, and verification procedure |
| [CHATBOT_AGENT.md](CHATBOT_AGENT.md) | SUPERSEDED | Older helpdesk-chatbot description; replaced by `CHATBOT_PIPELINE_v1.0.0.md`, retained as history |
| [REPORTS_EXPORT_v1.1.0.md](REPORTS_EXPORT_v1.1.0.md) | CURRENT | Current reports/export behaviour â€” PDF and Excel routes, limits, pre-flight row caps, and suppression rules |
| [REPORTS_EXPORT_DESIGN_v1.0.0.md](REPORTS_EXPORT_DESIGN_v1.0.0.md) | SUPERSEDED | Measurement evidence behind the export rebuild; retained as history |
| [CLIENT_REQUEST_INBOX_ROLLOUT.md](CLIENT_REQUEST_INBOX_ROLLOUT.md) | CURRENT | Rollout scope for agencyâ†’client document requests answered through the tracking experience |

### Operations

| Document | Status | Description |
|----------|--------|-------------|
| [REDIS_INTEGRATION_v2.1.0.md](REDIS_INTEGRATION_v2.1.0.md) | CURRENT | Redis as cache/queue/session backend â€” provisioning criteria and rollout order (status: Implemented) |
| [REDIS_INTEGRATION_v2.0.0.md](REDIS_INTEGRATION_v2.0.0.md) | SUPERSEDED | Previous revision of the Redis integration guide |
| [REDIS_INTEGRATION.md](REDIS_INTEGRATION.md) | SUPERSEDED | Unversioned original; retained as history |
| [EMAIL_DELIVERY_v2.1.0.md](EMAIL_DELIVERY_v2.1.0.md) | CURRENT | Mail domain requirement, SPF/DKIM/DMARC, transport selection, delivery-event tracking, and the webhook endpoint |
| [EMAIL_DELIVERY_v2.0.0.md](EMAIL_DELIVERY_v2.0.0.md) | SUPERSEDED | Previous revision of the email delivery guide |
| [EMAIL_DOMAIN_RESEND.md](EMAIL_DOMAIN_RESEND.md) | SUPERSEDED | Tombstoned 2026-09-15 â€” do not use; replaced by `EMAIL_DELIVERY_v2.1.0.md` |
| [WARD_REMEDIATION_PLAN.md](WARD_REMEDIATION_PLAN.md) | CURRENT | Static-analysis scanner findings and their remediations, plus the standing pre-production checklist |

### Appendices

| Document | Status | Description |
|----------|--------|-------------|
| [appendices/Appendix_N_List_of_Modules.md](appendices/Appendix_N_List_of_Modules.md) (Word originals alongside: `Appendix_N_List_of_Modules.docx`, `Appendix_N_List_of_Modules_Revised.docx`) | CURRENT | Appendix N â€” list of system modules for formal submission |
| [MANUSCRIPT_CODEBASE_INCONSISTENCIES.md](MANUSCRIPT_CODEBASE_INCONSISTENCIES.md) | CURRENT | **Authoritative manuscript-vs-code audit - the single source of truth.** 11 findings where the manuscript makes a claim about the system that the code contradicts. Each carries part/chapter/section, table number, a Ctrl+F-searchable string, the quoted claim, code evidence with file:line, and the fix. Also includes a manuscript map, 7 cleared claims not to "fix", 2 retractions that would damage the manuscript if acted on, 3 items needing a human decision, and 2 leads still under verification. Corrects 7 fabrications found in the earlier audit. |
| _(archived 2026-10-03)_ | SUPERSEDED | Two earlier audit files were removed from `docs/` and archived, nothing lost: `MANUSCRIPT_CONSISTENCY_CHECKLIST.txt` (Revision 2 - 46 findings, mostly manuscript-internal; its byte-identical master is retained outside the repository) and `MANUSCRIPT_FIX_BATCHES.md` (a section index over the lost Revision 4, matching no surviving file). Both were renamed with a `SUPERSEDED_` prefix and moved out of the repository. |

### Historical archive

Dated plans, specs, findings, and records kept for provenance only â€” not current guidance.

| Document | Status | Description |
|----------|--------|-------------|
| [plans/2026-07-09-comprehensive-compliance-remediation.md](plans/2026-07-09-comprehensive-compliance-remediation.md) | HISTORICAL-ARCHIVE | Dated plan for the compliance remediation work packages |
| [plans/2026-07-09-helpdesk-articles-overhaul.md](plans/2026-07-09-helpdesk-articles-overhaul.md) | HISTORICAL-ARCHIVE | Dated plan to overhaul helpdesk articles and screenshots |
| [plans/2026-09-08-audit-coverage-gaps.md](plans/2026-09-08-audit-coverage-gaps.md) | HISTORICAL-ARCHIVE | Dated plan closing audit-coverage gaps |
| [plans/2026-09-21-agency-focal-service-assignment.md](plans/2026-09-21-agency-focal-service-assignment.md) | HISTORICAL-ARCHIVE | Dated plan for agency-focal service assignment |
| [superpowers/plans/2026-07-27-aws-staging-deployment-v1.0.0.md](superpowers/plans/2026-07-27-aws-staging-deployment-v1.0.0.md) | HISTORICAL-ARCHIVE | Staging deployment plan (dated 2026-07-27) |
| [superpowers/specs/2026-07-11-role-dashboards-redesign-design-v1.0.0.md](superpowers/specs/2026-07-11-role-dashboards-redesign-design-v1.0.0.md) | HISTORICAL-ARCHIVE | Role dashboard redesign spec (original) |
| [superpowers/specs/2026-07-11-role-dashboards-redesign-design-v1.0.1.md](superpowers/specs/2026-07-11-role-dashboards-redesign-design-v1.0.1.md) | HISTORICAL-ARCHIVE | Role dashboard redesign spec (revision) |
| [superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md](superpowers/specs/2026-07-27-aws-staging-deployment-design-v1.0.0.md) | HISTORICAL-ARCHIVE | Staging deployment design record |
| [superpowers/specs/2026-07-27-resend-delivery-webhooks-design-v1.0.0.md](superpowers/specs/2026-07-27-resend-delivery-webhooks-design-v1.0.0.md) | HISTORICAL-ARCHIVE | Delivery-event webhook design record |
| [presentations/DICT_REGION_VII_SYSTEM_PRESENTATION_PLAN_v1.0.0.md](presentations/DICT_REGION_VII_SYSTEM_PRESENTATION_PLAN_v1.0.0.md) | HISTORICAL-ARCHIVE | Plan/script for the DICT Region VII system presentation |
| [presentations/CLAUDE_DESIGN_PROMPT_DICT_REGION_VII_v1.0.0.md](presentations/CLAUDE_DESIGN_PROMPT_DICT_REGION_VII_v1.0.0.md) | HISTORICAL-ARCHIVE | Design prompt used to produce the presentation deck |
| [FEEDBACK_FEATURE_RESEARCH_2026-09-16.md](FEEDBACK_FEATURE_RESEARCH_2026-09-16.md) | HISTORICAL-ARCHIVE | Research record on the retired Gen-1 SERVQUAL feedback stack (tables dropped) |
| [DB_SCHEMA_DEAD_TABLE_AUDIT_2026-09-16.md](DB_SCHEMA_DEAD_TABLE_AUDIT_2026-09-16.md) | HISTORICAL-ARCHIVE | Point-in-time dead-table audit of the live schema (no code changed) |
| [SCANNER_REMEDIATION_2026-09.md](SCANNER_REMEDIATION_2026-09.md) | HISTORICAL-ARCHIVE | Record of remediations for the 2026-09-01 external website scanner report |
| [E2E_TEST_FINDINGS_v1.0.0.md](E2E_TEST_FINDINGS_v1.0.0.md) | HISTORICAL-ARCHIVE | Archived findings from the single 2026-07-27 staging E2E run; counts are not current |
| [MFA_ROLLOUT.md](MFA_ROLLOUT.md) | CURRENT | MFA enrollment enforcement and challenge tuning. Corrected: there is no `MFA_LOGIN_CHALLENGE_ENABLED` flag to activate; deployments configure `MFA_ENROLLMENT_ENFORCEMENT_ENABLED` and `MFA_ENROLLMENT_ENFORCED_ROLES` in `config/mfa.php` |

## Tech Stack (Verified)

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend Framework | Laravel | ^13.7 (`composer.lock`: v13.34.0) |
| Language | PHP | >=8.4.1 <9.0 |
| Frontend | React | 18.2 |
| SPA Bridge | Inertia.js | 2.0 |
| Styling | Tailwind CSS | 3.2 |
| Build Tool | Vite | 8.0 |
| Database | PostgreSQL | 17 (CI) / 15 (Docker local) |
| File Storage | S3-compatible object storage |
| Auth | Email + password login, then TOTP MFA (RFC 6238 authenticator app). OTP is used for email-change verification and public intake/tracking email verification â€” **not** for login |
| RBAC | Custom `CheckRole` middleware (`users.role` column) |
| CAPTCHA | Bot-protection verify API (`TURNSTILE_*` keys) |
| Cache / Queue | Redis 7 (database driver as degraded fallback) |
| PDF Reports | DomPDF |
| Excel Export | PhpSpreadsheet |
| AI/Chatbot | LLM API via configurable provider |
| Error Tracking | Sentry SDK â†’ any compatible ingest endpoint |
| Image Storage | Avatars on the private object-storage disk via short-lived signed URLs; optional external image-hosting service when configured |
| Packaging | OCI container image (Docker) â€” production, staging, and local |

## Roles

| Role | Slug | Description |
|------|------|-------------|
| Case Manager | `CASE_MANAGER` | DMW staff â€” creates cases, sends referrals, manages clients |
| Agency Focal | `AGENCY` | Partner agency staff â€” processes referrals, adds milestones |
| Administrator | `ADMIN` | System admin â€” manages users, agencies, settings (IP-whitelisted) |
| Overseas Filipino Worker | `OFW` | Portal user â€” views own cases, referrals, and milestones at `/my-cases` (`routes/web.php:445`) |

## Changelog

### v2.4.0 (2026-10-02)
- Rebuilt: the index now lists every file under `docs/` exactly once, grouped by topic, with version, one-line description, and status (CURRENT / SUPERSEDED / HISTORICAL-ARCHIVE)
- Added: previously unindexed material â€” `ARCHITECTURE_DIAGRAM_v9.6.0.md`, `IMPLEMENTATION_BACKLOG.md`, `REQUIREMENTS_TRACEABILITY.md`, `DEPLOYMENT_COSTING_v1.0.0.md`, `WARD_REMEDIATION_PLAN.md`, `CLIENT_REQUEST_INBOX_ROLLOUT.md`, `MFA_LOGIN_CHALLENGE.md`, `MFA_ROLLOUT.md`, `DB_SCHEMA_DEAD_TABLE_AUDIT_2026-09-16.md`, `SCANNER_REMEDIATION_2026-09.md`, `E2E_TEST_FINDINGS_v1.0.0.md`, `FEEDBACK_FEATURE_RESEARCH_2026-09-16.md`, all superseded versions of the guides, and everything under `agents/`, `appendices/`, `compliance/`, `management/`, `plans/`, `presentations/`, `procedures/`, and `superpowers/`
- Removed: `CAPSTONE_MANUSCRIPT_INCONSISTENCIES.txt` â€” a 679-line report built against an older manuscript. It was superseded by `MANUSCRIPT_CONSISTENCY_CHECKLIST.md`, which is itself now superseded by `MANUSCRIPT_CONSISTENCY_CHECKLIST.txt`
- Added: OFW row to the Roles table (`routes/web.php:445-446`)
- Fixed: image storage row now reflects code truth â€” avatars live on the private object-storage disk behind signed URLs first, with an optional external image host
- Fixed: Laravel version note now cites `composer.lock` (v13.34.0)
- Documented: `MANUAL_QA_TEST_CASES.md` corrected to v1.0.1 â€” authentication, dashboard, and data-export expectations reconciled against current code

### v2.3.0 (2026-09-16)
- Added: `SECURITY_REQUIREMENTS_v2.2.0.md` â€” rate-limit reconciliation against `AppServiceProvider.php` code truth (all 28 named limiters corrected; stale `/login/verify-otp` + `/login/resend-otp` rows replaced with the routed `AuthenticatedSessionController`/`MfaChallengeController` reality)
- Fixed: index now references all current docs â€” `ARCHITECTURE_v2.2.0.md`, `FRONTEND_ARCHITECTURE_v1.0.0.md`, `ROLES_AND_PERMISSIONS_v1.0.0.md`, `DATA_MODEL.md` (v2.2.0), `PSGC_ADDRESSES_v1.0.0.md`, `API_CONTRACTS.md` (v2.1.0), `CHATBOT_PIPELINE_v1.0.0.md` (noting it supersedes `CHATBOT_AGENT.md`), `REPORTS_EXPORT_v1.1.0.md`, `DEPLOYMENT_GUIDE_v3.1.0.md`, `CI_CD_GUIDE_v2.1.0.md`, `TESTING_STRATEGY_v2.1.0.md`, `REDIS_INTEGRATION_v2.1.0.md`, `STAGING_DATA_v1.0.0.md`, and the `EMAIL_DOMAIN_RESEND.md` superseded banner
- Fixed: tech-stack floor PHP `^8.3` â†’ `>=8.4.1 <9.0` and Laravel `13.7+` â†’ `^13.7` (installed 13.24.0) per `composer.json`/`composer.lock` code truth

### v2.2.0 (2026-07-27)
- Added: `EMAIL_DELIVERY_v2.1.0.md` â€” supersedes `EMAIL_DELIVERY_v2.0.0.md`; documents delivery-event tracking (provider message-ID correlation, Svix-signed webhook endpoint, monotonic delivery-status vocabulary), the `mail:verify-transport` command, and unverified-domain sending restrictions
- Note: `DEPLOYMENT_GUIDE_v3.0.0.md` Â§4 still cross-references `EMAIL_DELIVERY_v2.0.0.md`; the pointer updates at that guide's next revision

### v2.1.0 (2026-07-27)
- Generalised all deployment documentation: infrastructure is now described by technology and capability (PostgreSQL, S3-compatible object storage, Redis, OCI container, SMTP/HTTPS mail), never by hosting or managed-service vendor
- Added: `DEPLOYMENT_GUIDE_v3.0.0.md` (platform capability contract, environment matrix, four deployment models, migration policy, scaling model, platform-binding inventory, standards-readiness check)
- Added: `CI_CD_GUIDE_v2.0.0.md` (provider-agnostic deploy-trigger contract, CI-portability guide)
- Added: `EMAIL_DELIVERY_v2.0.0.md` â€” supersedes `EMAIL_DOMAIN_RESEND.md` (transport selection by platform egress instead of a named provider)
- Added: `ARCHITECTURE_v2.1.0.md`, `SECURITY_REQUIREMENTS_v2.1.0.md`, `PROJECT_RULES_v2.1.0.md`, `REDIS_INTEGRATION_v2.0.0.md`, `TESTING_STRATEGY_v2.0.1.md`
- Added: platform-neutrality rule as a project rule (`PROJECT_RULES_v2.1.0.md` Â§9)
- Fixed: storage configuration documented as `FILESYSTEM_DISK=object-storage` + `STORAGE_*` (the canonical names in `config/filesystems.php`); legacy `SUPABASE_S3_*` keys noted as fallbacks
- Fixed: index links now point at the current versioned documents; removed the link to the non-existent `FRONTEND.md`
- Note: the superseded unversioned copies (which still contained vendor names) were removed 2026-10-08; the compliance artefacts under `docs/compliance/` and `docs/management/` intentionally keep named suppliers as audit evidence

### v2.0.0 (2026-07-11)
- Consolidated from `docs/` + `documentation/` into single source of truth
- Fixed: Laravel version 11 â†’ 13, PHP 8.2 â†’ 8.3/8.4
- Fixed: RBAC from "Spatie laravel-permission" â†’ custom CheckRole middleware
- Fixed: Test database from SQLite â†’ PostgreSQL
- Added: Turnstile CAPTCHA documentation
- Added: TOTP MFA documentation
- Added: Docker deployment documentation
- Added: Onboarding system documentation
- Added: FRONTEND.md (comprehensive frontend architecture)
- Removed: `documentation/` folder (merged into `docs/`)
- Removed: root `ARCHITECTURE.md` (merged into `docs/ARCHITECTURE.md`)

### v1.0.0 (2026-05-28)
- Initial documentation derived from SRS document
