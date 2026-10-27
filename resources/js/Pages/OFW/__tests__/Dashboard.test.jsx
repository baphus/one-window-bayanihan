import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '../Dashboard';

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    usePage: () => ({ url: '/my-cases', props: { auth: { user: { name: 'Test OFW' } } } }),
    router: { post: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/Layouts/OfwLayout', () => ({
    default: ({ children }) => <div>{children}</div>,
}));

vi.mock('@/Components/OfwNotificationBell', () => ({ default: () => <div /> }));

globalThis.route = (name, params) => `/${name}/${Array.isArray(params) ? params.join('/') : params ?? ''}`;

const STORAGE_KEY = 'owb-view_ofw-cases';

const CASES = {
    data: [
        {
            id: 'case-1',
            tracker_number: 'TRK-AB12CD34',
            case_number: 'CASE-0001',
            summary: 'Unpaid wages claim against employer',
            status: 'OPEN',
            created_at: '2026-01-05T00:00:00Z',
        },
        {
            id: 'case-2',
            tracker_number: null,
            case_number: null,
            summary: 'Repatriation assistance',
            status: 'PENDING_REVIEW',
            created_at: '2026-02-10T00:00:00Z',
        },
    ],
};

const CASE_STATS = { total: 2, under_review: 1, in_progress: 1, completed: 0 };

function renderDashboard(overrides = {}) {
    return render(
        <Dashboard
            cases={overrides.cases ?? CASES}
            caseStats={overrides.caseStats ?? CASE_STATS}
        />,
    );
}

function layoutGroup() {
    return screen.getByRole('group', { name: 'Case layout' });
}

beforeEach(() => {
    window.localStorage.clear();
});

describe('OFW/Dashboard case view mode', () => {
    it('defaults to the grid view when nothing is stored', () => {
        renderDashboard();

        // Grid cards show the summary as card text; the table header does not exist.
        expect(screen.getByText('Unpaid wages claim against employer')).toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'Tracking ID' })).not.toBeInTheDocument();
        expect(within(layoutGroup()).getByRole('button', { name: 'Grid view' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('switches to the list view when the list toggle is clicked', () => {
        renderDashboard();

        fireEvent.click(within(layoutGroup()).getByRole('button', { name: 'List view' }));

        expect(screen.getByRole('columnheader', { name: 'Tracking ID' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Submitted' })).toBeInTheDocument();
        expect(within(layoutGroup()).getByRole('button', { name: 'List view' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('persists the chosen view mode so it survives navigation', () => {
        renderDashboard();

        fireEvent.click(within(layoutGroup()).getByRole('button', { name: 'List view' }));

        expect(JSON.parse(window.localStorage.getItem(STORAGE_KEY))).toBe('list');
    });

    it('restores a stored list preference on first render', () => {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify('list'));

        renderDashboard();

        expect(screen.getByRole('columnheader', { name: 'Tracking ID' })).toBeInTheDocument();
        expect(within(layoutGroup()).getByRole('button', { name: 'List view' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('renders the empty state without a layout toggle when the OFW has no cases', () => {
        renderDashboard({ cases: { data: [] } });

        expect(screen.getByText(/You haven't filed any cases yet/i)).toBeInTheDocument();
        expect(screen.queryByRole('group', { name: 'Case layout' })).not.toBeInTheDocument();
    });

    it('labels a pending-review case as Under Review in the grid', () => {
        renderDashboard();

        expect(screen.getAllByText('Under Review').length).toBeGreaterThan(0);
    });
});
