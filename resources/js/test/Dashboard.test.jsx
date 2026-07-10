import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from '../Pages/Dashboard.jsx';

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    router: { visit: vi.fn() },
}));

vi.mock('chart.js', () => ({
    Chart: { register: vi.fn() },
    CategoryScale: {},
    LinearScale: {},
    BarElement: {},
    ArcElement: {},
    Title: {},
    Tooltip: {},
    Legend: {},
}));

vi.mock('react-chartjs-2', () => ({
    Doughnut: () => <div data-testid="doughnut-chart" />,
    Bar: () => <div data-testid="bar-chart" />,
}));

vi.mock('@/Layouts/AppLayout', () => ({
    default: ({ children }) => <main>{children}</main>,
}));

vi.mock('@/Components/DashboardBanner', () => ({
    default: () => <div data-testid="dashboard-banner" />,
}));

vi.mock('../Pages/__TourPrototype', () => ({
    default: () => <div data-testid="tour-prototype" />,
}));

vi.mock('../Pages/Dashboard/Admin', () => ({
    default: () => <div>Admin dashboard</div>,
}));

vi.mock('@/Components/ui/StatusBadge', () => ({
    default: ({ status }) => <span>{status}</span>,
}));

vi.mock('@/Components/ui/KpiCard', () => ({
    default: ({ title, value }) => <div>{title}: {value}</div>,
}));

vi.mock('@/Components/ui/RecentTable', () => ({
    default: ({ title }) => <div>{title}</div>,
}));

describe('Dashboard role insights', () => {
    it('renders the agency focal work queue and sparse feedback empty state', () => {
        render(
            <Dashboard
                role="AGENCY"
                totalReferrals={2}
                completedReferrals={1}
                pendingReferrals={1}
                processingReferrals={0}
                rejectedReferrals={0}
                workQueue={[{ key: 'pendingReferrals', label: 'Pending', count: 1, note: 'Needs action.', tone: 'amber', href: '/referrals' }]}
                referralStatusDistribution={[{ key: 'pending', label: 'Pending', count: 1, percent: 50, tone: 'amber' }]}
                referralAgingBands={[{ key: '0-2', label: '0-2 days', count: 1, percent: 100, tone: 'emerald' }]}
                feedbackPulse={{ hasData: false, totalSent: 0, totalSubmitted: 0, href: '/feedbacks' }}
                recentActivity={[]}
            />,
        );

        expect(screen.getByText('Agency focal')).toBeInTheDocument();
        expect(screen.getByText('Dispatch board')).toBeInTheDocument();
        expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
        expect(screen.getByText('Feedback is still quiet')).toBeInTheDocument();
        expect(screen.getByText('Priority referrals')).toBeInTheDocument();
    });

    it('renders case manager coordination sections and priority lists', () => {
        render(
            <Dashboard
                role="CASE_MANAGER"
                totalCases={3}
                openCases={2}
                closedCases={1}
                totalReferrals={2}
                pendingReferrals={1}
                completedReferrals={1}
                workQueue={[{ key: 'agingOpenCases', label: 'Aging open cases', count: 1, note: 'Open seven days or more.', tone: 'amber', href: '/cases' }]}
                referralStatusDistribution={[{ key: 'pending', label: 'Pending', count: 1, percent: 50, tone: 'amber' }]}
                priorityCases={[{ id: 'case-1', caseNo: 'CASE-001', clientName: 'Juan Dela Cruz', status: 'OPEN', ageDays: 8, reason: 'No referral yet', href: '/cases/case-1' }]}
                priorityReferrals={[{ id: 'ref-1', caseNo: 'CASE-002', clientName: 'Maria Santos', service: 'Assistance', agencyName: 'OWWA', status: 'PENDING', ageDays: 6, href: '/referrals/ref-1' }]}
                agencyResponseScorecard={[{ agencyId: 'agency-1', agencyName: 'OWWA', activeCount: 1, overdueCount: 1, completedCount: 1, completionRate: 50, href: '/referrals' }]}
                allCases={[]}
                allReferrals={[]}
                casesOverTime={[]}
                recentActivity={[]}
            />,
        );

        expect(screen.getByText('Case manager')).toBeInTheDocument();
        expect(screen.getByText('Aging open cases')).toBeInTheDocument();
        expect(screen.getByText('Agency response scorecard')).toBeInTheDocument();
        expect(screen.getByText('Priority cases')).toBeInTheDocument();
        expect(screen.getByText('CASE-001')).toBeInTheDocument();
        expect(screen.getByText('CASE-002')).toBeInTheDocument();
    });
});
