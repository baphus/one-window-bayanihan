## ADDED Requirements

### Requirement: Attention Required section in Overview tab
The Overview tab SHALL display an "Attention Required" section below the KPI hero and above the first chart row, showing actionable alerts computed from already-loaded report data.

#### Scenario: Section renders when overdue referrals exist
- **WHEN** `overdueReferrals.count` is greater than 0
- **THEN** a MetricCard or alert card with amber accent SHALL render showing the overdue count and a link to route('overdue-referrals.index')

#### Scenario: Section renders when pending referral volume is high
- **WHEN** `referralStatusDistribution.data[0]` (PENDING count) is greater than 10
- **THEN** an alert card SHALL render: "X referrals pending — review caseload" with a link to the referrals index

#### Scenario: Section hidden when no alerts
- **WHEN** all alert conditions evaluate to zero/false
- **THEN** the "Attention Required" section SHALL NOT render

#### Scenario: Section hidden while loading
- **WHEN** any alert data source is still loading
- **THEN** the entire section SHALL be hidden (not flash skeleton)
