# Reports Page — Exploration & Feature Ideas

> Explore mode capture | Not an implementation plan

---

## 1. Current State Summary

The reports page is organized into four tabs, each pulling data via Inertia deferred props:

```
┌──────────────────────────────────────────────────────────────────┐
│                         REPORTS PAGE                            │
├──────────────────────────────────────────────────────────────────┤
│  [Overview]  [Performance]  [Agencies & Services]  [Caseload]   │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  TAB: OVERVIEW                                                  │
│  ┌─ KPI Hero ─────────────────────────────────────────────┐     │
│  │ Active Caseload │ Completed │ Completion Rate │ Avg Res │     │
│  │ Total Referrals │ Pending   │ For Compliance            │     │
│  └──────────────────────────────────────────────────────────┘     │
│  ┌─ Referral Pipeline ────┐ ┌─ Referral Status ────────┐        │
│  │  Funnel bar chart      │ │  Doughnut                 │        │
│  └────────────────────────┘ └──────────────────────────┘        │
│  ┌─ Cases Over Time ──────┐ ┌─ Agency Scorecard ───────┐       │
│  │  Line/bar chart        │ │  Table (agency/rate/days) │       │
│  └────────────────────────┘ └──────────────────────────┘        │
│                                                                  │
│  [TAB: PERFORMANCE] → Status + Aging + CycleTime + Pipeline     │
│  [TAB: AGENCIES]     → Scorecard + Category + By Agency + Issue │
│  [TAB: CLIENTS]      → Case Status + Category + Demographics    │
│                         + Vulnerability + Geo + Employment       │
└──────────────────────────────────────────────────────────────────┘
```

### Chart types in use
- **Bar** (horizontal): Category, Cycle Time, Geographic, Referral Aging, Referrals by Agency, Case Issue, City, Employment country
- **Doughnut**: Referral Status, Case Status, Gender/ClientType/AgeGroup
- **Line/Bar toggleable**: Cases Over Time, Case Trends
- **Funnel bar**: Referral Pipeline
- **Table**: Agency Scorecard, Employment Position breakdown
- **KPI cards**: 7 MetricCards with optional sparklines and trend indicators

---

## 2. Low-Hanging Fruit — Already Computed, Not Displayed

The backend already computes these but the reports page never shows them:

| Prop | Role | What it contains | Current status |
|------|------|-----------------|----------------|
| `mostRequestedService` | CASE_MANAGER | `{ name, value }` — most-requested referral service | **Not rendered** |
| `agencyWorkload` | ADMIN | `{ labels, data }` — agencies ranked by referral count | **Not rendered** |
| `referralTrends` | AGENCY | `{ labels, datasets }` — 12-month referral trend line | **Not rendered** |
| `avgReferralCompletion` | AGENCY | `float` — average days to complete across all referrals | **Not rendered** |
| `overdueReferrals` | ADMIN | `{ count, referrals (paginated list) }` | Rendered only in admin `/overdue-referrals` page, **not in reports** |

### Idea: Surface these immediately

Add a `mostRequestedService` metric card to the overview KPI strip for CASE_MANAGER. Add `agencyWorkload` as a horizontal bar chart in the Agencies tab. For AGENCY, surface `referralTrends` as a trend chart and `avgReferralCompletion` as a metric card. Show `overdueReferrals.count` as a metric card in Performance tab with drill-down.

---

## 3. Feature Ideas by Category

### 3.1 Customer/Client Feedback & Satisfaction

The app has a full SERVQUAL feedback system (`Feedback`, `FeedbackInvitation`, `FeedbackServqualResponse`) that is **completely invisible in reports**.

```
┌──────────────────────────────────────────────────────┐
│  New tab suggestion: QUALITY                          │
│                                                      │
│  Feedback Summary Card                               │
│  ┌─────────────────────────────────────────────┐    │
│  │ Invitations Sent │ Response Rate │ Avg Rating│    │
│  └─────────────────────────────────────────────┘    │
│                                                      │
│  SERVQUAL Dimension Scores                           │
│  ┌─────────────────────────────────────────────┐    │
│  │ [Tangibles ████████░░] 4.2/5                │    │
│  │ [Reliability ██████░░░░] 3.8/5              │    │
│  │ [Responsiveness ███████░░] 4.0/5            │    │
│  │ [Assurance ████████░░] 4.3/5                │    │
│  │ [Empathy ███████░░░░] 3.9/5                 │    │
│  └─────────────────────────────────────────────┘    │
│                                                      │
│  Feedback Trend (ratings over time) — line chart     │
│  Comments word cloud / sentiment summary             │
│  Invitation funnel: Sent → Opened → Submitted        │
└──────────────────────────────────────────────────────┘
```

**Data sources**: `Feedback` table (overall_rating), `FeedbackServqualResponse` (dimension, expectation, perception), `FeedbackInvitation` (sent/submitted/expired statuses)

**Value**: DMW can measure service quality, identify low-performing agencies, track if client satisfaction is improving.

### 3.2 Milestone / Timeline Analytics

The `Milestone` model tracks referral lifecycle events. Currently not visible in reports.

```
Milestone-related ideas:
  • Average time per milestone stage (referral → processing → compliance → complete)
  • Bottleneck detection: which stage has the longest average dwell time?
  • Milestone completion rate over time
```

**Data source**: `Milestone` model (refr_id, type, completed_at timestamps)

### 3.3 Compliance Tracking

`ReferralComplianceRequirement` tracks per-requirement fulfillment per referral.

```
Compliance ideas:
  • Compliance requirement fulfillment rate (by agency, by service type)
  • Average time to fulfill compliance requirements
  • Most commonly required compliance items (word/bar chart)
```

**Data source**: `ReferralComplianceRequirement` (service_name, requirement_name, status, completed_at)

### 3.4 Notification / Engagement Analytics

`CaseNotification` records every notification sent to clients.

```
Engagement ideas:
  • Notifications sent over time (line chart)
  • Notification type breakdown (status update, milestone, referral)
  • Read rate (notifications with read_at set vs. total)
```

**Data source**: `CaseNotification` (type, read_at, created_at)

### 3.5 Attention & Action Items (port from dashboard)

The CaseManager dashboard has a sophisticated "Attention Required" section that the reports page lacks:

```
Attention Items ideas:
  • Overdue referral follow-ups (PENDING > 5 days)
  • Rejected referrals needing reassignment
  • Cases open > 7 days without any referral
  • For Compliance items nearing or past deadline
  • Could live in an "Actions" section or as alert metric cards
```

**Value**: Proactive case management — reports should not just describe the past, but drive action.

### 3.6 Client Vulnerability Profiles (port from dashboard)

The CaseManager dashboard shows a client snapshot card with OFW/NOK split, sex breakdown, and vulnerability indicator counts. The reports page scatters these across multiple sections. A consolidated **Client Profile Summary** card at the top of the Clients tab could:

```
  ┌─ Client Snapshot ─────────────────────────────────┐
  │ 1,247 clients | 842 OFW (67%) | 405 NOK (33%)    │
  │ Male 480 │ Female 767                             │
  │ PWD 23 │ Senior 45 │ Solo Parent 112 │ IP 8      │
  └───────────────────────────────────────────────────┘
```

### 3.7 Calendar / Time-Based Heatmap

```
Idea: Case Creation / Referral Activity Heatmap

    Mon Tue Wed Thu Fri Sat Sun
  W1 ██  ███ █ ████ █   █  █
  W2 ███ ██  ███ █   ██  █  █
  W3 █   ████ ██  ███ █   ██ █
  W4 ██  █   ███ █   ████ █  █

Shows which days of the week / hours see the most case activity.
Useful for staffing and resource planning.
```

**Data source**: `cases.created_at`, `referrals.created_at`

### 3.8 Service Request Analysis

The `required_services` field on referrals is a free-text/selection field. Currently only `mostRequestedService` returns the single top result.

```
Service Analysis ideas:
  • Service request distribution (bar chart of top 10-15 services)
  • Agency × Service matrix (heatmap: which agencies handle which services)
  • Service completion rate per type
  • Service trend over time (is a particular service growing?)
```

**Data source**: `Referral.required_services` grouped + `Agency` names

### 3.9 Performance Benchmarks / SLO Tracking

```
Benchmark ideas:
  • Target: 80% of referrals completed within 14 days
  ┌─ SLO Gauge ──────────────────────────────────────┐
  │                                                     │
  │  ┌─────────────────────────────────┐               │
  │  │    76%   ████████████████░░░░  │   Target: 80% │
  │  └─────────────────────────────────┘               │
  │                                                     │
  │  By Agency:                                        │
  │  Agency A: 92% ✓   Agency B: 71% ✗                │
  │  Agency C: 84% ✓   Agency D: 45% ✗                │
  └─────────────────────────────────────────────────────┘
```

Could be computed from existing `cycleTimeDistribution` data by comparing against configurable targets.

### 3.10 Trend Comparison / Period-Over-Period

The KPI hero already shows trend indicators (arrows up/down) from `kpis.kpiChanges`. But the visual charts don't overlay period comparison.

```
  ┌─ Cases Over Time ───────────────────────────┐
  │                                              │
  │  📈 Current Period   ━━━━━━━━━━━━━━           │
  │  📉 Previous Period  ─ ─ ─ ─ ─ ─ ─            │
  │                                              │
  │  Jan  Feb  Mar  Apr  May  Jun                │
  └──────────────────────────────────────────────┘
```

### 3.11 Drill-Down / Interactive Details

Most sections are static charts. No section links to the underlying records. Could add:

```
  • Click agency name → pre-filtered referrals list
  • Click status segment → filtered case list
  • Click chart bar → filtered view
  • "View details" links on metric cards
```

### 3.12 Survey / Sentiment Trend Over Time

If the NPS / satisfaction rating is collected per completed referral, a trend chart showing monthly average rating would be valuable.

### 3.13 Worker's Profile: Combined Demographics

Currently gender, age group, and client type are separate small pies. A combined profile view could show intersections:

```
  • OFW Female 26-40 (largest demographic segment)
  • OFW Male 41-60
  • Next of Kin Female 41-60
  ...useful for targeted outreach programs
```

---

## 4. Data Already Available, Badly Placed

Some backend data arrives at the frontend but isn't in the most useful tab:

| What | Current location | Better location |
|------|-----------------|-----------------|
| `cityDistribution` | Caseload & Clients tab (collapsed) | Geographic tab or alongside province map |
| `vulnerabilityDistribution` | Caseload & Clients tab | Client Profile section |
| `caseIssueDistribution` | Agencies & Services tab | Performance tab (bottleneck analysis) |
| `overview` | Fetched but **not rendered in UI** at all | Summary row at top |
| `referralAging` | Performance tab | Both Overview and Performance |
| `agencyWorkload` | **Not rendered** | Agencies tab |

---

## 5. Cross-Cutting UX Improvements

### 5.1 Save/Customize Report Views
Let users save a filter combination (date range + province + city) as a "Saved View" for quick switching.

### 5.2 Report Scheduling
Schedule automated PDF/Excel exports weekly or monthly and email to stakeholders.

### 5.3 Data Freshness Indicator
Show when the data was last refreshed (especially important for deferred props that load asynchronously).

### 5.4 Abnormalities / Anomaly Detection
Flag statistically unusual periods automatically:
- "This month's new cases (187) are 40% above the 6-month average"
- "Processing time jumped from 5.2d to 8.7d this week"

### 5.5 Role-Specific Default Tabs
- CASE_MANAGER lands on Overview → sees caseload-centric view
- AGENCY lands on Performance → sees referral-centric view
- ADMIN lands on Overview → sees system-wide view

(This is already partially handled by differing subtitles but the default tab is always 'overview'.)

---

## 6. Data Model Gaps (Backend Work Needed)

These features need new backend queries (no existing service method):

| Feature | What's needed |
|---------|---------------|
| Feedback satisfaction | New `getFeedbackSummary()`, `getSatisfactionTrend()`, `getServqualDimensionScores()` methods |
| Milestone bottleneck analysis | New `getMilestoneDwellTimes()`, `getBottleneckDetection()`, possibly denormalized aggregates |
| Compliance rate by agency | New `getComplianceByAgency()`, `getComplianceFulfillmentRate()` |
| Notification engagement | New `getNotificationStats()` |
| Service request distribution | New `getServiceDistribution()` replacing the single `mostRequestedService` |
| SLO benchmarks | New `getBenchmarkPerformance()` with configurable targets |
| Calendar heatmap | New `getActivityHeatmap()` |
| Client profile summary | Could derive from existing `genderDistribution` + `vulnerabilityDistribution` + `clientTypeDistribution` |
| Period-over-period overlay | Requires returning two date-range datasets from `getCasesOverTime()` |
| Drill-down endpoints | New Inertia visit routes or modal-based filtered views |
| Anomaly detection | New `getAnomalyFlags()` comparing current vs trailing averages |

---

## 7. Quick Wins (Frontend Only)

These don't need new backend work — just surface already-fetched data:

1. **`mostRequestedService`** → Add a metric card "Top Service Request" in Overview KPI row (CASE_MANAGER)
2. **`overview`** → Display as a compact info row: "X total cases · Y open · Z closed · W active agencies"
3. **`agencyWorkload`** → Horizontal bar chart in Agencies tab (ADMIN)
4. **`referralTrends`** → Trend chart in Overview/Performance tab (AGENCY)
5. **`avgReferralCompletion`** → KPI card in Performance tab (AGENCY)
6. **`overdueReferrals.count`** → Metric card with drill-down in Performance tab
7. **Attention/alerts section** → Port from CaseManager dashboard to reports Overview tab
8. **Client Snapshot card** → Combine gender/type/vulnerability data into a compact summary card

---

## 8. Open Questions

- **Feedback data scope**: Are feedback ratings per-agency or system-wide? Can CASE_MANAGER see feedback on referrals they sent?
- **Compliance data quality**: Are `ReferralComplianceRequirement` rows populated reliably, or is adoption spotty?
- **Notification read tracking**: Is `read_at` being set in practice, or is this always null?
- **Milestone adoption**: Are milestones used consistently across all case managers?
- **Date of birth completeness**: The age distribution query handles null DOB gracefully, but what's the actual fill rate?
- **`required_services` normalization**: Is this a controlled vocabulary or free text? That determines how useful a service distribution chart would be.
- **SLO targets existence**: Are there written SLAs for referral processing times that should be reflected in benchmarks?
- **Geographic address fill rate**: `getGeographicDistribution` masks low data quality by ignoring null/empty provinces. How many cases lack geographic data?
