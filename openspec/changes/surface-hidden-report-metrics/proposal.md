## Why

The reports page already has an extensive chart/report infrastructure and the backend already computes several valuable metrics (`mostRequestedService`, `overview`, `agencyWorkload`, `referralTrends`, `avgReferralCompletion`, `overdueReferrals`), but the frontend never surfaces them. Additionally, attention/alert patterns proven useful in the CaseManager dashboard have no equivalent in reports, and the geographic distribution section is limited to a horizontal bar chart when the data is geographic by nature. These are quick wins — no new backend queries, new model methods, or data pipelines needed for the core set.

## What Changes

1. **Surface `mostRequestedService`** — Add a compact "Top Service Requested" metric card to the Overview KPI hero strip (CASE_MANAGER role).

2. **Surface `overview`** — Add a compact summary banner below the KPI hero showing total cases, open/closed splits, and active agencies.

3. **Surface `agencyWorkload`** — Add a horizontal bar chart in the Agencies & Services tab (ADMIN role) showing all agencies ranked by referral count.

4. **Surface `referralTrends`** — Add a line/bar trend chart in the Performance tab (AGENCY role).

5. **Surface `avgReferralCompletion`** — Add a "Avg Completion Time" metric card in the Performance tab (AGENCY role).

6. **Surface `overdueReferrals.count`** — Add a metric card in the Performance tab with a count badge and drill-down link to the admin overdue referrals page (ADMIN role).

7. **Attention / Alert items section** — Port the "Attention Required" pattern from the CaseManager dashboard into the reports Overview tab, computing overdue follow-ups, rejected referrals, and stalled cases from already-loaded data.

8. **Consolidated Client Snapshot card** — Combine gender, client-type, and vulnerability data into a single compact summary card at the top of the Caseload & Clients tab (replacing the current fragmented layout for these stats).

9. **Geographic map visualization** — Replace the provincial bar chart with a province-level choropleth or bubble map (Philippines) using a lightweight SVG-based approach, supplemented by the existing bar chart as a tabular alternative. The city-distribution chart remains as-is.

## Capabilities

### New Capabilities
- `report-metric-cards`: Compact metric cards that surface already-computed backend data across all report tabs, visible to the appropriate roles.
- `report-alert-items`: "Attention Required" section in the Overview tab that proactively flags overdue follow-ups, rejected referrals, and stalled cases.
- `client-profile-summary`: Consolidated client demographic snapshot card combining gender, client-type, and vulnerability indicators.
- `geographic-map`: Province-level Philippine map visualization replacing the horizontal bar chart for geographic distribution, with drill-down to province-filtered views.

### Modified Capabilities
*(No existing specs to modify — this is the first change in this project.)*

## Impact

**Backend**: Minimal. Only the geographic map feature may require a new endpoint (`/reports/geographic-map-data`) that returns province geometries or coordinates. Everything else uses existing deferred props.

**Frontend files touched:**
- `resources/js/Pages/Reports/Index.jsx` — tab layout, KPI strip, new sections
- `resources/js/Pages/Reports/sections/*.jsx` — new section components
- `resources/js/Components/Reports/*.jsx` — new shared components (Map, AttentionCard, SnapshotCard)
- `app/Http/Controllers/ReportsController.php` — possibly one new deferred prop for map data
- `app/Services/ReportsService.php` — possibly one new method for map data

**Dependencies:**
- A Philippine map GeoJSON or SVG dataset (lightweight, bundled, ~20-50KB)
- No new npm packages — SVG map can be rendered with existing React/SVG capabilities
