## 1. Backend: Add `$agencyId` parameter to service entry point and controller

- [x] 1.1 Add `?string $agencyId = null` parameter to `ReportsService::getAll()`
- [x] 1.2 In `ReportsController::index()`, resolve `$agencyId` from `$user->agency?->id` when role is AGENCY and pass it to `getAll()`, `getProvinceOptions()`, and `getCityOptions()`
- [x] 1.3 Update `getAgencyPayload()` to accept `$agencyId` as a parameter instead of resolving it internally; pass it through to every child method call

## 2. Backend: Add AGENCY branches to all query methods

- [x] 2.1 `referralQuery()` — add `$agencyId` param + AGENCY branch (`$query->where('referrals.agcy_id', $agencyId)`)
- [x] 2.2 `caseQuery()` — add `$agencyId` param + AGENCY branch (`$query->whereIn('cases.id', referrals subquery)`)
- [x] 2.3 `filteredClientIds()` — add `$agencyId` param + AGENCY branch
- [x] 2.4 `getGeographicProvinceCounts()` — add `$agencyId` param + AGENCY branch
- [x] 2.5 `getProvinceOptions()` — add AGENCY branch
- [x] 2.6 `getCityOptions()` — add AGENCY branch

## 3. Backend: Add AGENCY scoping to no-param methods

- [x] 3.1 `getReferralTrends(int $months = 12)` → add `?string $role = null, ?string $agencyId = null` params + AGENCY branch
- [x] 3.2 `getAvgReferralCompletionDays()` → add `?string $role = null, ?string $agencyId = null` params + AGENCY branch
- [x] 3.3 `getOverdueReferrals()` — add `$agencyId` param + AGENCY branch
- [x] 3.4 `getClientTypeDistribution()` — add AGENCY branch (already has `$role` param)
- [x] 3.5 `categoryDistribution()` — add AGENCY branch (already has `$role` param)
- [x] 3.6 `getCaseStatusDistribution()` — add AGENCY branch (already has `$role` param)
- [x] 3.7 `getLastEmploymentDistribution()` — add `$agencyId` param + AGENCY branch
- [x] 3.8 `getEmploymentPositionBreakdown()` — add `$agencyId` param + AGENCY branch
- [x] 3.9 `getVulnerabilityDistribution()` — add `$agencyId` param + AGENCY branch
- [x] 3.10 `getCityDistribution()` — add `$agencyId` param + AGENCY branch

## 4. Frontend: AGENCY tab bar pruning

- [x] 4.1 In `ReportTabBar.jsx`, accept `role` prop and render only `overview` and `performance` tabs for AGENCY role
- [x] 4.2 In `Index.jsx`, pass role to ReportTabBar and AgencyScorecardSection

## 5. Frontend: AgencyScorecardSection single-row mode for AGENCY

- [x] 5.1 In `AgencyScorecardSection.jsx`, detect AGENCY role and render a single-row summary card instead of multi-agency comparison table

## 6. Tests

- [x] 6.1 Run existing test suite (24 Reports tests pass, no baseline regressions)
- [x] 6.2 Write ReportsAgencyScopingTest with AGENCY scenarios (6 tests: KPIs, status dist, scorecard, case status, client type, province options)
- [x] 6.3 Run full test suite — 1004 tests, 1001 passed, 3 skipped (same as baseline), 0 regressions

## 7. Verification

- [x] 7.1 `php -l` on all changed PHP files — no syntax errors
- [x] 7.2 `./vendor/bin/pint --test` — style compliant
- [x] 7.3 `npm run build` — builds successfully
- [x] 7.4 `composer run test` — 1004 tests, 1001 passed, 3 skipped
