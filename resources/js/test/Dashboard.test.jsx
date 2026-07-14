import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from '../Pages/Dashboard.jsx';

const { pageProps } = vi.hoisted(() => ({ pageProps: {} }));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Deferred: ({ children }) => children,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    router: { visit: vi.fn() },
    usePage: () => ({ props: pageProps }),
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

vi.mock('@/Components/GettingStartedChecklist', () => ({
    default: () => <div data-testid="getting-started-checklist" />,
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
        Object.assign(pageProps, {
            auth: { user: { role: 'AGENCY' } },
            dashboard: {
                totalReferrals: 2,
                completedReferrals: 1,
                pendingReferrals: 1,
                processingReferrals: 0,
                rejectedReferrals: 0,
                workQueue: [{ key: 'pendingReferrals', label: 'Pending', count: 1, note: 'Needs action.', tone: 'amber', href: '/referrals' }],
                referralStatusDistribution: [{ key: 'pending', label: 'Pending', count: 1, percent: 50, tone: 'amber' }],
                referralAgingBands: [{ key: '0-2', label: '0-2 days', count: 1, percent: 100, tone: 'emerald' }],
                feedbackPulse: { hasData: false, totalSent: 0, totalSubmitted: 0, href: '/surveys' },
                recentActivity: [],
            },
        });

        render(
            <Dashboard />,
        );

        expect(screen.getByText('Agency focal')).toBeInTheDocument();
        expect(screen.getByText('Referral status')).toBeInTheDocument();
        expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
        expect(screen.getByText('Feedback signals appear once clients respond to invitations.')).toBeInTheDocument();
        expect(screen.getByText('Priority referrals')).toBeInTheDocument();
    });

    it('renders case manager coordination sections and priority lists', () => {
        Object.assign(pageProps, {
            auth: { user: { role: 'CASE_MANAGER' } },
            dashboard: {
                totalCases: 3,
                openCases: 2,
                closedCases: 1,
                totalReferrals: 2,
                pendingReferrals: 1,
                completedReferrals: 1,
                workQueue: [{ key: 'agingOpenCases', label: 'Aging open cases', count: 1, note: 'Open seven days or more.', tone: 'amber', href: '/cases' }],
                referralStatusDistribution: [{ key: 'pending', label: 'Pending', count: 1, percent: 50, tone: 'amber' }],
                agencyBreakdown: [{ agencyId: 'agency-1', agencyName: 'OWWA', count: 1, overdueCount: 1 }],
                allCases: [{ id: 'case-1', caseNo: 'CASE-001', clientName: 'Juan Dela Cruz', status: 'OPEN' }],
                allReferrals: [{ id: 'ref-1', caseNo: 'CASE-002', clientName: 'Maria Santos', service: 'Assistance', agencyName: 'OWWA', status: 'PENDING' }],
                casesOverTime: [],
                recentActivity: [],
            },
        });

        render(
            <Dashboard />,
        );

        expect(screen.getByText('Case manager')).toBeInTheDocument();
        expect(screen.getByText('Aging open cases')).toBeInTheDocument();
        expect(screen.getByText('Agency load')).toBeInTheDocument();
        expect(screen.getByText('Recent case activity')).toBeInTheDocument();
        expect(screen.getByText('CASE-001')).toBeInTheDocument();
    });
});
