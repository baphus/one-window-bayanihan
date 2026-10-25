import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CaseIndex from '../Index.jsx';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  router: {
    get: routerGet,
    visit: vi.fn(),
    patch: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    on: () => () => {},
  },
  usePage: () => ({
    props: {
      auth: { user: { role: 'CASE_MANAGER', id: 'u1' } },
      roles: { ADMIN: 'ADMIN', AGENCY: 'AGENCY', CASE_MANAGER: 'CASE_MANAGER', OFW: 'OFW' },
    },
  }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Components/ExportDialog', () => ({ default: () => null }));
vi.mock('@/Components/ExportExcelButton', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({
  RowContextMenu: ({ children }) => <div>{children}</div>,
  RowContextMenuItem: () => null,
}));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }) }));

const caseRow = {
  id: 'case-1',
  case_number: 'CASE-001',
  tracker_number: 'TRK-001',
  client_type: 'OFW',
  status: 'OPEN',
  vulnerability_indicator: 'None',
  created_at: '2026-01-05T00:00:00Z',
  client: { first_name: 'Maria', last_name: 'Santos' },
  referrals: [],
};

function page(overrides = {}) {
  return { data: [caseRow], total: 1, from: 1, to: 1, current_page: 1, last_page: 1, per_page: 15, ...overrides };
}

function renderIndex(props = {}) {
  return render(
    <CaseIndex
      cases={page()}
      filters={{}}
      stats={{}}
      users={[]}
      agencies={[]}
      categories={[]}
      caseIssues={[]}
      {...props}
    />,
  );
}

function lastVisitOptions() {
  return routerGet.mock.calls[routerGet.mock.calls.length - 1][2];
}

beforeEach(() => {
  globalThis.route = vi.fn((name, params) => (params ? `/cases?${new URLSearchParams(params)}` : '/cases'));
  routerGet.mockClear();
  vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
  vi.useRealTimers();
});

describe('Case Index table loading', () => {
  // Behaviour that must survive the move to the shared hook: a visit marks the
  // table busy and the end of that visit clears it. The hook owns loading via
  // the onStart/onFinish/onCancel callbacks it injects into router.get.
  it('marks the table busy for the duration of a visit', async () => {
    const { container } = renderIndex();
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'false');

    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'maria' } });
    await act(async () => { vi.advanceTimersByTime(400); });

    const options = lastVisitOptions();
    expect(options.showProgress).toBe(false);

    await act(async () => { options.onStart({}); });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'true');

    await act(async () => { options.onFinish({}); });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'false');
  });

  it('clears the busy state when a visit is cancelled', async () => {
    const { container } = renderIndex();

    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'maria' } });
    await act(async () => { vi.advanceTimersByTime(400); });

    const options = lastVisitOptions();
    await act(async () => { options.onStart({}); });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'true');

    await act(async () => { options.onCancel({}); });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'false');
  });
});

describe('Case Index search debounce', () => {
  it('sends the search after the debounce window', async () => {
    renderIndex();

    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'maria' } });
    expect(routerGet).not.toHaveBeenCalled();

    await act(async () => { vi.advanceTimersByTime(400); });

    expect(routerGet).toHaveBeenCalledTimes(1);
    expect(routerGet.mock.calls[0][1]).toMatchObject({ search: 'maria' });
  });

  // THE BUG: handleSearchChange closed over `filters` from the render that
  // created the timeout, so a quick filter toggled during the 400ms window was
  // dropped when the timer fired.
  it('keeps a filter toggled during the debounce window', async () => {
    renderIndex();

    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'maria' } });
    // Toggle a quick filter while the search timer is still pending.
    fireEvent.click(screen.getByRole('button', { name: /^Open$/ }));

    await act(async () => { vi.advanceTimersByTime(400); });

    const searchCall = routerGet.mock.calls.find((call) => call[1]?.search === 'maria');
    expect(searchCall).toBeDefined();
    expect(searchCall[1]).toMatchObject({ search: 'maria', status: 'OPEN' });
  });

  it('does not lose a status filter that arrived before typing', async () => {
    renderIndex({ filters: { status: 'CLOSED' } });

    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'santos' } });
    await act(async () => { vi.advanceTimersByTime(400); });

    const searchCall = routerGet.mock.calls.find((call) => call[1]?.search === 'santos');
    expect(searchCall).toBeDefined();
    expect(searchCall[1]).toMatchObject({ search: 'santos', status: 'CLOSED' });
  });
});
