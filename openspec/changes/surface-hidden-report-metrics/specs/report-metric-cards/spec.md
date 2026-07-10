## ADDED Requirements

### Requirement: Top Service Requested metric card
The reports page SHALL display a "Top Service Requested" MetricCard in the Overview tab's KPI hero row when the current user role is CASE_MANAGER.

#### Scenario: Card appears for CASE_MANAGER with data
- **WHEN** the user role is CASE_MANAGER AND `mostRequestedService` returns `{ name: "Repatriation Assistance", value: 42 }`
- **THEN** a MetricCard with label "Top Service Requested", value "Repatriation Assistance", and a trailing subtitle "42 requests this period" SHALL appear in the Overview KPI row

#### Scenario: Card gracefully empty-states for CASE_MANAGER
- **WHEN** the user role is CASE_MANAGER AND `mostRequestedService` returns `{ name: "N/A", value: 0 }`
- **THEN** the card SHALL display value "—" or "None yet"

#### Scenario: Card hidden for non-CASE_MANAGER roles
- **WHEN** the user role is AGENCY or ADMIN
- **THEN** the "Top Service Requested" card SHALL NOT appear

### Requirement: Case Overview summary banner
The reports page SHALL display a compact summary banner below the KPI hero strip in the Overview tab, showing total cases, open/closed splits, and active agencies from the `overview` deferred prop.

#### Scenario: Banner renders with data
- **WHEN** `overview` returns `{ totalCases: 150, openCases: 42, closedCases: 108, totalReferrals: 320, pendingReferrals: 15, activeAgencies: 12 }`
- **THEN** the rendered banner SHALL display all six values as inline stat items: "150 total cases · 42 open · 108 closed · 320 referrals · 15 pending · 12 active agencies"

#### Scenario: Banner hidden while loading
- **WHEN** `overview` data is still loading
- **THEN** a ChartSkeleton-style placeholder SHALL render instead of the banner

### Requirement: Agency Workload chart (ADMIN)
The Agencies & Services tab SHALL display a horizontal bar chart of all agencies ranked by referral count, using the `agencyWorkload` deferred prop, when the user role is ADMIN.

#### Scenario: Chart renders for ADMIN
- **WHEN** the user role is ADMIN AND `agencyWorkload` returns `{ labels: ["Agency A", "Agency B"], data: [50, 30] }`
- **THEN** a horizontal bar chart titled "Agency Workload" SHALL render in the Agencies tab between the Agency Scorecard and the "Referrals by Agency" chart

#### Scenario: Chart not rendered for non-ADMIN
- **WHEN** the user role is not ADMIN
- **THEN** the Agency Workload chart SHALL NOT render

### Requirement: Referral Trends chart (AGENCY)
The Performance tab SHALL display a line/bar trend chart of referral activity over 12 months when the user role is AGENCY.

#### Scenario: Trend chart renders for AGENCY
- **WHEN** the user role is AGENCY AND `referralTrends` returns a `{ labels, datasets }` object
- **THEN** a toggleable line/bar TrendChart titled "Referral Trends" SHALL render in the Performance tab

#### Scenario: Chart not rendered for non-AGENCY
- **WHEN** the user role is not AGENCY
- **THEN** the Referral Trends chart SHALL NOT render

### Requirement: Avg Completion Time metric card (AGENCY)
The Performance tab SHALL display a MetricCard showing the average referral completion days when the user role is AGENCY.

#### Scenario: Card renders for AGENCY
- **WHEN** the user role is AGENCY AND `avgReferralCompletion` returns `8.4`
- **THEN** a MetricCard with label "Avg Completion Time" and value "8.4d" SHALL appear in the Performance tab

### Requirement: Overdue Referrals count badge with drill-down (ADMIN)
The Performance tab SHALL display a MetricCard showing the count of overdue referrals (>14 days in active status) when the user role is ADMIN, with a drill-down link to the admin overdue referrals page.

#### Scenario: Card renders with drill-down for ADMIN
- **WHEN** the user role is ADMIN AND `overdueReferrals.count` returns `7`
- **THEN** a MetricCard with label "Overdue Referrals", value "7", and an accent/badge SHALL render. Clicking the card SHALL navigate to `route('overdue-referrals.index')`

#### Scenario: Card hidden for non-ADMIN
- **WHEN** the user role is not ADMIN
- **THEN** the Overdue Referrals card SHALL NOT render

## MODIFIED Requirements

*(No existing specs to modify.)*
