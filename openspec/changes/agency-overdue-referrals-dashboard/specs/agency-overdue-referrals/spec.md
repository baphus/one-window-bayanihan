## ADDED Requirements

### Requirement: Unified page for all roles
The `OverdueReferralController::index()` method SHALL render a single page `Admin/OverdueReferrals/Index` for all three roles (ADMIN, CASE_MANAGER, AGENCY). The old table page SHALL be replaced by the new rich dashboard. The route `/overdue-referrals` SHALL remain unchanged.

#### Scenario: Agency user visits overdue referrals page
- **WHEN** an authenticated user with role `AGENCY` visits `GET /overdue-referrals`
- **THEN** the system renders the `Admin/OverdueReferrals/Index` dashboard with data scoped to their agency

#### Scenario: Case manager visits overdue referrals page
- **WHEN** an authenticated user with role `CASE_MANAGER` visits `GET /overdue-referrals`
- **THEN** the system renders the `Admin/OverdueReferrals/Index` dashboard with data scoped to cases they own

#### Scenario: Admin visits overdue referrals page
- **WHEN** an authenticated user with role `ADMIN` visits `GET /overdue-referrals`
- **THEN** the system renders the `Admin/OverdueReferrals/Index` dashboard with data across all agencies

### Requirement: Inactivity-based overdue calculation
The `ReferralService::getOverdueReferralsDashboard()` method SHALL define "overdue" based on days since the referral's last activity, where activity is the most recent of: the latest milestone's `created_at`, or the referral's `updated_at`. The overdue threshold SHALL use the system setting `referral_overdue_days` (default 7).

#### Scenario: Referral with recent milestone is not overdue
- **WHEN** a referral was created 30 days ago but received a milestone 2 days ago, and `referral_overdue_days` is 7
- **THEN** the referral is overdue only if 2 days > 7 is false — it is NOT shown on the page

#### Scenario: Referral with no milestones and old creation date is overdue
- **WHEN** a referral was created 14 days ago, has no milestones, and `referral_overdue_days` is 7
- **THEN** the referral is overdue (14 days since creation > 7) and IS shown on the page

#### Scenario: Completed or rejected referrals excluded
- **WHEN** a referral has status `COMPLETED` or `REJECTED`
- **THEN** it MUST NOT appear in the overdue list regardless of age

### Requirement: Severity banding
Each overdue referral SHALL have a computed `severity` field: `mild` for 7–14 days since last activity, `moderate` for 15–29 days, `severe` for 30+ days. These thresholds SHALL be hardcoded in the service method, not user-configurable.

#### Scenario: Severity assignment
- **WHEN** a referral's days since last activity is 10
- **THEN** its severity is `mild`
- **WHEN** days since last activity is 22
- **THEN** its severity is `moderate`
- **WHEN** days since last activity is 45
- **THEN** its severity is `severe`

### Requirement: Role-scoped data filtering
The `getOverdueReferralsDashboard()` method SHALL accept the user's role, user ID, and agency ID. It SHALL apply the following scoping:
- **ADMIN**: no filter — sees overdue referrals across all agencies
- **CASE_MANAGER**: filters to referrals where the case's `user_id` matches the logged-in user
- **AGENCY**: filters to referrals where `agcy_id` matches the user's agency

Results SHALL be ordered by days since last activity descending (most stale first) by default.

#### Scenario: Agency sees only their own referrals
- **WHEN** a user belonging to Agency A with role `AGENCY` visits the overdue page
- **THEN** only referrals with `agcy_id` equal to Agency A are shown

#### Scenario: Case manager sees only their cases' referrals
- **WHEN** a user with role `CASE_MANAGER` and ID `user-123` visits the overdue page
- **THEN** only referrals whose `case_id` belongs to a case with `user_id = user-123` are shown

#### Scenario: Admin sees all referrals
- **WHEN** a user with role `ADMIN` visits the overdue page
- **THEN** referrals across all agencies are shown (no additional filter)

### Requirement: Enriched referral data
Each overdue referral row SHALL include: case number, client full name, required services, current status, agency name, days since last activity, severity, a human-readable description of the last activity, compliance requirement counts (total and fulfilled, only for FOR_COMPLIANCE status), and the referring case manager's name.

#### Scenario: Compliance progress shown for FOR_COMPLIANCE referrals
- **WHEN** a referral has status `FOR_COMPLIANCE` with 3 total requirements and 2 fulfilled
- **THEN** the row SHALL display "2 of 3 documents submitted"

#### Scenario: Compliance progress hidden for non-compliance statuses
- **WHEN** a referral has status `PENDING` or `PROCESSING`
- **THEN** compliance progress MUST NOT be displayed

### Requirement: Summary statistics
The page SHALL compute and display aggregate stats: total overdue count, counts per severity band (mild/moderate/severe), and counts per current status (PENDING/PROCESSING/FOR_COMPLIANCE). A computed bottleneck insight SHALL identify which status has the highest count.

#### Scenario: Stats render correctly
- **WHEN** there are 12 overdue referrals (6 mild, 4 moderate, 2 severe; 3 pending, 4 processing, 5 for_compliance)
- **THEN** the dashboard SHALL show Total: 12, Mild: 6, Moderate: 4, Severe: 2, and a status breakdown with PENDING: 3, PROCESSING: 4, FOR_COMPLIANCE: 5

#### Scenario: Bottleneck insight for compliance
- **WHEN** FOR_COMPLIANCE has the highest count among overdue referrals
- **THEN** the insight reads "Most overdue referrals are waiting for compliance documents"

#### Scenario: Bottleneck insight for pending
- **WHEN** PENDING has the highest count
- **THEN** the insight reads "Most overdue referrals are awaiting acceptance"

### Requirement: Role-aware action column
Each overdue referral card SHALL render role-appropriate actions:
- **AGENCY**: Shows only "View Full Details" link navigating to `route('referrals.show', id)`
- **ADMIN / CASE_MANAGER**: Shows "View Full Details" link + "Send Reminder" button

The "Send All Reminders" batch button and row selection checkboxes SHALL only appear for ADMIN and CASE_MANAGER roles.

#### Scenario: Agency user sees no reminder buttons
- **WHEN** an AGENCY user views the overdue page
- **THEN** there are no "Send Reminder", "Send All Reminders", or batch selection controls visible on the page

#### Scenario: Case manager sees send reminder button
- **WHEN** a CASE_MANAGER user views the overdue page
- **THEN** each card has a "Send Reminder" button, and a "Send All Reminders" batch button is visible in the header

#### Scenario: Clicking View Full Details navigates to referral detail
- **WHEN** a user clicks "View Full Details" on an overdue referral card
- **THEN** the browser navigates to the referral's show page regardless of role

### Requirement: Sort and filter controls
The list SHALL have a sort dropdown (Most Stale, Status, Client Name) defaulting to "Most Stale" (days since last activity descending). It SHALL have a status filter dropdown (All Status, PENDING, PROCESSING, FOR_COMPLIANCE) defaulting to "All Status". These SHALL be server-side via query parameters with Inertia partial reloads.

#### Scenario: Sort by status
- **WHEN** a user selects sort "Status"
- **THEN** the list is re-fetched sorted by referral status alphabetically, preserving pagination and other filters

#### Scenario: Filter by status
- **WHEN** a user selects filter "PENDING"
- **THEN** only overdue referrals with status `PENDING` are shown

### Requirement: Pagination
The list SHALL be paginated (15 per page) with standard pagination controls. Pagination SHALL use Inertia partial reloads (`only: ['referrals']`) to avoid re-rendering the full page.

#### Scenario: Page navigation
- **WHEN** a user clicks page 2 of the paginator
- **THEN** the system loads the next 15 overdue referrals, preserving sort and filter selections

### Requirement: Role-adaptive page header
The page subtitle SHALL adapt to the user's role:
- **ADMIN**: "Overdue referrals across all agencies — sorted by most stale first"
- **CASE_MANAGER**: "Overdue referrals from your cases — sorted by most stale first"
- **AGENCY**: "Referrals sent to your agency that need attention — sorted by most stale first"

#### Scenario: Header matches role
- **WHEN** a user with role `AGENCY` views the page
- **THEN** the subtitle reads "Referrals sent to your agency that need attention — sorted by most stale first"
- **WHEN** a user with role `ADMIN` views the page
- **THEN** the subtitle reads "Overdue referrals across all agencies — sorted by most stale first"
