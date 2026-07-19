import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ReferralIndex from '../Index.jsx';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: routerGet, on: () => () => {} },
    usePage: () => ({ props: { auth: { user: { role: 'AGENCY', agcy_id: 'agency-a' } } } }),
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ info: vi.fn() }) }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ExportDialog', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({
    RowContextMenu: ({ children }) => <div>{children}</div>,
    RowContextMenuItem: () => null,
}));
vi.mock('@/Components/ui/UnifiedTable', () => ({
    UnifiedTable: ({ data, quickFilters, onPageChange }) => (
        <section>
            {quickFilters}
            <button onClick={() => onPageChange(2)}>Go to page 2</button>
            {data.map((row) => <div key={row.id}>{row.required_services} / {row.agency?.name}</div>)}
        </section>
    ),
}));

describe('Referral Index agency filtering', () => {
    it('resets a stale page to one when Rejected is selected and keeps another agency invisible', () => {
        globalThis.route = vi.fn(() => '/referrals');
        routerGet.mockClear();

        render(<ReferralIndex
            referrals={{ data: [{ id: 'ref-a', required_services: 'Agency A rejected', status: 'REJECTED', agency: { name: 'Agency A' } }], total: 1, from: 1, to: 1, current_page: 2, last_page: 2, per_page: 15 }}
            filters={{ page: 2 }}
            stats={{ rejected: 1 }}
            agencies={[]}
            categories={[]}
            caseIssues={[]}
        />);

        expect(screen.getAllByText((_, element) => element?.textContent?.includes('Agency A rejected')).length).toBeGreaterThan(0);
        expect(screen.queryByText('Other agency rejected')).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: /Rejected/ }));

        expect(routerGet).toHaveBeenCalledWith('/referrals', { status: 'REJECTED' }, expect.objectContaining({ replace: true }));
        expect(routerGet.mock.calls[0][1]).not.toHaveProperty('page');
    });
});
