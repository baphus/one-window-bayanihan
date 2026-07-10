## ADDED Requirements

### Requirement: Backend query scoping by agency

When an AGENCY-role user views reports, every metric query SHALL scope data to `referrals.agcy_id` matching the agency ID of the authenticated user. Case-based metrics SHALL be scoped to cases that have at least one referral belonging to the user's agency. Client-based metrics SHALL inherit scoping through their associated cases.

#### Scenario: Referral metrics scope to agency

- **WHEN** an AGENCY user views referral metrics (KPIs, referral status distribution, cycle time distribution, referral aging, referral trends, avg completion days, most requested service, agency scorecard)
- **THEN** the query SHALL include `WHERE referrals.agcy_id = {user's agency_id}`
- **AND** referral counts SHALL NOT include referrals belonging to other agencies

#### Scenario: Case metrics scope to agency's referred cases

- **WHEN** an AGENCY user views case-based metrics (case status distribution, category distribution, cases over time, case issue distribution)
- **THEN** the query SHALL scope to cases where `cases.id IN (SELECT case_id FROM referrals WHERE agcy_id = {user's agency_id})`
- **AND** case counts SHALL NOT include cases with no referral to the user's agency

#### Scenario: Client demographics scope to agency's referred clients

- **WHEN** an AGENCY user views client demographic metrics (gender distribution, age group distribution, client type distribution)
- **THEN** client queries SHALL be scoped via `filteredClientIds()` which limits to clients whose cases have a referral to the user's agency

#### Scenario: Geographic data scopes to agency's referral base

- **WHEN** an AGENCY user views geographic distribution or map data
- **THEN** province/city counts SHALL only include clients whose cases have a referral to the user's agency

#### Scenario: Province/city filter options scope to agency

- **WHEN** an AGENCY user opens province or city dropdown filters
- **THEN** the options SHALL only include provinces/cities where the agency has referral clients

#### Scenario: No-param methods accept role and agency ID

- **WHEN** `getReferralTrends()`, `getAvgReferralCompletionDays()`, `categoryDistribution()`, `getCaseStatusDistribution()`, or `getClientTypeDistribution()` are called for an AGENCY user
- **THEN** they SHALL accept and apply the agency-scoping filter

#### Scenario: CASE_MANAGER and ADMIN reports unchanged

- **WHEN** a CASE_MANAGER or ADMIN user views any report
- **THEN** their data SHALL remain the same as before (no scoping by agency)
- **AND** the new `$agencyId` parameter defaulting to `null` SHALL ensure no behavior change for non-AGENCY roles

### Requirement: AGENCY report UI hides irrelevant sections

When an AGENCY-role user views the reports page, the UI SHALL only show tabs and sections that are meaningful for single-agency data.

#### Scenario: AGENCY sees only Overview and Performance tabs

- **WHEN** an AGENCY user opens the reports page
- **THEN** the tab bar SHALL show only "Overview" and "Performance" tabs
- **AND** "Agencies & Services" and "Caseload & Clients" tabs SHALL NOT be rendered

#### Scenario: Geographic map hidden for AGENCY

- **WHEN** an AGENCY user is on any report tab
- **THEN** the geographic map section SHALL NOT render

#### Scenario: Multi-agency comparison components hidden for AGENCY

- **WHEN** an AGENCY user views reports
- **THEN** `AgencyWorkloadChart`, `LazyChartArticle` for `referralAgencyDistribution` SHALL NOT render

#### Scenario: AgencyScorecard renders as single-row card for AGENCY

- **WHEN** an AGENCY user views the report
- **THEN** the agency scorecard section SHALL render as a single-row summary card
- **AND** SHALL NOT display data for other agencies
