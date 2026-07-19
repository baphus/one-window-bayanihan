import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null }));
vi.mock('@/Hooks/useLazyProp', () => ({ useLazyProp: () => [undefined, false] }));
vi.mock('@/Hooks/useReportFilters', () => ({
  useReportFilters: () => ({
    fromDateISO: '', toDateISO: '', quickRange: '',
    handleQuickRange: vi.fn(), resetDateRange: vi.fn(),
    setFromDateISO: vi.fn(), setToDateISO: vi.fn(),
    applyFilters: vi.fn(), hasPendingChanges: false,
  }),
}));

vi.mock('@/Components/Reports/MetricCard', () => ({
  default: ({ label, value }) => <article data-testid={`metric-${label}`}>{value}</article>,
}));

vi.mock('@/Components/Reports/TopServiceRequestedCard', () => ({ default: () => null }));
vi.mock('@/Components/Reports/OverviewBanner', () => ({ default: () => null }));
vi.mock('@/Components/Reports/AttentionSection', () => ({ default: () => null }));
vi.mock('@/Components/Reports/ClientSnapshotCard', () => ({ default: () => null }));
vi.mock('@/Components/Reports/AgencyWorkloadChart', () => ({ default: () => null }));
vi.mock('@/Components/Reports/AvgCompletionCard', () => ({ default: () => null }));
vi.mock('@/Components/Reports/OverdueReferralsCard', () => ({ default: () => null }));
vi.mock('@/Components/Reports/DateRangePicker', () => ({ default: () => null }));
vi.mock('@/Components/Reports/CaseStatusPieChart', () => ({ default: () => null }));
vi.mock('@/Components/Reports/DateScopeSelect', () => ({ default: () => null }));
vi.mock('@/Components/Reports/AgencyFilter', () => ({ default: () => null }));
vi.mock('@/Components/Reports/ReportTabBar', () => ({ default: () => null }));
vi.mock('@/Components/Reports/ProvinceCityFilter', () => ({ default: () => null }));
vi.mock('@/Components/Reports/ExportButtons', () => ({ default: () => null }));
vi.mock('@/Components/Reports/TrendIndicator', () => ({ default: () => null }));
vi.mock('@/Components/Reports/Sparkline', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/AgencyScorecardSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/StatusDistributionSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/CycleTimeSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/GeographicMapSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/CategorySection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/EmploymentSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/LazyTrendChart', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/LazyChartArticle', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/ReferralFunnelSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/ReferralTrendsSection', () => ({ default: () => null }));

import ReportsIndex from '../Index';

describe('Reports KPI count display characterization', () => {
  it.each([
    [0, '0'],
    [1, '1'],
    [4, '4'],
    [5, '5'],
  ])('renders KPI count %s exactly', (input, expected) => {
    render(<ReportsIndex role="CASE_MANAGER" kpis={{
      openCases: input,
      completedReferrals: input,
      totalReferrals: input,
      pendingReferrals: input,
      forComplianceReferrals: input,
    }} />);

    for (const label of ['Active Caseload', 'Completed This Period', 'Total Referrals', 'Pending', 'For Compliance']) {
      expect(screen.getByTestId(`metric-${label}`)).toHaveTextContent(expected);
    }
  });
});
