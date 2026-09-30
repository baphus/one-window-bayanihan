import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import CaseManagerDashboard from '../CaseManager';

const { pageProps, chartSpy, routerSpy } = vi.hoisted(() => ({
    pageProps: {},
    chartSpy: {},
    routerSpy: { visit: vi.fn() },
}));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Deferred: ({ children }) => children,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    router: routerSpy,
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
    Tooltip: {},
    Legend: {},
}));

vi.mock('react-chartjs-2', () => ({
    Bar: (props) => {
        chartSpy.bar = props;
        return <div data-testid="bar-chart" />;
    },
    Line: (props) => {
        chartSpy.line = props;
        return <div data-testid="line-chart" />;
    },
    Pie: (props) => (
        <div
            data-testid="pie-chart"
            data-labels={JSON.stringify(props.data.labels)}
            data-values={JSON.stringify(props.data.datasets[0].data)}
        />
    ),
}));

vi.mock('@/Components/GettingStartedChecklist', () => ({
    default: () => <div data-testid="getting-started-checklist" />,
}));

vi.mock('@/Components/ui/StatusBadge', () => ({
    default: ({ status }) => <span>{status}</span>,
}));

const dashboard = {
    myDraftCount: 2,
    openCases: 5,
    closedCases: 3,
    pendingReferrals: 4,
    processingReferrals: 7,
    forComplianceReferrals: 6,
    completedReferrals: 8,
    rejectedReferrals: 1,
    totalCases: 10,
    totalReferrals: 26,
    caseTrends: { labels: ['2026-01', '2026-02'], data: [3, 9] },
    allCases: [],
    priorityReferrals: [],
    priorityCases: [],
    agencyBreakdown: [],
    recentActivity: [],
};

const originalFetch = globalThis.fetch;

beforeEach(() => {
    routerSpy.visit.mockClear();
    vi.stubGlobal('fetch', vi.fn((url) => {
        if (String(url).includes('unread-count')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ count: 0 }) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({ data: [] }) });
    }));
});

afterEach(() => {
    globalThis.fetch = originalFetch;
});

function renderNumbers(customDashboard = dashboard) {
    Object.assign(pageProps, {
        auth: { user: { name: 'Maria Santos', role: 'CASE_MANAGER' } },
        dashboard: customDashboard,
    });

    const client = new QueryClient({
        defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
    });

    render(
        <QueryClientProvider client={client}>
            <CaseManagerDashboard dashboard={customDashboard} />
        </QueryClientProvider>,
    );

    return within(document.querySelector('[data-tour="dashboard-stats"]'));
}

function renderIntake(customDashboard = dashboard) {
    renderNumbers(customDashboard);

    return within(document.querySelector('[data-tour="dashboard-intake-queue"]'));
}

describe('CaseManager Numbers block', () => {
    it('shows exact case and referral counts with matching status badges', () => {
        const numbers = renderNumbers();

        // Case counts: DRAFT / OPEN / CLOSED.
        expect(numbers.getByText('2')).toBeInTheDocument();
        expect(numbers.getByText('5')).toBeInTheDocument();
        expect(numbers.getByText('3')).toBeInTheDocument();
        expect(numbers.getByText('DRAFT')).toBeInTheDocument();
        expect(numbers.getByText('OPEN')).toBeInTheDocument();
        expect(numbers.getByText('CLOSED')).toBeInTheDocument();

        // Referral counts: PENDING / PROCESSING / FOR_COMPLIANCE / COMPLETED / REJECTED.
        expect(numbers.getByText('4')).toBeInTheDocument();
        expect(numbers.getByText('7')).toBeInTheDocument();
        expect(numbers.getByText('6')).toBeInTheDocument();
        expect(numbers.getByText('8')).toBeInTheDocument();
        expect(numbers.getByText('PENDING')).toBeInTheDocument();
        expect(numbers.getByText('PROCESSING')).toBeInTheDocument();
        expect(numbers.getByText('FOR_COMPLIANCE')).toBeInTheDocument();
        expect(numbers.getByText('COMPLETED')).toBeInTheDocument();
        expect(numbers.getByText('REJECTED')).toBeInTheDocument();
    });

    it('toggles Line and Bar views while keeping the same dataset', () => {
        const numbers = renderNumbers();

        // Line is the default view.
        expect(numbers.getByTestId('line-chart')).toBeInTheDocument();
        expect(numbers.queryByTestId('bar-chart')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Line/ })).toHaveAttribute('aria-pressed', 'true');

        fireEvent.click(screen.getByRole('button', { name: /Bar/ }));

        expect(numbers.getByTestId('bar-chart')).toBeInTheDocument();
        expect(numbers.queryByTestId('line-chart')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Bar/ })).toHaveAttribute('aria-pressed', 'true');

        // Same weekly dataset in both views — labels and values unchanged.
        expect(chartSpy.bar.data.labels).toEqual(chartSpy.line.data.labels);
        expect(chartSpy.bar.data.datasets[0].data).toEqual(chartSpy.line.data.datasets[0].data);
        expect(chartSpy.bar.data.labels).toEqual(['2026-01', '2026-02']);
        expect(chartSpy.bar.data.datasets[0].data).toEqual([3, 9]);

        fireEvent.click(screen.getByRole('button', { name: /Line/ }));

        expect(numbers.getByTestId('line-chart')).toBeInTheDocument();
        expect(numbers.queryByTestId('bar-chart')).not.toBeInTheDocument();
    });

    it('falls back to referral trends when cases-over-time has no labels', () => {
        const numbers = renderNumbers({
            ...dashboard,
            casesOverTime: { labels: [], datasets: [{ data: [] }] },
            caseTrends: { labels: [], data: [] },
            referralTrends: {
                labels: ['2026-08', '2026-09'],
                datasets: [{ label: 'Referrals Created', data: [12, 18] }],
            },
        });

        expect(numbers.getByTestId('line-chart')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Bar/ }));

        expect(numbers.getByTestId('bar-chart')).toBeInTheDocument();
        expect(chartSpy.bar.data.labels).toEqual(['2026-08', '2026-09']);
        expect(chartSpy.bar.data.datasets[0].data).toEqual([12, 18]);
    });

    it('shows a plain empty state with no chart when every trend source is empty', () => {
        const numbers = renderNumbers({
            ...dashboard,
            casesOverTime: { labels: [], datasets: [{ data: [] }] },
            caseTrends: { labels: [], data: [] },
            referralTrends: { labels: [], datasets: [{ data: [] }] },
        });

        expect(numbers.queryByTestId('line-chart')).not.toBeInTheDocument();
        expect(numbers.queryByTestId('bar-chart')).not.toBeInTheDocument();
        expect(numbers.getByText('The trend appears as case activity accumulates.')).toBeInTheDocument();

        // Counts and badges still render — only the chart is empty.
        expect(numbers.getByText('DRAFT')).toBeInTheDocument();
        expect(numbers.getByText('FOR_COMPLIANCE')).toBeInTheDocument();
    });
});

describe('CaseManager status pies', () => {
    const dashboardWithAgencies = {
        ...dashboard,
        agencyBreakdown: [
            { agencyId: 'agency-1', agencyName: 'OWWA', count: 4, activeCount: 4, overdueCount: 1 },
            { agencyId: 'agency-2', agencyName: 'DSWD', count: 2, activeCount: 2, overdueCount: 0 },
        ],
    };

    function pieSlices(dataTour) {
        const card = within(document.querySelector(`[data-tour="${dataTour}"]`));
        const pie = card.getByTestId('pie-chart');
        return {
            card,
            labels: JSON.parse(pie.getAttribute('data-labels')),
            values: JSON.parse(pie.getAttribute('data-values')),
        };
    }

    it('renders three pies with slices matching the on-screen counts', () => {
        renderNumbers(dashboardWithAgencies);

        const cases = pieSlices('dashboard-pie-cases');
        expect(cases.labels).toEqual(['DRAFT', 'OPEN', 'CLOSED']);
        expect(cases.values).toEqual([2, 5, 3]);

        const agencies = pieSlices('dashboard-pie-agencies');
        expect(agencies.labels).toEqual(['OWWA', 'DSWD']);
        expect(agencies.values).toEqual([4, 2]);

        const status = pieSlices('dashboard-pie-status');
        expect(status.labels).toEqual(['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED']);
        expect(status.values).toEqual([4, 7, 6, 8, 1]);

        // Legends carry the exact status text with counts.
        expect(cases.card.getByText('DRAFT')).toBeInTheDocument();
        expect(status.card.getByText('FOR_COMPLIANCE')).toBeInTheDocument();
        expect(agencies.card.getByText('OWWA')).toBeInTheDocument();
    });

    it('shows a one-line empty state per pie when there is no data', () => {
        renderNumbers({
            ...dashboard,
            myDraftCount: 0,
            openCases: 0,
            closedCases: 0,
            pendingReferrals: 0,
            processingReferrals: 0,
            forComplianceReferrals: 0,
            completedReferrals: 0,
            rejectedReferrals: 0,
            referralStatusDistribution: [],
            agencyBreakdown: [],
            agencyResponseScorecard: [],
        });

        expect(screen.queryAllByTestId('pie-chart')).toHaveLength(0);
        expect(screen.getByText('No case data to show.')).toBeInTheDocument();
        expect(screen.getByText('No agency data to show.')).toBeInTheDocument();
        expect(screen.getByText('No referral data to show.')).toBeInTheDocument();
    });
});

describe('CaseManager Intake Queue block', () => {
    const dashboardWithIntakes = {
        ...dashboard,
        intakeReview: [
            { id: 'case-2', caseNo: 'CASE-002', trackerNumber: 'TRK-002', clientName: 'Maria Santos', status: 'DRAFT', source: 'self_filed', isOwnDraft: false, createdAt: '2026-09-20T00:00:00Z', href: '/cases/case-2' },
            { id: 'case-1', caseNo: 'CASE-001', clientName: 'Juan Dela Cruz', status: 'DRAFT', source: 'self_filed', isOwnDraft: false, createdAt: '2026-09-10T00:00:00Z', href: '/cases/case-1' },
        ],
    };

    it('renders portal-filed rows oldest-first with exact badges and Review routing', () => {
        const intake = renderIntake(dashboardWithIntakes);

        // Payload arrives newest-first; the queue shows oldest first.
        const names = intake.getAllByText(/Juan Dela Cruz|Maria Santos/).map((node) => node.textContent);
        expect(names).toEqual(['Juan Dela Cruz', 'Maria Santos']);

        expect(intake.getAllByText('Portal')).toHaveLength(2);
        expect(intake.getAllByText('DRAFT')).toHaveLength(2);

        const reviews = intake.getAllByRole('link', { name: 'Review' }).map((node) => node.getAttribute('href'));
        expect(reviews).toEqual(['/cases/case-1/review-intake', '/cases/case-2/review-intake']);

        expect(intake.getByRole('link', { name: /View all/ })).toHaveAttribute('href', '/cases/intake-queue');
    });

    it('shows a plain one-line empty state with no intakes', () => {
        const intake = renderIntake();

        expect(intake.getByText('No pending intakes.')).toBeInTheDocument();
        expect(intake.queryByRole('link', { name: 'Review' })).not.toBeInTheDocument();
    });
});

describe('CaseManager Notifications block', () => {
    const notificationList = [
        { id: 'notif-1', type: 'App\\Notifications\\ReferralCompletedNotification', data: { title: 'Referral completed', url: '/referrals/ref-1' }, read_at: null, created_at: new Date(Date.now() - 5 * 60000).toISOString() },
        { id: 'notif-2', type: 'App\\Notifications\\CaseCreatedNotification', data: { title: 'Case opened', url: '/cases/case-9' }, read_at: '2026-09-26T10:00:00Z', created_at: '2026-09-26T10:00:00Z' },
    ];

    function stubNotifications({ items = notificationList, count = 1 } = {}) {
        const calls = [];
        vi.stubGlobal('fetch', vi.fn((url, options = {}) => {
            calls.push([url, options]);
            if (String(url).endsWith('/read')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
            }
            if (String(url).includes('unread-count')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve({ count }) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ data: items }) });
        }));
        return calls;
    }

    function renderNotifications(customDashboard = dashboard) {
        renderNumbers(customDashboard);

        return within(document.querySelector('[data-tour="dashboard-work-queue"]'));
    }

    it('renders normalized rows with unread highlight, count, and View-all', async () => {
        stubNotifications();
        const card = renderNotifications();

        expect(await card.findByText('Referral completed')).toBeInTheDocument();
        expect(card.getByText('Success')).toBeInTheDocument();
        expect(card.getByText('Case opened')).toBeInTheDocument();

        expect(card.getByText('Referral completed').closest('div[class*="bg-blue-50"]')).not.toBeNull();
        expect(card.getByText('Case opened').closest('div[class*="bg-blue-50"]')).toBeNull();

        expect(card.getByText('1 unread')).toBeInTheDocument();
        expect(card.getByRole('link', { name: /View all/ })).toHaveAttribute('href', '/notifications/page');
    });

    it('routes row clicks through action_url and marks rows read', async () => {
        const calls = stubNotifications();
        const card = renderNotifications();

        fireEvent.click(await card.findByText('Referral completed'));
        expect(routerSpy.visit).toHaveBeenCalledWith('/referrals/ref-1');

        fireEvent.click(card.getByRole('button', { name: 'Read' }));
        await waitFor(() => {
            const patchCall = calls.find(([url]) => String(url).endsWith('/read'));
            expect(patchCall).toBeDefined();
            expect(patchCall[0]).toBe('/notifications/notif-1/read');
            expect(patchCall[1].method).toBe('PATCH');
        });
    });

    it('shows a plain one-line empty state when there are none', async () => {
        const card = renderNotifications();

        expect(await card.findByText('No notifications.')).toBeInTheDocument();
        expect(card.queryByRole('button', { name: 'Read' })).not.toBeInTheDocument();
    });
});
