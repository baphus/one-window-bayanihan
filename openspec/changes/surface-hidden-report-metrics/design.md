## Context

The reports page (`resources/js/Pages/Reports/Index.jsx`) loads 26 deferred props via Inertia, but only ~18 are visibly rendered. The other 8 are silently loaded into the frontend's Inertia page store but never displayed. Additionally, the CaseManager dashboard computes alert-worthy conditions (overdue referrals, rejected referrals, stalled cases) that have no equivalent in reports. The geographic section renders a horizontal bar chart for a fundamentally geographic dataset.

All proposed changes are frontend-only except the map feature, which needs coordinate data.

## Goals / Non-Goals

**Goals:**
- Display every backend-computed report prop that currently reaches the frontend but isn't rendered
- Port the "Attention Required" alert pattern from the CaseManager dashboard to the reports Overview tab
- Consolidate scattered demographic stats into a single Client Snapshot card
- Replace the province bar chart with an interactive Philippine map, keeping the bar chart as an alternative view
- Keep all new metric cards and sections consistent with the existing report visual language (tight spacing, 11px fonts, muted uppercase headings, ChartSkeleton loading states)

**Non-Goals:**
- No new backend queries for the core metric-card set (everything already exists in `ReportsService`)
- No new npm packages (SVG map is inline; map feature uses existing chart layout)
- No changes to the export PDF/Excel payloads (they work independently)
- No milestone/feedback/compliance analytics (per user's direction)
- No period-over-period chart overlays or anomaly detection (scope kept tight)
- No saved report views, scheduling, or data freshness indicators

## Decisions

### 1. Metric Card Placement & Data Access

| Metric | Backend prop | Already loaded? | Placement | Access pattern |
|--------|-------------|----------------|-----------|---------------|
| Top Service Requested | `mostRequestedService` | Yes (deferred) | Overview KPI strip, after Avg Resolution | `useLazyProp('mostRequestedService')` |
| Case Overview | `overview` | Yes (deferred) | Compact banner below KPI strip | `useLazyProp('overview')` |
| Agency Workload | `agencyWorkload` | Yes (deferred) | Agencies tab, between scorecard and by-agency chart | `useLazyProp('agencyWorkload')` |
| Referral Trends | `referralTrends` | Yes (deferred) | Performance tab, below the existing Case Trends | `useLazyProp('referralTrends')` |
| Avg Completion Time | `avgReferralCompletion` | Yes (deferred) | Performance tab, as a MetricCard | `useLazyProp('avgReferralCompletion')` |
| Overdue Referrals count | `overdueReferrals` | Yes (deferred) | Performance tab, MetricCard with drill-down | `useLazyProp('overdueReferrals')` |

**Decision**: Use the existing `useLazyProp` + `ReportLazySection` pattern consistently. The deferred props are already batched into a single group request by Inertia, so adding more consumers of already-fetched data adds zero network cost.

### 2. Attention / Alert Items

**Decision**: Compute alert items client-side from existing eager props (`kpis`) and deferred props (`overdueReferrals`, `referralStatusDistribution`). Follow the CaseManager dashboard's pattern in `Dashboard.jsx:331-371`:

```
Condition                        Source               Display
─────────────────────────────────────────────────────────────
Referral PENDING > 5 days        allReferrals[]       Warning card → referral detail
Referral REJECTED                allReferrals[]       Error card → referral detail
Case OPEN > 7 days, no refs     allCases[]           Info card → case detail
Overdue count > 0               overdueReferrals     Alert badge → overdue page
```

The reports page doesn't have `allCases`/`allReferrals` arrays (they're dashboard-only). Instead, derive from:
- `referralStatusDistribution.data[PENDING]` — total pending count (generic, not per-case)
- `overdueReferrals` — list of overdue referrals with case/agency info (available as deferred)
- `kpiChanges` — trend signals ("up 20% this period")

**Design**: A single row of 2-3 alert-style MetricCards with amber/rose accents, conditionally shown when counts > 0. Each has a drill-down link.

### 3. Client Snapshot Card

**Decision**: Create a new `ClientSnapshotCard` component that consumes three already-loaded deferred props: `genderDistribution`, `clientTypeDistribution`, `vulnerabilityDistribution`. Display as a single compact row of badges/stat blocks, replacing the current free-floating layout where gender is a pie, client-type is a bar, and vulnerability is a bar in disconnected positions.

Layout (desktop): A single card with three inline stat blocks:
```
┌──────────────────────────────────────────────────────────────┐
│  CLIENT DEMOGRAPHICS                                         │
│                                                              │
│  ┌────────────┐  ┌────────────┐  ┌──────────────────────┐   │
│  │ 1,247      │  │ 67% OFW    │  │ PWD 23               │   │
│  │ Total      │  │ 33% NOK    │  │ Senior 45            │   │
│  │ Clients    │  │ 480 M      │  │ Solo Parent 112      │   │
│  └────────────┘  │ 767 F      │  │ Indigenous Person 8  │   │
│                   └────────────┘  └──────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

### 4. Geographic Map

**Decision**: Use an inline SVG Philippine map with province-level coloring, rendered as a React component. No Leaflet/MapLibre dependency — this keeps bundle size negligible.

**Data format needed from backend**:
```json
{
  "provinces": [
    { "id": "CEB", "name": "Cebu", "cases": 342, "color": "#1e3a8a" },
    { "id": "BOH", "name": "Bohol", "cases": 89, "color": "#3b82f6" },
    ...
  ]
}
```

**Approach**: Create a new deferred prop `geographicMapData` that is a one-per-province dataset. The SVG map is a static Region VII map (Central Visayas: Cebu, Bohol, Negros Oriental, Siquijor) with simplified province outlines. Each province is a `<path>` filled based on case density. No pan/zoom needed at this scope.

**Fallback**: The existing horizontal bar chart remains accessible via a toggle (tab or button: "Map" / "Bar").

**Implementation order**: Backend returns province-level case counts with resolved names (already done in `getGeographicDistribution` — just need to repackage as a list). Frontend renders SVG paths.

### 5. Design System Consistency

All new components follow existing patterns:
- **Card shell**: `cardShell` class from `pageHeadingStyles.js`
- **Section titles**: `pageHeadingStyles.sectionTitle`
- **Loading states**: `ChartSkeleton` component
- **Empty states**: paragraph with `text-[13px] text-slate-400`
- **MetricCards**: `MetricCard` component with accent border, optional trailing element
- **Colors**: `COLORS` palette from `pageHeadingStyles.js`

## Risks / Trade-offs

| Risk | Mitigation |
|------|-----------|
| **`mostRequestedService` is stale or unreliable** — the service name is free-text on referrals; could be empty or inconsistent | Wire it up as a MetricCard that gracefully empty-states: if name is "N/A" or null, show "No data" |
| **Map SVG file needs maintenance** — province boundaries change | Use a simplified topology (Region VII only). The SVG is embedded in the component, not fetched externally. Swap cost is one file edit. |
| **Overdue referrals count already shown in admin dashboard** — risk of duplicate | Reports is the canonical BI surface. The admin dashboard widget is a quick-glance. Different audiences. |
| **Alert items complexity** — computing alerts without the full cases/referrals arrays may yield less actionable items | Start with `overdueReferrals` and `referralStatusDistribution` only. Can expand later. |
| **Map adds backend work** — new deferred prop and service method | The query is a trivial repackage of `getGeographicDistribution` data into a list format — ~20 lines of PHP. |

## Open Questions

- Should the geographic map be Region-VII-only (the project's scope) or the entire Philippines? If Region VII, we need to confirm province names match SVG path IDs (e.g., "Cebu" matches the PSGC-resolved name).
- For the alert items section: should alerts auto-dismiss, or persist until the underlying referral is actioned? Recommend: they persist because the data drives them (referral still pending > 5 days).
- Should the map show absolute case counts or density (cases per capita)? Absolute counts — per-capita requires population data we don't have.
- What's the threshold for "overdue" in the alert section? The dashboard uses 5 days for PENDING referrals. We'll match that.
