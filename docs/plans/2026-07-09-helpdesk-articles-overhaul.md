# Helpdesk Articles Overhaul — Implementation Plan

**Goal:** Replace all 22 placeholder/commercial helpdesk articles with accurate, codebase-referencing guides, add 7 new articles for missing workflows, and attach Playwright screenshots to every article.

**Architecture:** Static data-only change — no new DB tables, no routes, no backend code. Articles live as TypeScript string exports in `resources/js/data/helpdesk/content/*.ts`, registered in `resources/js/data/helpdesk/articles.ts`. Category/tag metadata and the rendering pipeline (`HelpdeskLayout`, `ArticleCard`, `Show.jsx`) are untouched. Screenshots are stored in `storage/app/public/helpdesk/` and served via relative URLs.

**Tech Stack:** Laravel 13, Inertia (React 18), PostgreSQL, Tailwind CSS, TypeScript/JSX

**Framework:** Diátaxis documentation model — articles are a mix of Tutorials (step-by-step learning), How-to Guides (task-oriented), and Reference (accurate technical description).

---

## Current State Audit

### Problems with existing articles

| Issue | Example | Impact |
|-------|---------|--------|
| Generic placeholders | "Navigate to the system login page" — which URL? | User can't find the actual page |
| Wrong status names | Says case lifecycle is `OPEN → PROCESSING → FOR COMPLIANCE → CLOSED` — missing `DRAFT`, `ARCHIVED` | Misleads users |
| Missing route references | Never uses `route('cases.index')`, `route('referrals.show')`, etc. | User can't navigate |
| No screenshots | Every article is pure text | Hard to follow |
| No actual field references | Says "Fill out the intake form" without naming actual form fields | Incomplete guidance |
| Wrong controller details | Generic "Click New Case button" — doesn't match actual UX | Mismatched expectations |
| Missing key workflows | No articles on draft management, compliance requirements, referral comments, reports, data export, case categories | Users can't learn critical features |

### Existing articles inventory

22 articles exist. 15 need substantial rewrite. 7 are structurally okay but need accuracy fixes. 7 new articles needed.

**Articles needing FULL REWRITE:**
1. getting-started-case-managers — generic, wrong status names, missing draft workflow
2. getting-started-agency-focal — generic, wrong route references
3. getting-started-system-admin — generic, admin features not accurately described
4. understanding-case-statuses-tracker-numbers — wrong statuses (OPEN/PROCESSING/FOR COMPLIANCE/CLOSED — missing DRAFT, ARCHIVED)
5. using-dashboard-daily-monitoring — KPI list doesn't match actual DashboardService
6. case-documentation-best-practices — generic advice, no actual feature references
7. case-closure-checklist-procedures — generic, wrong status transition info
8. managing-overdue-referrals — SLA logic described incorrectly
9. managing-your-agency-services-profile — route references wrong
10. best-practices-timely-referral-processing — generic advice, no real feature refs
11. communication-guidelines-inter-agency — replaces with Referral Comments guide
12. managing-helpdesk-knowledge-base — no actual CMS reference
13. monitoring-queue-jobs-system-health — artisan commands but no dashboard refs
14. configuring-system-settings-ai-chatbot — somewhat accurate but no screenshots
15. troubleshooting-common-issues — accurate content, needs screenshots

**Articles needing MODERATE REWRITE (accuracy fixes + screenshots):**
16. using-public-tracking-portal — accurate concept, needs OTP flow details
17. providing-feedback-on-your-case — accurate concept, needs SERVQUAL details
18. ofw-assistance-services-available — reference article, needs accuracy pass
19. understanding-using-audit-log — accurate, needs actual query docs
20. adding-milestones-referrals-complete-guide — accurate, needs screenshots
21. privacy-data-protection-owb — reference article, needs accuracy pass
22. glossary-of-terms — reference article, update with actual terms

**New articles needed:**
23. Creating and Publishing Cases: Complete Walkthrough
24. Managing Draft Cases: Save, Edit, Publish, Delete
25. Managing Compliance Requirements on Referrals
26. Using Referral Comments and Communication
27. Using Reports and Analytics
28. Exporting Case Data to Excel
29. Admin Reference: Managing Case Categories, Issues, and Statuses

---

## Article Architecture

### Directory Structure
```
resources/js/data/helpdesk/
├── articles.ts                    # article index (update metadata)
├── categories.ts                  # unchanged
├── tags.ts                        # unchanged
├── types.ts                       # unchanged
├── search.ts                      # unchanged
├── index.ts                       # unchanged
├── content/                       # article body files
│   ├── [existing rewritten files]
│   └── [new files]
└── screenshots/                   # referenced by articles
    ├── login-page.png
    ├── dashboard-cm.png
    ├── ...
```

### Where screenshots live
Static images are served from `storage/app/public/helpdesk/`. In markdown content they are referenced as `/storage/helpdesk/filename.png`.

In dev: `php artisan storage:link` creates `public/storage` → `storage/app/public`. So the articles will reference `![alt](/storage/helpdesk/login-page.png)`.

---

## Detailed Article Specifications

### Article 1: Getting Started for Case Managers
**Category:** cm-workflow | **Tags:** onboarding, training, cases | **Type:** Tutorial

**Content requirements:**
- Accurate login URL (route name: `login`)
- OTP flow to email, 5-min expiry, resend button
- Dashboard: KPI cards matching actual DashboardService::getCaseManagerData() keys: Open Cases, Pending Referrals, Completed This Month, Overdue Referrals
- Sidebar navigation: Case/Index (`route('cases.index')`), Referral/Index, Drafts, Reports
- Draft-first workflow: ALL cases start as DRAFT, then are published
- "Cases" link → `route('cases.index')` with filter for status, search, date range
- Case Create form sections matching actual fields: client_type (OFW / NEXT_OF_KIN), vulnerability_indicator (PWD / Senior Citizen / Solo Parent), client fields (first_name, last_name, middle_initial, suffix, date_of_birth, sex, email, contact_number), address (region, province, city_municipality, barangay), employment (employer_name, position, country, start_date, end_date, last_country, last_position, date_of_arrival), next_of_kin (multi-entry with first_name, last_name, relationship, phone_number, email, is_primary), summary, category_id, case_issue_id, consent
- Tracker number format: OWBAP-XXXXXXX (7 random uppercase alphanumeric)
- Referral creation: select agency (`agencies` from `ReferralService::getAgenciesWithServices()`), select services, write notes, attach documents (via `StorageService`, stored at `referrals/{id}/`)
- Referral statuses: PENDING, PROCESSING, FOR_COMPLIANCE, COMPLETED, REJECTED
- Milestone addition per referral (route: `referrals.milestones.store`)
- SLA overdue days configurable in SystemSetting `referral_overdue_days` (default 7)

**Screenshots needed:** login page, CM dashboard, case list, create case form (top), create case form (OFW info), referrals list, create referral, case detail view

---

### Article 2: Getting Started for Agency Focal Persons
**Category:** referral-processing | **Tags:** onboarding, training, referrals | **Type:** Tutorial

**Content requirements:**
- Login via same OTP flow
- Agency dashboard: `Dashboard/Agency.jsx` — shows referral stats for their agency only (scoped by `agcy_id`)
- Referral list scoped to their agency: `ReferralController::getReferrals()` filters by `user->agcy_id` for AGENCY role
- Accept/reject referrals: status transitions PENDING → PROCESSING (accept) or REJECTED (reject with comment)
- Add milestones per referral: `ReferralService::addMilestone()` — title + description, append-only
- Update referral status: `ReferralService::updateStatus()` — valid transitions (see article 28)
- Manage their agency services: `Admin/Service/Index.jsx` — add/edit/deactivate services with processing_days SLA
- Comments system: `ReferralController::addComment()` with visibility (INTERNAL/PUBLIC), threaded replies
- Compliance requirements: fulfilling `ReferralComplianceRequirement` items by uploading documents

**Screenshots needed:** agency dashboard, referral list (agency view), referral detail, add milestone form, services management, referral comments

---

### Article 3: Getting Started for System Administrators
**Category:** system-config | **Tags:** onboarding, training, troubleshooting | **Type:** Tutorial

**Content requirements:**
- IP whitelist enforcement for admin area (`ip.whitelist` middleware)
- User management: `Admin/User/Index.jsx` — create/edit/deactivate users, roles (ADMIN, CASE_MANAGER, AGENCY), assign agency for AGENCY role
- Agency management: `Admin/Agency/Index.jsx` — create/edit agencies, set active/inactive, map integration
- Service management: `Admin/Service/Index.jsx` — add services per agency, set processing_days SLA, service requirements (compliance checklist items)
- System settings: `Admin/System/Settings` — OTP debug mode, session timeout, file upload limits, AI chatbot config (provider selection: OpenAI/Anthropic/Custom, API key, instruction prompt, temperature, token limit)
- Helpdesk CMS: manage articles/categories/tags
- Audit Log: `AuditLogController::index()` — filter by action/module/user/date/search, cursor pagination
- Maintenance: queue monitoring (`Admin/Maintenance/Index.jsx`), failed jobs, system health

**Screenshots needed:** user list, create user form, agency list, services management, system settings, audit log, maintenance dashboard

---

### Article 4: Using the Public Tracking Portal
**Category:** case-submission | **Tags:** tracking, cases | **Type:** How-to Guide

**Content requirements:**
- `route('track.index')` — public page with TrackerSection component
- Enter tracker number (OWBAP-XXXXXXX format)
- OTP verification flow: `TrackingService::generateOtp()` sends 6-digit code to email
- `TrackingService::verifyOtp()` validates code
- Case overview: status, client info, work history, next of kin
- Milestone timeline: `TrackingService::buildMilestoneTimeline()` — filtered to client-facing events only
- Agency cards with 4-step progress model: Created → Referred → Received → Processing/Completed
- Compliance requirement status display
- Case notifications (unread count, click to read)

---

### Article 5: Understanding Case Statuses and Tracker Numbers (REFERENCE REWRITE)
**Category:** case-submission | **Tags:** tracking, cases | **Type:** Reference

**Corrected statuses mapping to actual CaseFile model:**
| Status | Meaning | Notes |
|--------|---------|-------|
| DRAFT | Being prepared by Case Manager, not yet active | Only visible to creator; can be edited/deleted |
| OPEN | Active case, initial assessment | Published from draft; referrals can be created |
| CLOSED | Resolved, all referrals in terminal state | Triggers feedback invitation via ReferralCompleted event |
| ARCHIVED | No longer active, preserved for records | Can be unarchived |

Internal case_number format: `CASE-YYYYMMDD-XXXX` (year-month-day + random 4 digits)
Public tracker_number format: `OWBAP-XXXXXXX` (7 random uppercase alphanumeric)

Referral statuses: PENDING → PROCESSING / REJECTED → FOR_COMPLIANCE → PROCESSING → COMPLETED

---

### Article 6: Providing Feedback on Your Case
**Category:** ofw-rights | **Tags:** cases, documents | **Type:** How-to Guide

**Content requirements:**
- Triggered by `ReferralCompleted` event → `route('feedback.create', [...])`
- Overall rating 1-5
- SERVQUAL 5 dimensions: Tangibles, Reliability, Responsiveness, Assurance, Empathy
- Per-dimension ratings + optional comments
- Linked to specific referral and agency
- Stored in `Feedback` model with `FeedbackServqualResponse` for each dimension

---

### Article 7-22: (remaining rewrite specs in implementation tasks)
Each follows the same pattern — extract actual route names, controller methods, service layers, model fields, and validation rules from the codebase.

---

## Implementation Tasks

### Phase 1: Setup — Screenshot directory and storage link

- [ ] **Step 1: Create screenshots directory and ensure storage link**

```bash
mkdir -p storage/app/public/helpdesk
php artisan storage:link  # if not already linked
```

---

### Phase 2: Article Content Rewrite — 4 parallel streams

Because each article is an independent file, we can dispatch parallel @fixer agents per stream.

**Stream A — Getting Started & Tutorials (articles 1-3, 23)**
Files:
- Modify: `resources/js/data/helpdesk/content/getting-started-case-managers.ts`
- Modify: `resources/js/data/helpdesk/content/getting-started-agency-focal.ts`
- Modify: `resources/js/data/helpdesk/content/getting-started-system-admin.ts`
- Create: `resources/js/data/helpdesk/content/creating-publishing-cases.ts`

**Stream B — Case & Referral How-to Guides (articles 4, 6, 8-11, 14-15, 24-26)**
Files:
- Modify: `resources/js/data/helpdesk/content/using-public-tracking-portal.ts`
- Modify: `resources/js/data/helpdesk/content/providing-feedback-on-your-case.ts`
- Rewrite: `resources/js/data/helpdesk/content/case-documentation-best-practices.ts` → rename to `resources/js/data/helpdesk/content/creating-publishing-cases.ts` (merged with new 23)
- Modify: `resources/js/data/helpdesk/content/using-dashboard-daily-monitoring.ts`
- Rewrite: `resources/js/data/helpdesk/content/case-closure-checklist-procedures.ts`
- Modify: `resources/js/data/helpdesk/content/managing-overdue-referrals.ts`
- Rewrite: `resources/js/data/helpdesk/content/managing-your-agency-services-profile.ts`
- Modify: `resources/js/data/helpdesk/content/adding-milestones-referrals-complete-guide.ts`
- Rewrite: `resources/js/data/helpdesk/content/best-practices-timely-referral-processing.ts`
- Create: `resources/js/data/helpdesk/content/managing-draft-cases.ts` (new 24)
- Create: `resources/js/data/helpdesk/content/managing-compliance-requirements.ts` (new 25)
- Create: `resources/js/data/helpdesk/content/using-referral-comments.ts` (new 26)
- Delete: `resources/js/data/helpdesk/content/case-documentation-best-practices.ts` (replaced by 23)
- Delete: `resources/js/data/helpdesk/content/communication-guidelines-inter-agency.ts` (replaced by 26)

**Stream C — Admin & Reference Guides (articles 16-22, 27-29)**
Files:
- Modify: `resources/js/data/helpdesk/content/managing-helpdesk-knowledge-base.ts`
- Modify: `resources/js/data/helpdesk/content/understanding-using-audit-log.ts`
- Modify: `resources/js/data/helpdesk/content/configuring-system-settings-ai-chatbot.ts`
- Modify: `resources/js/data/helpdesk/content/monitoring-queue-jobs-system-health.ts`
- Modify: `resources/js/data/helpdesk/content/privacy-data-protection-owb.ts`
- Modify: `resources/js/data/helpdesk/content/glossary-of-terms.ts`
- Modify: `resources/js/data/helpdesk/content/troubleshooting-common-issues.ts`
- Modify: `resources/js/data/helpdesk/content/ofw-assistance-services-available.ts`
- Modify: `resources/js/data/helpdesk/content/understanding-case-statuses-tracker-numbers.ts`
- Create: `resources/js/data/helpdesk/content/using-reports-analytics.ts` (new 27)
- Create: `resources/js/data/helpdesk/content/exporting-case-data-excel.ts` (new 28)
- Create: `resources/js/data/helpdesk/content/admin-case-categories-issues-statuses.ts` (new 29)

**Stream D — Article Index Update + Tag/Category alignment**
- Modify: `resources/js/data/helpdesk/articles.ts` — update all metadata (new article IDs, updated slugs, changed categories/tags, delete replaced articles, add new ones)
- Ensure categories.ts and tags.ts still match; no changes unless needed

---

### Phase 3: Screenshot Capture — Playwright automation

**Screenshot specs needed (28 screenshots):**

| Screenshot | Page/Route | Notes |
|------------|-----------|-------|
| login-page.png | /login | Login form with email/password |
| login-otp.png | /login-otp | OTP verification screen |
| dashboard-cm.png | /dashboard | Case Manager dashboard |
| dashboard-agency.png | /dashboard | Agency dashboard |
| dashboard-admin.png | /dashboard | Admin dashboard |
| cases-index.png | /cases | Case list with filters |
| cases-create-top.png | /cases/create | Top of create form |
| cases-create-client.png | /cases/create | Client info section |
| cases-create-address.png | /cases/create | Address cascade dropdowns |
| cases-create-nok.png | /cases/create | Next of kin section |
| cases-show.png | /cases/{id} | Case detail with timeline |
| cases-drafts.png | /cases/drafts | Draft list |
| referrals-index.png | /referrals | Referral list |
| referrals-create.png | /referrals/create | Create referral form |
| referrals-show.png | /referrals/{id} | Referral detail with milestones |
| referrals-add-milestone.png | /referrals/{id} | Add milestone section |
| referrals-comments.png | /referrals/{id} | Comments thread |
| referrals-compliance.png | /referrals/{id} | Compliance requirements |
| admin-users.png | /admin/users | User management |
| admin-agencies.png | /admin/agencies | Agency management |
| admin-services.png | /admin/services | Service management |
| admin-settings.png | /admin/settings | System settings |
| admin-audit-log.png | /admin/audit-log | Audit log viewer |
| admin-maintenance.png | /admin/maintenance | Queue/health dashboard |
| reports.png | /reports | Reports dashboard |
| tracking-portal.png | /track | Public tracking portal |
| track-result.png | /track/{number} | Track case results |
| helpdesk-index.png | /helpdesk | Help center landing |

For each screenshot, the Playwright script:
1. Starts `php artisan serve --port=8000`
2. Navigates to the route
3. If authenticated, logs in with test credentials (create via seeder if needed)
4. Takes full-page screenshot
5. Saves to `storage/app/public/helpdesk/`

---

### Phase 4: Verification

- [ ] Run `npm run build` to verify no TypeScript/import errors
- [ ] Open each article in the helpdesk to verify rendering
- [ ] Verify all `![screenshot](/storage/helpdesk/...)` paths resolve
- [ ] Run `php artisan test --testsuite=Feature` to ensure no regressions

---

## File Change Summary

| Action | Files |
|--------|-------|
| Create (new articles) | 10 files: `managing-draft-cases.ts`, `managing-compliance-requirements.ts`, `using-referral-comments.ts`, `using-reports-analytics.ts`, `exporting-case-data-excel.ts`, `admin-case-categories-issues-statuses.ts`, `creating-publishing-cases.ts` |
| Modify (rewrite) | 19 files: all 22 existing content files except 3 that are being replaced |
| Delete (replaced) | `case-documentation-best-practices.ts`, `communication-guidelines-inter-agency.ts` |
| Modify (index) | `articles.ts` — update article registry |
| Create (screenshots) | ~28 PNG files in `storage/app/public/helpdesk/` |
| Unchanged | `categories.ts`, `tags.ts`, `types.ts`, `search.ts`, `index.ts` |

---

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Article content becomes stale again | Medium | Articles reference codebase URLs not hardcoded paths; update triggers in future PRs ensure accuracy |
| Screenshots show dev/test data | Low | Use seeder-generated data for consistency |
| Screenshot URLs break if storage link is missing | Medium | Storage link setup is prerequisite step; add check in verification |
| Content length exceeds type strings | Low | Each article is ~2-5KB, well within TS string limits |
| Category/tag mismatch after restructure | Low | Cross-reference during articles.ts update |

---

## Execution Order

1. ❏ Launch Stream A, B, C in parallel (background @fixer agents) — each rewrites assigned article files with accurate content
2. ❏ After all streams complete, update `articles.ts` index
3. ❏ Run Playwright screenshot capture script
4. ❏ Insert screenshot references into all article markdown
5. ❏ Verify build, test, and manual check

---

*Plan created 2026-07-09. Total articles: 29 (22 rewritten + 7 new). Screenshots: ~28.*
