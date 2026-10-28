import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CaseIndex from '../Index.jsx';

const { routerGet, routerVisit } = vi.hoisted(() => ({ routerGet: vi.fn(), routerVisit: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  router: {
    get: routerGet,
    visit: routerVisit,
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
  routerVisit.mockClear();
  // The page persists its filters and restores them on mount. A previous test's
  // stored filters would restore themselves here and issue an extra request,
  // which is not what the test under way is about.
  localStorage.clear();
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

describe('Case Index approval column', () => {
  it('shows the final approver only after the case is approved', () => {
    const notApproved = renderIndex({
      cases: page({ data: [{ ...caseRow, reviewer: null }] }),
    });

    expect(screen.getByRole('columnheader', { name: /Approval/i })).toBeInTheDocument();
    expect(screen.queryByText('Approver Ana')).not.toBeInTheDocument();
    notApproved.unmount();

    renderIndex({
      cases: page({ data: [{ ...caseRow, reviewer: { name: 'Approver Ana' } }] }),
    });

    expect(screen.getByText('Approver Ana')).toBeInTheDocument();
  });
});

describe('Case Index register columns', () => {
  it('merges case number and tracking ID into one column', () => {
    renderIndex();

    expect(screen.getByRole('columnheader', { name: /Case \/ Tracking ID/i })).toBeInTheDocument();
    // Both identifiers render, and neither survives as its own column header.
    expect(screen.getByText('CASE-001')).toBeInTheDocument();
    expect(screen.getByText('TRK-001')).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Case Number$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Tracking ID$/ })).not.toBeInTheDocument();
  });

  it('merges client name with client type and age into one column', () => {
    renderIndex({
      cases: page({ data: [{ ...caseRow, client: { first_name: 'Maria', last_name: 'Santos', date_of_birth: '1990-01-01' } }] }),
    });

    expect(screen.getByRole('columnheader', { name: /^Client$/ })).toBeInTheDocument();
    expect(screen.getByText('Maria Santos')).toBeInTheDocument();
    // Secondary line carries type + derived age.
    expect(screen.getByText(/OFW · \d+ yrs?/)).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Client Name$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Client Type$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Age$/ })).not.toBeInTheDocument();
  });

  it('merges issue and category into one column', () => {
    renderIndex({
      cases: page({
        data: [{
          ...caseRow,
          case_issue: { id: 'i1', name: 'Unpaid final salary' },
          categories: [{ id: 'c1', name: 'Employment' }],
        }],
      }),
      categories: [{ id: 'c1', name: 'Employment' }],
    });

    expect(screen.getByRole('columnheader', { name: /Issue \/ Category/i })).toBeInTheDocument();
    expect(screen.getByText('Unpaid final salary')).toBeInTheDocument();
    expect(screen.getByText('Employment')).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Issue\/Concern$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Category$/ })).not.toBeInTheDocument();
  });

  it('keeps the register default to nine data columns plus actions', () => {
    renderIndex();

    // Address headers by accessible name: the sort glyph is aria-hidden, so it
    // never joins the name the way it joins textContent.
    const expectedHeaders = [
      'Case / Tracking ID',
      'Client',
      'Issue / Category',
      'Vulnerability',
      'Author',
      'Case Status',
      'Approval',
      'Referred To',
      'Latest Update',
      'Actions',
    ];

    for (const header of expectedHeaders) {
      expect(screen.getByRole('columnheader', { name: header })).toBeInTheDocument();
    }

    expect(screen.getByRole('columnheader', { name: /Date Created/i })).toBeInTheDocument();
    expect(screen.getAllByRole('columnheader')).toHaveLength(expectedHeaders.length + 1);
  });
});

describe('Case Index row actions', () => {
  it('replaces inline buttons with a labelled kebab per row', () => {
    renderIndex();

    expect(screen.getByRole('button', { name: /Actions for case CASE-001/i })).toBeInTheDocument();
    // The old inline action buttons are gone; the menu now owns them.
    expect(screen.queryByRole('button', { name: /^View$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Edit$/ })).not.toBeInTheDocument();
  });

  it('makes the case reference a keyboard-reachable link to the case', () => {
    renderIndex();

    const reference = screen.getByRole('button', { name: 'CASE-001' });
    fireEvent.click(reference);

    expect(routerVisit).toHaveBeenCalledTimes(1);
    expect(String(routerVisit.mock.calls[0][0])).toContain('/cases');
  });
});

describe('Case Index advanced filter panel', () => {
  function openFilters() {
    fireEvent.click(screen.getByRole('button', { name: /^Filters/ }));
  }
  it('presents the list filters as checkboxes rather than dropdowns', () => {
    renderIndex();
    openFilters();

    const panel = screen.getByRole('dialog', { name: /filters/i });
    expect(within(panel).getAllByRole('checkbox').length).toBeGreaterThan(0);

    // The only remaining select is Case Age, which the backend accepts as a
    // scalar integer only; every list filter is a checkbox group.
    const comboboxes = within(panel).getAllByRole('combobox');
    expect(comboboxes).toHaveLength(1);
    expect(comboboxes[0]).toHaveAccessibleName(/case age/i);
  });

  it('sends a multi-select status list when a second status is ticked', () => {
    // Filters come from server props, so the second tick is applied to a table
    // that already holds the first value.
    renderIndex({ filters: { status: 'OPEN' } });
    openFilters();

    const panel = screen.getByRole('dialog', { name: /filters/i });
    expect(within(panel).getByRole('checkbox', { name: 'Open' })).toBeChecked();
    fireEvent.click(within(panel).getByRole('checkbox', { name: 'Closed' }));

    const lastCall = routerGet.mock.calls[routerGet.mock.calls.length - 1];
    expect(lastCall[1]).toMatchObject({ status: ['OPEN', 'CLOSED'] });
  });

  it('collapses a two-value selection back to the scalar form when one is unticked', () => {
    renderIndex({ filters: { status: ['OPEN', 'CLOSED'] } });
    openFilters();

    const panel = screen.getByRole('dialog', { name: /filters/i });
    fireEvent.click(within(panel).getByRole('checkbox', { name: 'Closed' }));

    const lastCall = routerGet.mock.calls[routerGet.mock.calls.length - 1];
    expect(lastCall[1]).toMatchObject({ status: 'OPEN' });
  });

  it('clears a filter group by unticking its only value', () => {
    renderIndex({ filters: { vulnerability_indicator: 'PWD' } });
    openFilters();

    const panel = screen.getByRole('dialog', { name: /filters/i });
    fireEvent.click(within(panel).getByRole('checkbox', { name: 'PWD' }));

    const lastCall = routerGet.mock.calls[routerGet.mock.calls.length - 1];
    expect(lastCall[1]).not.toHaveProperty('vulnerability_indicator');
  });

  it('marks a multi-value selection as active in the filter panel', () => {
    renderIndex({ filters: { status: ['OPEN', 'CLOSED'] } });
    openFilters();

    // The active-filter chip strip was removed from the table, so the applied
    // state now lives in the panel: both values read as checked and the trigger
    // reports how many filters are active.
    const panel = screen.getByRole('dialog', { name: /filters/i });
    expect(within(panel).getByRole('checkbox', { name: 'Open' })).toBeChecked();
    expect(within(panel).getByRole('checkbox', { name: 'Closed' })).toBeChecked();
    expect(screen.getByRole('button', { name: /filters, 1 active/i })).toBeInTheDocument();
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

  // The pending-params capture is only meaningful if the search timer has NOT
  // already disarmed itself when the filter click records its params. When the
  // timer nulls its own id before calling updateTable, the click that lands
  // afterwards records nothing and the filter is silently dropped.
  it('keeps a filter clicked after an earlier search has already fired', async () => {
    renderIndex();

    // First search fires and completes its own navigation.
    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'maria' } });
    await act(async () => { vi.advanceTimersByTime(400); });
    expect(routerGet.mock.calls.some((call) => call[1]?.search === 'maria')).toBe(true);

    routerGet.mockClear();

    // Now type again, and click a filter inside the pending window.
    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'santos' } });
    fireEvent.click(screen.getByRole('button', { name: /^Open$/ }));
    await act(async () => { vi.advanceTimersByTime(400); });

    const searchCall = routerGet.mock.calls.find((call) => call[1]?.search === 'santos');
    expect(searchCall).toBeDefined();
    expect(searchCall[1]).toMatchObject({ search: 'santos', status: 'OPEN' });
  });

  // The filter click issues its OWN request before the search timer fires. The
  // timer must then reuse that request's params, not the props it rendered
  // with — otherwise a filter whose value only exists in the click is dropped
  // from the search navigation.
  it('reuses the params of a filter request issued during the debounce window', async () => {
    // The props say CLOSED, but the user clicks Open during the debounce window.
    renderIndex({ filters: { status: 'CLOSED' } });

    fireEvent.change(screen.getByPlaceholderText(/Search by tracking ID/), { target: { value: 'reyes' } });
    fireEvent.click(screen.getByRole('button', { name: /^Open$/ }));

    const filterCall = routerGet.mock.calls.find((call) => call[1]?.status === 'OPEN');
    expect(filterCall).toBeDefined();

    await act(async () => { vi.advanceTimersByTime(400); });

    const searchCall = routerGet.mock.calls.find((call) => call[1]?.search === 'reyes');
    expect(searchCall).toBeDefined();
    expect(searchCall[1]).toMatchObject({ search: 'reyes', status: 'OPEN' });
  });
});
