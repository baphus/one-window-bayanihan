## 1. Reports Page Layout Prep

- [ ] 1.1 Add new section slots in `ReportsDashboard` (`resources/js/Pages/Reports/Index.jsx`): placeholders for the following in correct tab positions — Top Service Requested card (overview KPI), Overview banner (below KPI), Attention section (below banner), Agency Workload (agencies tab), Referral Trends (performance tab), Avg Completion card (performance tab), Overdue Referrals card (performance tab), Client Snapshot (clients tab, replacing old LazyDemographics row)

## 2. Metric Cards (report-metric-cards capability)

- [ ] 2.1 Add `TopServiceRequestedCard` component at `resources/js/Components/Reports/TopServiceRequestedCard.jsx` — consumes `mostRequestedService` via `useLazyProp`, renders a MetricCard with label, value, and trailing count. Gracefully empty-states. Only visible for CASE_MANAGER role.
- [ ] 2.2 Add `OverviewBanner` component at `resources/js/Components/Reports/OverviewBanner.jsx` — consumes `overview` via `useLazyProp`, renders a compact inline stat strip (total cases · open · closed · referrals · pending · agencies). Skeleton while loading.
- [ ] 2.3 Add `AgencyWorkloadChart` component at `resources/js/Components/Reports/AgencyWorkloadChart.jsx` — consumes `agencyWorkload` via `ReportLazySection`, renders a horizontal Bar chart. Only visible for ADMIN role.
- [ ] 2.4 Add `ReferralTrendsSection` component at `resources/js/Pages/Reports/sections/ReferralTrendsSection.jsx` — wraps `referralTrends` prop for TrendChart. Only visible for AGENCY role.
- [ ] 2.5 Add `AvgCompletionCard` and `OverdueReferralsCard` as MetricCards in the Performance tab — consume `avgReferralCompletion` and `overdueReferrals.count` via `useLazyProp`. Role-gated (AGENCY, ADMIN respectively). Overdue card has drill-down link to `route('overdue-referrals.index')`.
- [ ] 2.6 Wire all new metric card components into `ReportsDashboard` in `Index.jsx`, matching the tab and role conditions specified in the specs.

## 3. Attention / Alert Items (report-alert-items capability)

- [ ] 3.1 Create `AttentionSection` component at `resources/js/Components/Reports/AttentionSection.jsx` — computes alerts from `referralStatusDistribution` and `overdueReferrals`. Renders a conditional row of alert cards with amber/rose accents. Section hidden when no alerts exist or while loading.
- [ ] 3.2 Wire `AttentionSection` into the Overview tab in `Index.jsx`, positioned below the OverviewBanner and above the first chart row.

## 4. Client Snapshot Card (client-profile-summary capability)

- [ ] 4.1 Create `ClientSnapshotCard` component at `resources/js/Components/Reports/ClientSnapshotCard.jsx` — consumes `genderDistribution`, `clientTypeDistribution`, and `vulnerabilityDistribution` via `useLazyProp`. Renders a single card with three stat blocks (total clients, type/sex split, vulnerability pills). Omits zero-value categories.
- [ ] 4.2 Update `ReportsDashboard` in the Clients tab: replace the standalone `LazyDemographics` row with the new `ClientSnapshotCard`, hiding the old mini-pie-chart row.
- [ ] 4.3 Keep the existing `LazyDemographics` component available but commented out (or gated behind a feature flag) in case the finer-grained charts are still wanted alongside the summary.

## 5. Geographic Map (geographic-map capability)

- [ ] 5.1 **Backend**: Add `geographicMapData` deferred prop in `ReportsController` — a province-by-province list with resolved names and case counts, derived from the existing `getGeographicDistribution` data. Add `getGeographicMapData()` method in `ReportsService`.
- [ ] 5.2 Create `PhilippinesMap` SVG component at `resources/js/Components/Reports/PhilippinesMap.jsx` — inline SVG with Province VII paths (Cebu, Bohol, Negros Oriental, Siquijor). Each province is a `<path>` with fill color from case density. Tooltips on hover. Click fires `onProvinceClick` callback.
- [ ] 5.3 Create `GeographicMapSection` component at `resources/js/Pages/Reports/sections/GeographicMapSection.jsx` — wraps the SVG map with a Map/Bar toggle control. Defaults to map view. Falls back to existing bar chart.
- [ ] 5.4 Wire `GeographicMapSection` into the Clients tab's Geographic section, replacing or complementing the existing `GeographicSection`. The map click sets the province filter.

## 6. Polish & Verification

- [ ] 6.1 Verify all new components use existing design tokens (`cardShell`, `pageHeadingStyles`, `COLORS`, `ChartSkeleton`, `MetricCard`) and match the report's visual language.
- [ ] 6.2 Run the Laravel test suite (`composer run test`) to confirm no regressions from backend changes.
- [ ] 6.3 Run frontend tests (`npm run test:run`) to confirm no regressions.
- [ ] 6.4 Manually test each role (CASE_MANAGER, AGENCY, ADMIN) to verify role-gated metrics appear/hide correctly.
- [ ] 6.5 Verify map view toggle (map ↔ bar chart) works correctly with province click triggering filter update.
