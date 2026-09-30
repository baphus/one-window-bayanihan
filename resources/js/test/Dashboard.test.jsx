import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from '../Pages/Dashboard.jsx';
import { agencyTour } from '../Onboarding/configs/agency';

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
    LineElement: {},
    PointElement: {},
    Filler: {},
    Title: {},
    Tooltip: {},
    Legend: {},
}));

vi.mock('react-chartjs-2', () => ({
    Doughnut: () => <div data-testid="doughnut-chart" />,
    Bar: (props) => <div data-testid="bar-chart" data-props={JSON.stringify(props.data ?? null)} />,
    Line: (props) => <div data-testid="line-chart" data-props={JSON.stringify(props.data ?? null)} />,
    Pie: () => <div data-testid="pie-chart" />,
}));

vi.mock('@tanstack/react-query', () => ({
    useQuery: () => ({ data: undefined, isLoading: false }),
    useMutation: () => ({ mutate: vi.fn(), isPending: false }),
    useQueryClient: () => ({ invalidateQueries: vi.fn() }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
    default: ({ children }) => (
        <main>
            <nav data-tour="sidebar-nav">
                <a data-tour="sidebar-help" href="/help">Help</a>
            </nav>
            <button data-tour="page-guide-button" type="button">?</button>
            <div data-tour="chatbot-launcher" />
            {children}
        </main>
    ),
}));

vi.mock('@/Components/DashboardBanner', () => ({
    default: () => <div data-testid="dashboard-banner" />,
}));

vi.mock('@/Components/GettingStartedChecklist', () => ({
    default: () => <div data-tour="getting-started-checklist" data-testid="getting-started-checklist" />,
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
    it('renders every configured Agency dashboard onboarding anchor', () => {
        Object.assign(pageProps, {
            auth: { user: { role: 'AGENCY', name: 'Agency Focal' } },
            dashboard: { workQueue: [] },
        });

        render(<Dashboard />);

        const dashboardPage = agencyTour.pages.find((page) => page.route === 'dashboard');
        for (const step of dashboardPage.steps) {
            expect(document.querySelector(step.element), `Missing Agency anchor: ${step.element}`).toBeInTheDocument();
        }
    });

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

    it('renders case manager 7-block sections and newest cases', () => {
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
                intakeReview: [
                    { id: 'case-2', caseNo: 'CASE-002', trackerNumber: 'TRK-002', clientName: 'Maria Santos', status: 'DRAFT', source: 'self_filed', isOwnDraft: false, createdAt: '2026-09-20T00:00:00Z', href: '/cases/case-2' },
                    { id: 'case-1', caseNo: 'CASE-001', clientName: 'Juan Dela Cruz', status: 'DRAFT', source: 'self_filed', isOwnDraft: false, createdAt: '2026-09-10T00:00:00Z', href: '/cases/case-1' },
                ],
                casesOverTime: [],
                recentActivity: [],
            },
        });

        render(
            <Dashboard />,
        );

        expect(screen.getByText('Case manager')).toBeInTheDocument();
        expect(screen.getByText('Numbers')).toBeInTheDocument();
        expect(screen.getByText('Notifications')).toBeInTheDocument();
        expect(screen.getByText('No notifications.')).toBeInTheDocument();
        expect(screen.getByText('Needs You')).toBeInTheDocument();
        expect(screen.getByText('Intake Queue')).toBeInTheDocument();
        expect(screen.getByText('Referral News')).toBeInTheDocument();
        expect(screen.getByText('Agencies Handling')).toBeInTheDocument();
        expect(screen.getByText('Case Activity Log')).toBeInTheDocument();
        expect(screen.getByText('Cases by status')).toBeInTheDocument();
        expect(screen.getByText('Referrals by agency')).toBeInTheDocument();
        expect(screen.getByText('Referrals by status')).toBeInTheDocument();
        expect(screen.getAllByText('OWWA').length).toBeGreaterThanOrEqual(2);

        const intake = within(document.querySelector('[data-tour="dashboard-intake-queue"]'));
        const rows = intake.getAllByText(/Juan Dela Cruz|Maria Santos/).map((node) => node.textContent);
        expect(rows).toEqual(['Juan Dela Cruz', 'Maria Santos']);
        expect(intake.getAllByText('Portal')).toHaveLength(2);
        expect(intake.getAllByText('DRAFT')).toHaveLength(2);
        const reviews = intake.getAllByRole('link', { name: 'Review' }).map((node) => node.getAttribute('href'));
        expect(reviews).toEqual(['/cases/case-1/review-intake', '/cases/case-2/review-intake']);
    });
});
