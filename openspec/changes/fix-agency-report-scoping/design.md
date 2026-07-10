## Context

ReportsService.php has three query factory methods (`referralQuery`, `caseQuery`, `filteredClientIds`) and several standalone methods (`getGeographicProvinceCounts`, `getProvinceOptions`, `getCityOptions`, plus five no-param methods) that all lack an AGENCY role branch. When `$role='AGENCY'` is passed, the methods ignore it — only `$role='CASE_MANAGER'` is handled. This means every chart, KPI, and distribution shown to an agency user contains data from every agency in the system.

The export service (`ReportsExportService.php`) already scopes correctly by agency, serving as the reference pattern.

The `getAgencyPayload()` method at line 75 already resolves the user's agency ID (`$agcyId = User::find($userId)?->agency?->id`) but never passes it to any child method — it only passes `null` as the userId and `'AGENCY'` as the role.

The frontend (`resources/js/Pages/Reports/Index.jsx`) renders the same tab set for all roles: Overview, Performance, Agencies & Services, Caseload & Clients. For AGENCY, the latter two tabs only make sense if data were system-wide — once scoped, they show single-row or empty data.

## Goals / Non-Goals

**Goals:**
- Every AGENCY report metric scopes to `referrals.agcy_id = $agencyId` (referral-based) or `cases whose referrals include this agency` (case/client-based)
- AGENCY users see only their own data everywhere in the reports UI
- Backend: no breaking API response shape change (existing keys remain)
- AGENCY frontend tabs simplified to remove irrelevant sections (multi-agency comparisons, geographic map)
- All existing CASE_MANAGER and ADMIN reports remain unchanged

**Non-Goals:**
- No new frontend charts or data visualizations for AGENCY (addressing empty/hollow UI after scoping is a separate concern)
- No redesign of the report page layout (tabs stay, just the AGENCY tab set is pruned)
- No changes to export/PDF scoping (already correct)
- No schema migrations or new database columns

## Decisions

### Decision 1: Null-safe optional `$agencyId` parameter on all methods

**Choice:** Add `?string $agencyId = null` as the last parameter (or before `$province`/`$city` for geo methods) on every public and private method that queries referral, case, or client data.

**Rationale:** Keeps backward compatibility — all 22+ existing call sites from CASE_MANAGER and ADMIN payloads pass no agencyId and get no AGENCY branch. Adding a default `null` means zero changes outside ReportsService.php except the controller that passes it.

**Alternatives considered:**
- Creating a separate `getAgencyReportsService` class — too much duplication, 80% of the methods are shared
- Using a request-scoped context (static singleton) — hides data flow, breaks testability

### Decision 2: Referral scoping via `referrals.agcy_id` column

**Choice:** In `referralQuery()`, add:
```php
if ($role === 'AGENCY' && $agencyId) {
    $query->where('referrals.agcy_id', $agencyId);
}
```

**Rationale:** The `referrals` table already has `agcy_id` — it's the direct, indexed, unambiguous column for this filter. This works for both date_scope modes (case_created_at uses a subquery, the where clause goes on the outer query).

For the `case_created_at` mode, the subquery scoping cases by user does not interfere — the `agcy_id` filter is on referrals, not cases.

### Decision 3: Case scoping via referral EXISTS subquery

**Choice:** In `caseQuery()`, add:
```php
if ($role === 'AGENCY' && $agencyId) {
    $query->whereIn('cases.id', function ($q) use ($agencyId) {
        $q->select('case_id')->from('referrals')
          ->where('agcy_id', $agencyId)
          ->whereNull('deleted_at');
    });
}
```

**Rationale:** An agency should only see cases that have at least one referral to them. A LEFT JOIN on referrals would multiply rows for multiple-referral cases. The `whereIn` subquery is clean and preserves the existing query structure.

**Alternatives considered:**
- `whereExists` — equivalent performance, but `whereIn` is more readable here
- `join('referrals', ...)` — wrong, would duplicate cases

### Decision 4: Client scoping passes through filteredClientIds

**Choice:** `filteredClientIds()` builds its own CaseFile query — add the same referral EXISTS subquery from Decision 3. `getGenderDistribution()` and `getAgeGroupDistribution()` already use `filteredClientIds()` so they inherit scoping automatically.

### Decision 5: Standalone geographic/option methods get standalone AGENCY branches

**Choice:** `getGeographicProvinceCounts()`, `getProvinceOptions()`, `getCityOptions()` each build dedicated queries (not through the factory methods) — add the referral EXISTS subquery directly in each.

**Rationale:** These queries join through `cases.client_id → clients → client_addresses` without touching referrals. Adding the subquery on `case_id IN (select case_id from referrals where agcy_id = $agencyId)` is the minimal, correct scoping.

### Decision 6: The five no-param methods accept role + agencyId

**Choice:** `getReferralTrends()`, `getAvgReferralCompletionDays()`, `categoryDistribution()`, `getCaseStatusDistribution()`, `getClientTypeDistribution()` all currently lack `$role`/`$agencyId` params (or have them but don't use AGENCY branch). Add `$role` and `$agencyId` to the first two, add AGENCY branch to all five.

### Decision 7: Frontend tab pruning for AGENCY

**Choice:** `ReportTabBar` receives `role` prop. For AGENCY, render only "Overview" and "Performance" tabs. In `Index.jsx`, conditionally render sections:

| Component | AGENCY behavior |
|---|---|
| `GeographicMapSection` | Hidden (not relevant for single-agency) |
| `AgencyWorkloadChart` | Hidden (multi-agency comparison) |
| `LazyChartArticle` (referralAgencyDistribution) | Hidden (single data point) |
| `AgencyScorecardSection` | Render as single-row agency summary card, not multi-agency table |

**Rationale:** Once data is correctly scoped, multi-agency comparisons become single-row (scorecard) or single-segment pie charts (agency distribution), which are meaningless. The geographic map serves DMW region-level analysis, not a single agency's office.

### Decision 8: ReportsController passes agency context

**Choice:** In `ReportsController`, resolve the calling user's agency ID and pass it through to `ReportsService::getAll()` (and then on to `getAgencyPayload()`). The controller already has `auth()->user()`, so this is a one-line lookup.

## Risks / Trade-offs

| Risk | Likelihood | Mitigation |
|---|---|---|
| A CASE_MANAGER or ADMIN report breaks due to changed method signatures | Low | All existing call sites use named arguments or positional defaults — the new `$agencyId` param has a `null` default, so no call site needs updating unless it explicitly passes it |
| Performance regression on AGENCY reports (extra EXISTS subquery on every query) | Medium | The subquery is a simple `SELECT case_id FROM referrals WHERE agcy_id = ?` which uses the existing btree index on `referrals.agcy_id`. AGENCY users have small data volumes by nature (single agency). Monitor query times if lag is reported |
| AGENCY "Performance" tab shows empty charts | Low | After scoping, an agency with zero referrals will see zeros everywhere — that's correct behavior. No regressions for active agencies |
| AGENCY users confused by missing tabs | Medium | The tab bar change is visual — an AGENCY user who previously saw 4 tabs now sees 2. This is a product decision; can be reverted to show all tabs with empty-state messages instead |
| ReportsExportService diverges from the scoping pattern | Low | The export service already scopes correctly. After this fix, both on-screen and export paths will align |

## Open Questions

1. Should the "Agencies & Services" tab be replaced with a "My Services" tab showing service-level breakdown for AGENCY? (Out of scope for this fix — could be a follow-up enhancement)
