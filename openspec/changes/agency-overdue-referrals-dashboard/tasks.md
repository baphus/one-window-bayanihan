## 1. Backend — Unified Service Method

- [x] 1.1 Add `getOverdueReferralsDashboard(string $userRole, ?string $userId, ?string $userAgencyId, int $overdueDays, array $filters)` method to `ReferralService.php` with the base query (inactivity-based overdue, exclude COMPLETED/REJECTED)
- [x] 1.2 Implement role-scoping via `match()`: ADMIN = all, CASE_MANAGER = subquery on caseFile.user_id, AGENCY = filter by agcy_id
- [x] 1.3 Add eager loads: `caseFile.client`, `caseFile.user` (case manager), `agency`, latest milestone; add `withCount` for compliance requirements (total + fulfilled)
- [x] 1.4 Compute per-referral attributes: `days_since_last_activity` (most recent of: latest milestone created_at, referral updated_at), `severity` (mild/moderate/severe), `last_activity_description` string
- [x] 1.5 Compute aggregate stats: total, mild_count, moderate_count, severe_count, pending_count, processing_count, for_compliance_count, bottleneck
- [x] 1.6 Add sort parameter support (sort_by: most_stale/status/client_name) and status filter parameter (status_filter: all/pending/processing/for_compliance)
- [x] 1.7 Return paginated results (15 per page) with stats and computed attributes
- [x] 1.8 Run existing tests to confirm no regressions from the new method

## 2. Backend — Unified Controller

- [x] 2.1 Update `OverdueReferralController::index()` to call the new `getOverdueReferralsDashboard()` with the user's role, ID, and agency ID, and pass `userRole` and `overdueDays` to the Inertia view
- [x] 2.2 Render the single `Admin/OverdueReferrals/Index` page for all roles
- [x] 2.3 Verify the route middleware `role:ADMIN,CASE_MANAGER,AGENCY` still covers all roles

## 3. Frontend — Rewrite Page Component

- [x] 3.1 Rewrite `resources/js/Pages/Admin/OverdueReferrals/Index.jsx` with `AppLayout` wrapper, `Head` title, and role-adaptive page header (different subtitle per role, batch reminders for non-agency)
- [x] 3.2 Implement Section 1: Summary Cards grid using 4 `KpiCard` components for Total, Mild, Moderate, Severe with role-appropriate icons and color accents
- [x] 3.3 Implement Section 2: Status Breakdown with horizontal bars showing PENDING/PROCESSING/FOR_COMPLIANCE counts, percentages, and computed bottleneck insight text
- [x] 3.4 Implement Section 3 sort/filter bar with dropdowns for sort (Most Stale/Status/Client Name) and status filter (All/PENDING/PROCESSING/FOR_COMPLIANCE) using Inertia partial reloads
- [x] 3.5 Implement rich card list rendering using a new `OverdueCard` sub-component for each referral item
- [x] 3.6 Create `resources/js/Pages/Admin/OverdueReferrals/OverdueCard.jsx` with: severity dot indicators, case number + client name, service, status badge, agency name, days stale, compliance progress (for FOR_COMPLIANCE only), last activity description, referring case manager, and role-aware actions
- [x] 3.7 Implement role-aware actions in OverdueCard: AGENCY gets only "View Full Details" link; ADMIN/CASE_MANAGER additionally get "Send Reminder" button per row
- [x] 3.8 Implement batch selection (checkboxes + "Send Selected Reminders" bar + "Send All Reminders" header button), visible only for ADMIN/CASE_MANAGER roles
- [x] 3.9 Add pagination using the existing paginator pattern (Inertia partial reloads with `only: ['referrals']`)
- [x] 3.10 Add empty state: when no overdue referrals, show a role-adaptive "All clear" message
- [x] 3.11 Add confirmation dialog for send-reminder actions to prevent accidental clicks

## 4. Tests

- [x] 4.1 Update `tests/Feature/Security/OverdueReferralsAccessTest.php` to cover all three roles accessing the unified page
- [x] 4.2 Add test for role-scoped data filtering: ADMIN sees all, CASE_MANAGER sees only their cases, AGENCY sees only their agency
- [x] 4.3 Add test for inactivity-based overdue calculation and severity band computation
- [x] 4.4 Add test verifying send-reminder actions are role-gated (available for admin/cm, absent for agency)
- [x] 4.5 Run PHP style check (passes: `./vendor/bin/pint --test`)

## 5. Verification

- [x] 5.1 Run `php artisan test` to confirm all existing tests pass with no regressions (OverdueReferralsAccessTest: 8/8 passed, ReferralAccessTest: 3/3 passed)
- [x] 5.2 Run `npm run test:run` to confirm frontend Vitest suite passes (36/36 passed)
- [x] 5.3 Run `./vendor/bin/pint --test` to confirm PHP style compliance (passed)
- [ ] 5.4 Visual walk-through of the page logged in as each role: verify data scoping, action buttons, header copy, severity bands, status breakdown, sort/filter, pagination, empty state
