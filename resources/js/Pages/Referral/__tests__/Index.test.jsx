import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ReferralIndex from '../Index.jsx';

const { routerGet, routerPatch } = vi.hoisted(() => ({ routerGet: vi.fn(), routerPatch: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: routerGet, patch: routerPatch, visit: vi.fn(), on: () => () => {} },
    usePage: () => ({ props: { auth: { user: { role: 'AGENCY', agcy_id: 'agency-a' } }, roles: { ADMIN: 'ADMIN', AGENCY: 'AGENCY', CASE_MANAGER: 'CASE_MANAGER', OFW: 'OFW' } } }),
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ info: vi.fn() }) }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ExportDialog', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({
    RowContextMenu: ({ children }) => <div>{children}</div>,
    RowContextMenuItem: () => null,
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

        expect(screen.getByText(/Agency A rejected/)).toBeInTheDocument();
        expect(screen.queryByText('Other agency rejected')).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: /Rejected/ }));

        expect(routerGet).toHaveBeenCalledWith('/referrals', { status: 'REJECTED' }, expect.objectContaining({ replace: true }));
        expect(routerGet.mock.calls[0][1]).not.toHaveProperty('page');
    });

    it('controls the table with the server default and starts the first column sort ascending', () => {
        globalThis.route = vi.fn(() => '/referrals');
        routerGet.mockClear();

        render(<ReferralIndex
            referrals={{ data: [{ id: 'ref-a', required_services: 'Service', status: 'PENDING', agency: { name: 'Agency A' }, case_file: { case_number: 'CASE-1' } }], total: 1, from: 1, to: 1, current_page: 1, last_page: 1, per_page: 15 }}
            filters={{}}
            stats={{}}
            agencies={[]}
            categories={[]}
            caseIssues={[]}
        />);

        const caseNumberHeader = screen.getByRole('button', { name: /Case #/ });
        expect(caseNumberHeader).toHaveTextContent('unfold_more');
        expect(screen.queryByRole('button', { name: /Reset Sort/ })).not.toBeInTheDocument();
        fireEvent.click(caseNumberHeader);

        expect(routerGet).toHaveBeenCalledWith('/referrals', { sort: 'case_number', direction: 'asc' }, expect.objectContaining({ replace: true }));
    });
});

describe('Referral Index reject reason', () => {
    function renderPendingRow() {
        globalThis.route = vi.fn(() => '/referrals');
        routerPatch.mockClear();
        render(<ReferralIndex
            referrals={{ data: [{ id: 'ref-a', required_services: 'Service', status: 'PENDING', agency: { name: 'Agency A' }, case_file: { case_number: 'CASE-1' } }], total: 1, from: 1, to: 1, current_page: 1, last_page: 1, per_page: 15 }}
            filters={{}}
            stats={{}}
            agencies={[]}
            categories={[]}
            caseIssues={[]}
        />);
    }

    it('requires a reason on reject and sends it with the decision', () => {
        renderPendingRow();
        fireEvent.click(screen.getByRole('button', { name: 'Reject' }));

        const modal = within(document.querySelector('.fixed.inset-0.z-50'));
        const reasonSelect = modal.getByRole('combobox');
        expect(within(reasonSelect).getAllByRole('option')).toHaveLength(7);

        const confirm = modal.getByRole('button', { name: 'Confirm Reject' });
        expect(confirm).toBeDisabled();

        fireEvent.change(reasonSelect, { target: { value: 'DUPLICATE_REFERRAL' } });
        fireEvent.change(modal.getByPlaceholderText('Enter your decision remark...'), { target: { value: 'Already referred last week' } });
        expect(confirm).not.toBeDisabled();

        fireEvent.click(confirm);
        expect(routerPatch).toHaveBeenCalledWith(
            '/referrals',
            expect.objectContaining({
                status: 'REJECTED',
                decision: 'REJECT',
                decision_comment: 'Already referred last week',
                rejection_reason: 'DUPLICATE_REFERRAL',
            }),
            expect.anything(),
        );
    });

    it('requires a longer comment when Other is selected', () => {
        renderPendingRow();
        fireEvent.click(screen.getByRole('button', { name: 'Reject' }));

        const modal = within(document.querySelector('.fixed.inset-0.z-50'));
        fireEvent.change(modal.getByRole('combobox'), { target: { value: 'OTHER' } });
        expect(modal.getByText('Please explain in at least 10 characters.')).toBeInTheDocument();

        const confirm = modal.getByRole('button', { name: 'Confirm Reject' });
        fireEvent.change(modal.getByPlaceholderText('Enter your decision remark...'), { target: { value: 'short' } });
        expect(confirm).toBeDisabled();

        fireEvent.change(modal.getByPlaceholderText('Enter your decision remark...'), { target: { value: 'Program paused for now' } });
        fireEvent.click(confirm);
        expect(routerPatch).toHaveBeenCalledWith(
            '/referrals',
            expect.objectContaining({ rejection_reason: 'OTHER', decision_comment: 'Program paused for now' }),
            expect.anything(),
        );
    });
});
