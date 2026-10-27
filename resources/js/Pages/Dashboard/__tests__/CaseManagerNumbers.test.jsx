import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CaseManagerDashboard from '../CaseManager';

const { pageProps, chartSpy } = vi.hoisted(() => ({
    pageProps: {},
    chartSpy: {},
}));

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    usePage: () => ({ props: pageProps }),
}));

vi.mock('chart.js', () => ({
    Chart: { register: vi.fn() },
    CategoryScale: {},
    LinearScale: {},
    LineElement: {},
    PointElement: {},
    Filler: {},
    Tooltip: {},
    Legend: {},
}));

vi.mock('react-chartjs-2', () => ({
    Line: (props) => {
        chartSpy.line = props;
        return <div data-testid="line-chart" />;
    },
}));

vi.mock('@/Components/GettingStartedChecklist', () => ({
    default: () => <div data-testid="getting-started-checklist" />,
}));

const dashboard = {
    intakeReview: [],
    priorityCases: [],
    priorityReferrals: [],
    recentActivity: [],
};

beforeEach(() => {
    pageProps.auth = { user: { name: 'Maria Santos' } };
    chartSpy.line = undefined;
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
            kpis: { active: 5, awaiting_agency: 4, needs_action: 2, closed: 3 },
            volume: { labels: ['2026-01-01', '2026-01-02'], received: [3, 9], closed: [1, 4] },
            period: { label: 'Last 30 days' },
        }),
    })));
});

describe('CaseManager dashboard', () => {
    it('renders period KPIs and case volume chart', async () => {
        render(<CaseManagerDashboard dashboard={dashboard} />);

        expect(screen.getByText('Total active cases')).toBeInTheDocument();
        expect(screen.getByText('Good day, Maria')).toBeInTheDocument();
        expect(screen.getByText('Case volume over time')).toBeInTheDocument();
        expect(await screen.findByTestId('line-chart')).toBeInTheDocument();
        expect(chartSpy.line.data.datasets[0].data).toEqual([3, 9]);
        expect(chartSpy.line.data.datasets[1].data).toEqual([1, 4]);
    });

    it('shows empty volume state when period has no labels', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                kpis: { active: 0, awaiting_agency: 0, needs_action: 0, closed: 0 },
                volume: { labels: [], received: [], closed: [] },
            }),
        })));

        render(<CaseManagerDashboard dashboard={dashboard} />);

        await waitFor(() => expect(screen.getByText('No case activity in this period')).toBeInTheDocument());
        expect(screen.queryByTestId('line-chart')).not.toBeInTheDocument();
    });

    it('renders pending reviews and empty work queues', () => {
        render(<CaseManagerDashboard dashboard={{
            ...dashboard,
            pendingReviewCount: 1,
            intakeReview: [{ id: 'case-1', clientName: 'Juan Dela Cruz', caseNo: 'CASE-001', status: 'PENDING_REVIEW', createdAt: '2026-09-10T00:00:00Z', href: '/cases/case-1' }],
        }} />);

        expect(screen.getByText('Juan Dela Cruz')).toBeInTheDocument();
        expect(screen.queryByText('No filings waiting for review.')).not.toBeInTheDocument();
        expect(screen.getByText('Nothing needs attention.')).toBeInTheDocument();
    });
});
