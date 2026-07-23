import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CaseIndex from './Case/Index.jsx';
import ClientIndex from './Client/Index.jsx';
import ReferralIndex from './Referral/Index.jsx';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: routerGet, on: () => () => {} },
    usePage: () => ({ props: { auth: { user: { role: 'ADMIN' } } } }),
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ info: vi.fn() }) }));
vi.mock('@/Hooks/usePersistedColumns', () => ({ default: (_, defaults) => [defaults, vi.fn()] }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ExportDialog', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({
    RowContextMenu: ({ children }) => <div>{children}</div>,
    RowContextMenuItem: () => null,
}));
vi.mock('@/Components/ui/UnifiedTable', () => ({
    UnifiedTable: ({ data, searchValue, searchPlaceholder, onSearchChange, onSearchClear }) => (
        <section data-testid="list-table">
            <input placeholder={searchPlaceholder} value={searchValue} onChange={(event) => onSearchChange(event.target.value)} />
            {searchValue && <button type="button" aria-label="Clear search" onClick={onSearchClear}>Clear</button>}
            <div data-testid="row-ids">{data.map((row) => <span key={row.id}>{row.id}</span>)}</div>
        </section>
    ),
}));

globalThis.route = vi.fn((name) => `/${name}`);

const pageCases = [
    {
        name: 'cases',
        Component: CaseIndex,
        prop: 'cases',
        placeholder: /tracking ID/,
        original: [{ id: 'case-original-1' }, { id: 'case-original-2' }],
        searched: [{ id: 'case-match' }],
        props: { stats: {}, users: [], agencies: [], categories: [], caseIssues: [] },
    },
    {
        name: 'clients',
        Component: ClientIndex,
        prop: 'clients',
        placeholder: /name, email/,
        original: [{ id: 'client-original-1' }, { id: 'client-original-2' }],
        searched: [{ id: 'client-match' }],
        props: { stats: {}, users: [], agencies: [], categories: [], caseIssues: [] },
    },
    {
        name: 'referrals',
        Component: ReferralIndex,
        prop: 'referrals',
        placeholder: /referral ID/,
        original: [{ id: 'referral-original-1' }, { id: 'referral-original-2' }],
        searched: [{ id: 'referral-match' }],
        props: { stats: {}, agencies: [], categories: [], caseIssues: [] },
    },
];

function paginator(data) {
    return { data, total: data.length, from: 1, to: data.length, current_page: 1, last_page: 1, per_page: 15 };
}

describe('Bug 22 list search clearing', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        routerGet.mockReset();
    });

    afterEach(() => vi.useRealTimers());

    it.each(pageCases)('$name restores the original first page after search completes and is cleared', async ({ Component, prop, placeholder, original, searched, props }) => {
        let completeRequest;
        routerGet.mockImplementation((_url, params) => new Promise((resolve) => {
            completeRequest = () => resolve(params);
        }));

        const makeProps = (rows, filters = {}) => ({
            [prop]: paginator(rows),
            filters,
            ...props,
        });
        const view = render(<Component {...makeProps(original)} />);
        const search = screen.getByPlaceholderText(placeholder);

        fireEvent.change(search, { target: { value: 'match' } });
        act(() => vi.advanceTimersByTime(400));
        expect(routerGet).toHaveBeenCalledTimes(1);
        expect(routerGet.mock.calls[0][1]).toMatchObject({ search: 'match' });
        completeRequest();
        await act(async () => {});
        view.rerender(<Component {...makeProps(searched, { search: 'match' })} />);
        expect(screen.getByTestId('row-ids')).toHaveTextContent(`${searched[0].id}`);

        fireEvent.click(screen.getByRole('button', { name: 'Clear search' }));
        expect(routerGet).toHaveBeenCalledTimes(2);
        expect(routerGet.mock.calls[1][1]).not.toHaveProperty('search');
        completeRequest();
        await act(async () => {});
        view.rerender(<Component {...makeProps(original)} />);

        expect(screen.getByTestId('row-ids')).toHaveTextContent(original.map((row) => row.id).join(''));
        expect(screen.getByTestId('row-ids')).not.toHaveTextContent(searched[0].id);
    });
});
