# Role Dashboards Redesign — Design Document

**Version:** v1.0.0
**Date:** 2026-07-11
**Status:** Approved for implementation (autonomous session; assumptions listed below)

## Changelog

| Version | Date | Change |
|---------|------|--------|
| v1.0.0 | 2026-07-11 | Initial design document. |

## Problem

The current (uncommitted) dashboard rewrite is a 1,341-line monolith that drifts from the app's design system: over-decorated nested cards, duplicated chart renditions, verbose filler copy, hover-translate effects everywhere, quick actions buried at the bottom, and heavy client-side fallback computation fed by shipping **every case and referral** to the browser. The user asked for a redesign that is professional, compact, intuitive, on-system, and restores prominent quick actions.

## Goals

1. Follow the established design philosophy: white `rounded-xl border-slate-200 shadow-sm` cards, tiny uppercase slate-400 labels, `text-2xl font-black` values, Public Sans, `primary #005288` accent, StatusBadge vocabulary, `divide-y` link lists.
2. Quick actions visible at the top of every role dashboard.
3. Answer each role's start-of-day question in the first screenful: *what needs action now?*
4. Compact: fewer, denser sections; no duplicated data renditions; short functional copy.
5. Data minimization: stop shipping full datasets; use server-computed aggregates only.

## Non-goals

- No new routes, permissions, or dependencies. No dark-mode work beyond what exists.
- No changes to the onboarding tour configs (anchors are preserved instead).

## Design decisions (assumptions made autonomously)

- **A1**: Three roles exist (ADMIN, AGENCY, CASE_MANAGER). No client-role dashboard.
- **A2**: The uncommitted `DashboardService` builder methods (work queues, priority lists, aging bands, scorecards, feedback pulse) are kept — the data layer was sound; the presentation was not.
- **A3**: "Quick actions seen earlier" = the Quick Actions card from the previous committed dashboard; restored as a header-level action row (more prominent than before).
- **A4**: Semantic-version filenames are applied to documents (this file). Source code files keep stable names because module imports and the Inertia page resolver depend on them; git history provides code versioning. Renaming code files per-edit would break the build.

## Architecture

```
Pages/Dashboard.jsx            ← routed by Inertia ('Dashboard'); thin dispatcher +
                                 <Deferred> skeleton fallback (~90 lines)
Pages/Dashboard/Admin.jsx      ← role page
Pages/Dashboard/Agency.jsx     ← role page (replaces orphaned file)
Pages/Dashboard/CaseManager.jsx← role page (replaces orphaned file)
Pages/Dashboard/Index.jsx      ← DELETED (orphan duplicate dispatcher)
Components/Dashboard/primitives.jsx ← SectionCard, StatCard row, TriageStrip,
                                 QuickActions, EntityRow, BarList, DonutCard, ActivityFeed
```

### Shared visual language

- **Card shell**: `rounded-xl border border-slate-200 bg-white shadow-sm`.
- **Section header**: `px-5 py-3` bottom-bordered; title `text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary`; optional right-side "View all" link `text-xs font-bold text-primary`.
- **KPI**: existing `Components/ui/KpiCard` (the unified system) — 4 per row.
- **Signature element — Triage strip**: ONE horizontal card split by `divide-x divide-slate-100` into the role's work queues. Each cell is a link: count (`text-xl font-black`) + tone dot + label + one-line note. Rose-tinted text when an overdue/returned queue has items. Replaces the previous wall of jumbo queue cards; encodes "dispatch board" density.
- **Entity rows**: `divide-y divide-slate-100` rows with case-number pill (`bg-primary/10 text-primary`), client name, StatusBadge, age chip, chevron — the committed Admin-dashboard row pattern.
- **Charts**: demoted to the aside column. Doughnut for referral status (scoped data), compact bar for 12-month case trend, CSS bar lists for aging/demand/category/agency-load. No chart+bar duplication of the same series.
- **Copy**: plain verbs, sentence case, no filler narration. Empty states: one line + one action link.
- **Motion**: `hover:bg-slate-50` / border-color transitions only. No translate-on-hover. `motion-reduce` safe by construction.

### Page layouts

Common: `mx-auto max-w-7xl` → DashboardBanner → header (eyebrow role label, `font-headline` H1 greeting, date; right: QuickActions) → KPI row → Triage strip → 12-col grid (main 8 / aside 4) → activity feed.

**Case Manager** (`data-tour`: header→`dashboard-header`, KPI row→`dashboard-stats`, status donut→`dashboard-chart`, priority cases→`dashboard-recent`)
- Quick actions: **New case** (primary), Drafts (badge = draft count), Referrals, Reports.
- KPIs: Open cases, Pending referrals, Returned referrals, Avg. days to close.
- Triage: agingOpenCases, pendingReferrals, rejectedReferrals, draftCases, casesWithoutReferrals.
- Main: Priority cases (≤6 rows), Priority referrals (≤6 rows).
- Aside: Referral status donut, Case trend bar (12 mo), Agency response scorecard (top 5), Category mix.
- Footer: Recent activity (6, two-column on xl).

**Agency Focal** (`data-tour`: header→`dashboard-header`, KPI row→`dashboard-agency-metrics` + `dashboard-stats`, priority referrals→`dashboard-agency-referrals`)
- Quick actions: **Open referrals** (primary), Overdue, Feedback, Reports.
- KPIs: Pending, Processing, For compliance, Completed.
- Triage: newReferrals, pending, forCompliance, processing, overdue, returned.
- Main: Priority referrals (≤8 rows).
- Aside: Referral aging bands, Service demand, Feedback pulse (response rate / avg rating / SERVQUAL inline stats).
- Footer: Recent activity.

**Admin** (`data-tour`: header→`dashboard-header`, KPI row→`dashboard-stats`, admin tools→`dashboard-admin-system`, recent cases→`admin-recent-cases`, activity→`admin-recent-activity`)
- Quick actions: **Users**, Agencies, Services, Reports.
- KPIs: Total cases, Total referrals, Active agencies, Overdue referrals.
- Triage: openCases, pendingReferrals, processing, forCompliance, overdueReferrals.
- Main: Recent case movement (6 rows), Recent administrative activity.
- Aside: Admin tools list (Users/Agencies/Services/Audit logs/Sessions), Agency load bars, Users by role, Case mix by category.

### Backend changes

1. `DashboardService::getCaseManagerData` — **remove** `allCases`, `allReferrals`, and the seven unused demographic counters from the payload (data minimization; the new UI consumes only server aggregates). Keep everything else.
2. `DashboardController` — **stop overwriting** `referralStatusDistribution` with the global unscoped `ReportsService` version (scoping defect: agency users received system-wide data). The service's role-scoped `{status,label,count,percent,tone}[]` becomes the single shape. `getAdminData` gains the same key built from all referrals. `caseTrends` merge stays (ADMIN/CASE_MANAGER only).
3. Keep the uncommitted service builder methods; feature test `DashboardServiceTest` carried over and extended for payload keys per role.

## Error/empty/loading handling

- Deferred prop → `<Deferred fallback={<DashboardSkeleton/>}>` with pulse skeleton matching final layout (prevents CLS and removes the need for client-side fallback computation).
- Every list/chart has a one-line empty state with a link to the relevant workflow.
- All numeric rendering goes through a `safeCount`-style formatter.

## Testing

- PHPUnit: `DashboardServiceTest` asserts payload keys per role, absence of `allCases`/`allReferrals`, and scoped status distribution.
- `npm run build` clean; ESLint on touched files if configured.
- Playwright manual pass: screenshot each role dashboard; verify tour anchors exist via DOM query.

## Standards-readiness notes (flagged per org policy; this is not an audit artefact)

- **Least privilege / data minimization (ISO 27001 A.8, DPTM "Appropriate protection")**: removal of full-table payloads and the cross-agency status-distribution leak are corrective; previously any agency focal's browser received system-wide referral aggregates and case managers received all referral records client-side.
- **Accessibility (best practice, WCAG 2.1 AA)**: focus-visible rings on all interactive elements, `aria-hidden` on decorative icons, text contrast ≥ 4.5:1 (slate-400 labels on white used only at ≥10px bold uppercase — consistent with existing system).
- **Auditability (SOC 2 CC7)**: recent-activity feed retained on all roles, linking to the full audit log.
