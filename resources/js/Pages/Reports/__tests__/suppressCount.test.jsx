import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, usePage: () => ({ props: { roles: { ADMIN: 'ADMIN', AGENCY: 'AGENCY', CASE_MANAGER: 'CASE_MANAGER', OFW: 'OFW' } } }) }));
vi.mock('@/Hooks/useLazyProp', () => ({ useLazyProp: () => [undefined, false] }));
vi.mock('@/Hooks/useReportFilters', () => ({
  useReportFilters: () => ({
    fromDateISO: '', toDateISO: '', quickRange: '',
    handleQuickRange: vi.fn(), resetDateRange: vi.fn(),
    setFromDateISO: vi.fn(), setToDateISO: vi.fn(),
    applyFilters: vi.fn(), hasPendingChanges: false,
  }),
}));

vi.mock('@/Components/ui/KpiCard', () => ({
  default: ({ label, value }) => <article data-testid={`metric-${label}`}>{value}</article>,
}));

// Only the sections the reports page actually renders are mocked; the
// component list here is kept in step with Reports/Index -> CasesTab /
// ReferralsTab so a deleted section can never linger as a stale mock.
vi.mock('@/Components/Reports/TopServiceRequestedCard', () => ({ default: () => null }));
vi.mock('@/Components/Reports/DateRangePicker', () => ({ default: () => null }));
vi.mock('@/Components/Reports/AgencyFilter', () => ({ default: () => null }));
vi.mock('@/Components/Reports/ProvinceCityFilter', () => ({ default: () => null }));
vi.mock('@/Components/Reports/ExportButtons', () => ({ default: () => null }));
vi.mock('@/Components/Reports/TrendIndicator', () => ({ default: () => null }));
vi.mock('@/Components/Reports/Sparkline', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/GeographicMapSection', () => ({ default: () => null }));
vi.mock('@/Pages/Reports/sections/LazyTrendChart', () => ({ default: () => null }));
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

    expect(screen.getAllByRole('region', { name: 'Key performance indicators' })).toHaveLength(1);
    for (const label of ['Active caseload', 'Completed referrals', 'Total referrals', 'Pending', 'For compliance']) {
      expect(screen.getByText(label).nextElementSibling).toHaveTextContent(expected);
    }
  });
});
