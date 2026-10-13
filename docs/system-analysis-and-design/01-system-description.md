# One Window Bayanihan — System Description

> **Document:** 01 — System Description (System Analysis and Design series)
> **Version:** 1.1.0
> **Date:** 2026-10-11
> **Status:** CURRENT
> **Audience:** DICT reviewers, capstone panelists, and business stakeholders
> **Note:** Written for non-technical readers. Infrastructure is described by capability, never by hosting vendor.

## How to read the status words

Each factual claim carries one plain status word:

| Status word | Meaning |
|---|---|
| **Available** | Working in the current system and can be demonstrated. |
| **Partly available** | Working, but with a known limitation or switched off by default. |
| **Not available** | Looked for and genuinely not present. |
| **To be confirmed** | An open question; the project team should confirm before external publication. |

---

## Table of contents

1. [System name and purpose](#1-system-name-and-purpose)
2. [Background](#2-background)
3. [Objectives](#3-objectives)
4. [Users and stakeholders](#4-users-and-stakeholders)
5. [System overview](#5-system-overview)
6. [Key features](#6-key-features)
7. [Technology in brief](#7-technology-in-brief)
8. [Responsibilities of the main parts](#8-responsibilities-of-the-main-parts)
9. [Data approach](#9-data-approach)
10. [External services](#10-external-services)
11. [System boundaries and dependencies](#11-system-boundaries-and-dependencies)
12. [Current status](#12-current-status)
13. [Limitations and open questions](#13-limitations-and-open-questions)

---

## 1. System name and purpose

**System name:** One Window Bayanihan.

**Purpose:** A centralized inter-agency case management system for distressed Overseas Filipino Workers (OFWs) in Region VII, operated by the Department of Migrant Workers (DMW). It puts the **"One OFW, One Entry"** principle into practice: a DMW Case Manager opens one unified case file per OFW, then refers that case to partner agencies (OWWA, DOLE, TESDA, DSWD, DOH, law centers, LGUs) with the OFW's available documents attached. Each receiving agency then picks the service it will deliver from its own list of available services, and works its own lane while the system tracks progress, milestones, client compliance requests, and closure. **Available**

OFWs themselves are a secondary audience: after creating an account and signing in, they can file a case online; they can track it on the public tracker with the tracking number plus an email code, and also in the portal when signed in, and they can answer agency document requests through private time-limited links and take satisfaction surveys. **Available**

## 2. Background

Distressed-OFW case handling traditionally spans several government offices, forcing workers to repeat their story at every counter. One Window Bayanihan consolidates intake into a single case file owned by DMW, then distributes the work to partner agencies through referrals, while keeping one audit trail and one progress view for everyone involved. **Available**

## 3. Objectives

1. **Single entry point** — one case file per OFW, regardless of how many agencies must help. **Available**
2. **Controlled case lifecycle** — drafts, review, publish, close, archive, and removal, with each step allowed only when its conditions are met. **Available**
3. **Predictable inter-agency referral** — the Case Manager sends the referral with the OFW's documents attached, the receiving agency picks the service from its own list, and the referral then follows a fixed path: pending → processing or waiting on the client → completed or rejected, with no skipped steps. **Available**
4. **Client self-service with an account** — filing requires creating an account and signing in; tracking is public via tracking number plus email code, and also visible in the portal for a signed-in OFW; 7-day private links remain for document requests. **Available**
5. **Accountability** — every significant action is recorded in an audit trail; audit records are archived into tamper-evident bundles and integrity-checked. **Available**
6. **Role separation** — four roles (Case Manager, Agency, Admin, OFW) with access enforced at every screen and action. **Available**
7. **Operational visibility** — dashboards, reports, overdue-referral monitoring, and a system readiness check. **Available**

## 4. Users and stakeholders

| User | Who they are | What they use the system for | Status |
|---|---|---|---|
| **DMW Case Manager** | DMW Region VII staff handling distressed-OFW cases | Create and own case files, review self-filed intakes, send referrals with the OFW's documents attached, close cases, view reports | Available |
| **Partner agency staff** | Staff of OWWA, DOLE, TESDA, DSWD, DOH, law centers, LGUs | Pick the service for a referral from their own list of available services, work referred cases, update progress, ask clients for documents, message other agencies, design survey forms | Available |
| **System administrator** | Operators of the system | Manage users, partner agencies, reference lists (categories, statuses), settings, logs, data export, audit viewing | Available |
| **OFW / next of kin (registered)** | The worker or a family member with a personal account | See their own cases, agency milestones, notifications, and personal profile | Available |
| **OFW / family member (no account)** | Anyone on the public internet | Read help, contact DMW, answer document requests via a private link, take a survey, use the helpdesk | Available |

**Stakeholders:** DMW Region VII (system owner and operator); partner agencies (OWWA, DOLE, TESDA, DSWD, DOH, law centers, LGUs); the OFWs and their families; and the team that operates the hosting environment. **Available** (agency roster confirmed in project documentation)

## 5. System overview

The system is a web application backed by a central database, offering three experiences behind one sign-in world: a **staff portal** (Case Managers, agency staff, administrators), a **personal portal** for registered OFWs, and a **public area** where anyone can get help, contact DMW, track a case with the tracking number plus an email code, answer document requests, or complete a survey — filing a case happens after creating an account and signing in, while tracking is also visible in the portal for a signed-in OFW. **Available**

Heavy work — sending emails, preparing notifications, generating reports, issuing survey invitations — runs in the background so screens stay responsive. Regular housekeeping runs on a schedule: archiving audit records, cleaning up unused files, and permanently removing trashed cases after the retention period. **Available**

## 6. Key features

| Feature | What it does for the business | Status |
|---|---|---|
| Unified case file | One case per OFW no matter how many agencies are involved ("One OFW, One Entry"). | Available |
| Case lifecycle control | Draft, review, publish, close, archive, and remove cases — each step only when its conditions are met. | Available |
| Inter-agency referral | Route a case to a partner agency; the receiving agency picks the service from its own list, and the referral then follows a fixed status path (pending, processing, waiting on client, completed, rejected). | Available |
| Public self-filing | OFWs or families open a case online after creating an account and signing in (with verification and two-step where required). | Available |
| Intake review queue | Case Managers review self-filed requests and either take them up or decline, notifying the filer. | Available |
| Overdue referral watch | Referrals idle past a set period (7 days by default) appear on an administrator's dashboard for manual follow-up. | Available |
| Agency-to-agency messaging | A private conversation per case between two partner agencies. | Available |
| Document request inbox | Agencies ask clients for specific documents or answers through a private link that expires after 7 days. | Available |
| Public case tracking | Anyone with the tracking number and email access checks progress on the public tracker with an email code; a signed-in OFW also sees it in the portal (tracker number shown on the filing receipt). | Available |
| Account before filing | A new visitor creates a personal account and signs in before filing a case. | Available |
| OFW personal portal | Registered OFWs see their own cases, agency milestones, notifications, and profile. | Available |
| Notifications | In-app notices plus emails at key events (new intake, status change, document request, milestone). | Available |
| Satisfaction survey | When a referral completes, the client is invited (30-day link) to rate that agency's service. | Available |
| Reports and exports | Operational dashboards plus management exports in PDF and Excel. | Available |
| Audit trail | Every significant action is recorded; records are archived monthly and integrity-checked; kept one year by default. | Available |
| Secure document handling | Uploads are checked for malware and file type before storage; if the checker is unavailable, uploads are still accepted (see §13). | Partly available |
| Sign-in security | Email and password plus a multi-factor code, attempt limits, and recovery options; administrators can reset it. | Available |
| Email verification codes | Short-lived codes for public filing, tracking, and email changes. | Available |
| Email delivery tracking | The system records whether emails were actually delivered; staff can resend. | Available |
| Helpdesk and chat assistant | Help pages plus a chat assistant for common questions; the assistant is switched off by default until enabled. | Partly available |
| Guided start for new staff | A short post-login checklist; dismissible at any time. | Available |
| Public contact form | Public enquiries routed to a configured recipient, with abuse protection built in; the bot check is currently off by default. | Available |

## 7. Technology in brief

The system runs as a single packaged application on a central server, using an open-source database (PostgreSQL) as its record system, a background processing service for emails, notifications and exports, and cloud file storage for case documents. It sends mail through an email delivery provider and can optionally connect to a chat assistant provider, a profile-photo service, a bot-protection service, and an error-monitoring service. Because everything is packaged together, any hosting platform that provides those capabilities can run it.

## 8. Responsibilities of the main parts

- **The browser experience** — everything a user does happens in an ordinary web browser; nothing needs to be installed on the user's device. **Available**
- **The central server** — verifies identity and role at every step, enforces the rules of each process (who may act, what must be true before a step proceeds), holds the authoritative record, writes the audit trail, and serves all three user experiences. **Available**
- **Background processing** — sends emails and notifications, generates reports, and prepares survey invitations without slowing down the screens staff and clients are using. **Available**
- **Scheduled housekeeping** — nightly and weekly routines: permanent removal of trashed cases after the retention window, audit archiving and integrity checks, cleanup of unused files, and log rotation. **Available**

## 9. Data approach

- **One central database** is the single source of truth for cases, clients, partner agencies, referrals, milestones, survey answers, and audit records. Reports and dashboards depend on this database directly, so it is a firm requirement, not interchangeable. **Available**
- **Documents live in cloud file storage**, not in the database itself; every upload is malware-scanned and type-checked before it is stored. **Partly available** (see §13)
- **Personal information is seen only by the roles that need it:** OFWs see only their own file; agency staff see only referrals sent to their agency; Case Managers see their own cases plus the intake queue; administrators oversee everything. **Available**
- **Audit records** are written as work happens, archived monthly into tamper-evident bundles, integrity-checked weekly, and kept one year by default. Cases moved to trash are permanently removed automatically after the retention window. **Available**

## 10. External services

| Dependency | What it provides | Status |
|---|---|---|
| **Database and background processing platform** | Holds all records; powers background queues and login sessions. Mandatory for the system to start. | Available |
| **Cloud file storage** | Stores case documents, referral attachments, and audit archives. Needs storage configured. | Available |
| **Email delivery provider** | Sends all system emails and reports back whether they were delivered. Needs provider credentials. | Available |
| **Philippine address data** | Auto-complete for Philippine addresses in forms. | Available |
| **Chat assistant provider** | Optional AI answering for the helpdesk chat; off by default. | Partly available |
| **Profile photo service** | Optional hosting for user profile photos. | Available |
| **Bot protection service** | Optional anti-abuse check on public forms; off by default. | Partly available |
| **Error monitoring service** | Optional alerting when technical faults occur. | Available |

No credentials, keys, or secrets are described in this document; all are configured privately on the deployment.

## 11. System boundaries and dependencies

**Inside the boundary:** case data, client personal information, the referral workflow, agency messaging, surveys, the audit trail, helpdesk content, and all file storage.

**Outside the boundary (runtime dependencies):** the database platform, the background-processing service, file storage, the email provider, and the optional services above (chat assistant, profile photos, bot protection, error monitoring). Only the database and background-processing service are mandatory for the system to start; everything else is optional or switched on by configuration. **Available**

**Human and organizational dependency:** partner agencies must keep active user accounts and respond to referrals; OFWs must uptake the filing portal and case tracking (public tracker plus portal view); and production releases depend on operator discipline, since the release step is manual and gated by a typed confirmation. **To be confirmed** (organizational)

## 12. Current status

**Overall: implemented and operational-grade.** **Available**

- The full journey works end to end: intake → draft → publish → referral → the agency picks its service → client compliance → completion → survey → closure → archive → removal, each step with audit records and notifications.
- Security is hardened in depth: multi-factor sign-in with attempt limits, private expiring links instead of public file addresses, protections against guessing and abuse on every public form.
- Operations tooling exists: system readiness check, log viewing, maintenance mode, active-session management, audit integrity verification, orphan cleanup, and retention removals.

**Gaps in evidence, not necessarily in the product** — **To be confirmed:** live deployment state, independent penetration-test results, load-test results, and accessibility conformance results.

## 13. Limitations and open questions

Framed as business risks and questions for the project team:

1. **Helpdesk chat assistant — which story is current?** The assistant is switched off by default, and two different descriptions of how it answers (simple keyword matching vs. AI-assisted) exist in project documentation. Confirm the intended external description before publication. **To be confirmed**
2. **Document scanning fails open.** If the malware checker is unavailable, uploads are still accepted. For a casework system handling personal documents, confirm this is the intended policy, or whether uploads should be blocked instead. **To be confirmed**
3. **No separate trial environment.** Production releases are manual and gated by typing a confirmation phrase; there is no staging environment for a dry run. Confirm whether one is planned. **To be confirmed**
4. **Accessibility conformance not yet demonstrated.** Accessibility requirements exist as a document, but no conformance test results are on record — relevant to government accessibility expectations. **To be confirmed**
5. **No independent security or performance test results on record.** Penetration and load testing have not been evidenced. **To be confirmed**
6. **Overdue referral reminders are manual.** Idle referrals surface on a dashboard, but someone must send the reminder; the process relies on partner agencies keeping accounts active and responding. **Available** (risk is organizational)
7. **Database version split.** The version used in local development differs from the version used in automated checks; confirm one target version for production. **To be confirmed**
8. **Scope of this document.** This is a descriptive baseline for review. It does not replace the detailed architecture, information-model, or security documents; where those are more specific, treat them as authoritative. **Available**

---

*Prepared 2026-10-11. Companion document: [02-business-process-flow.md](02-business-process-flow.md).*
