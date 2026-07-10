## Why

Every metric on the AGENCY reports page returns system-wide data instead of data scoped to the user's agency. An agency user sees all other agencies' referrals, all provinces' case data, and a multi-agency scorecard that ranks them against competitors — all of which is wrong and misleading. The export/PDF path already scopes correctly; the on-screen reports do not.

## What Changes

**Backend — ReportsService.php (all methods are affected)**

- Add `?string $agencyId = null` parameter to `referralQuery()`, `caseQuery()`, `filteredClientIds()`, `getGeographicProvinceCounts()`, and every public method that queries data
- In each method, add an `AGENCY` branch that scopes the query to the user's agency:
  - Referral queries: `$query->where('referrals.agcy_id', $agencyId)`
  - Case queries: `$query->whereIn('cases.id', function ($q) { $q->select('case_id')->from('referrals')->where('agcy_id', $agencyId); })`
  - Client queries: inherit scoping through `caseQuery` or `filteredClientIds`
- The five no-param methods (`getReferralTrends`, `getAvgReferralCompletionDays`, `categoryDistribution`, `getCaseStatusDistribution`, `getClientTypeDistribution`) will accept role + agencyId and branch accordingly
- `getProvinceOptions()` and `getCityOptions()` also need an AGENCY branch so filter dropdowns respect agency scope
- The `getAgencyPayload()` method already resolves the user's agency ID at line 83; pass it through every call

**Frontend — ReportTabBar and AGENCY tabs**

- Revisit what tabs are useful for an AGENCY user who only sees their own data. The "Agencies & Services" tab showing multi-agency comparisons and "Caseload & Clients" with geographic maps are no longer relevant when scoped to a single agency.
- Create a simplified AGENCY-only tab layout: **Overview** and **Performance** (and optionally a tab like "My Agency" that shows the agency's own single-row scorecard + service stats)

**Frontend — Components**

- `AgencyScorecardSection` — render AGENCY as single-row card, not a table (for AGENCY users)
- `AgencyWorkloadChart` — hide or replace with a service-level breakdown for AGENCY
- `GeographicMapSection` — hide for AGENCY (not relevant)
- `LazyChartArticle` keyed `referralAgencyDistribution` — hide for AGENCY (only one agency)
- Export buttons must pass agency context (already done via ExportButtons)

**Tests**

- Fix existing tests that mock `referralQuery` / `caseQuery` / `filteredClientIds` to handle the new parameters
- Add AGENCY-scoping integration tests that create referrals for agency A + agency B, then assert an agency-A user sees only A's data

## Capabilities

### New Capabilities

- `agency-reports-scoping`: Backend query scoping that limits all report metrics (KPIs, referral status, cycle time, aging, scorecard, geographic, case status, client demographics, trends) to the requesting agency's own data when the user role is AGENCY

### Modified Capabilities

*(None — no existing OpenSpec specs to modify)*

## Impact

**Files affected:**

| File | Change |
|---|---|
| `app/Services/ReportsService.php` | Add `$agencyId` param to ~15 methods; add AGENCY branches in 3+ query factory methods; fix 5 no-param methods |
| `app/Http/Controllers/ReportsController.php` | Pass agency context from authenticated user to service calls (already partially wired) |
| `resources/js/Pages/Reports/Index.jsx` | AGENCY tab configuration; conditionally hide geographic map, agency comparison charts |
| `resources/js/Components/Reports/ReportTabBar.jsx` | Simplify tabs for AGENCY (omit Agencies, Clients tabs) |
| `resources/js/Components/Reports/AgencyWorkloadChart.jsx` | Conditionally render null for AGENCY |
| `resources/js/Pages/Reports/sections/GeographicMapSection.jsx` | Conditionally render null for AGENCY |
| `resources/js/Pages/Reports/sections/AgencyScorecardSection.jsx` | Different render mode for AGENCY (single row card vs multi-agency table) |
| Test files | Update mocks + add AGENCY-scoping integration tests |

**No new dependencies or external services.** No breaking API contract changes for the `/api/reports` response shape — existing keys remain, their values just become correct for AGENCY.
