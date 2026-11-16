import '@testing-library/jest-dom/vitest';
import { StrictMode } from 'react';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CaseIndex from '../Index.jsx';

const { routerGet, routerVisit, routerPost, toastError, pageState } = vi.hoisted(() => ({ routerGet: vi.fn(), routerVisit: vi.fn(), routerPost: vi.fn(), toastError: vi.fn(), pageState: { role: 'CASE_MANAGER' } }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  router: {
    get: routerGet,
    visit: routerVisit,
    patch: vi.fn(),
    post: routerPost,
    delete: vi.fn(),
    on: () => () => {},
  },
  usePage: () => ({
    props: {
      auth: { user: { role: pageState.role, id: 'u1' } },
      roles: { ADMIN: 'ADMIN', AGENCY: 'AGENCY', CASE_MANAGER: 'CASE_MANAGER', OFW: 'OFW' },
    },
  }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Components/ExportDialog', () => ({ default: ({ countUrlBuilder }) => <output data-testid="case-export-count-url">{countUrlBuilder('2026-01-01', '2026-01-31')}</output> }));
vi.mock('@/Components/ExportExcelButton', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', async (importOriginal) => ({
  ...await importOriginal(),
  RowContextMenu: ({ children }) => <div>{children}</div>,
}));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: ({ open, onConfirm, onCancel }) => open ? <div><button onClick={onConfirm}>Confirm archive</button><button onClick={onCancel}>Cancel archive</button></div> : null }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: toastError, info: vi.fn() }) }));

const caseRow = {
  id: 'case-1',
  case_number: 'CASE-001',
  tracker_number: 'TRK-001',
  client_type: 'OFW',
  status: 'OPEN',
  vulnerability_indicator: 'None',
  created_at: '2026-01-05T00:00:00Z',
  first_name: 'Maria',
  last_name: 'Santos',
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
      {...props}
    />,
  );
}

it('includes selected case categories in export count query', () => {
  renderIndex({ filters: { category_ids: ['cat-a', 'cat-b'], status: ['ARCHIVED'] } });
  const url = new URL(screen.getByTestId('case-export-count-url').textContent, 'http://localhost');
  expect(url.searchParams.getAll('category_ids[]')).toEqual(['cat-a', 'cat-b']);
  expect(url.searchParams.getAll('status[]')).toEqual(['ARCHIVED']);
});

function lastVisitOptions() {
  return routerGet.mock.calls[routerGet.mock.calls.length - 1][2];
}

beforeEach(() => {
  globalThis.route = vi.fn((name, params) => (params ? `/cases?${new URLSearchParams(params)}` : '/cases'));
  routerGet.mockReset();
  routerVisit.mockClear();
  routerPost.mockClear();
  pageState.role = 'CASE_MANAGER';
  toastError.mockClear();
  // The page persists its filters and restores them on mount. A previous test's
  // stored filters would restore themselves here and issue an extra request,
  // which is not what the test under way is about.
  localStorage.clear();
  vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
  vi.useRealTimers();
});

it('opens selected pending review from both actions and keeps View for other cases', () => {
  const pending = { ...caseRow, id: 'case-2', case_number: 'CASE-002', status: 'PENDING_REVIEW' };
  renderIndex({ cases: page({ data: [caseRow, pending] }) });
  fireEvent.click(screen.getByRole('button', { name: 'Actions for case CASE-002' }));
  fireEvent.click(screen.getByRole('menuitem', { name: 'Review' }));
  expect(route).toHaveBeenCalledWith('cases.review-pending', { review_case: 'case-2' });
  fireEvent.click(screen.getByRole('button', { name: 'CASE-002' }));
  expect(route).toHaveBeenCalledWith('cases.review-pending', { review_case: 'case-2' });
  fireEvent.click(screen.getByRole('button', { name: 'Actions for case CASE-001' }));
  fireEvent.click(screen.getByRole('menuitem', { name: 'View' }));
  expect(route).toHaveBeenCalledWith('cases.show', 'case-1');
});

it.each(['CASE_MANAGER', 'ADMIN'])('confirms archive for eligible cases as %s', (role) => {
  pageState.role = role;
  renderIndex({ cases: page({ data: [{ ...caseRow, status: 'CLOSED', referrals: [{ status: 'COMPLETED' }] }] }) });
  fireEvent.click(screen.getByRole('button', { name: 'Actions for case CASE-001' }));
  fireEvent.click(screen.getByRole('menuitem', { name: 'Archive' }));
  expect(routerPost).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole('button', { name: 'Confirm archive' }));
  expect(route).toHaveBeenCalledWith('cases.archive', 'case-1');
  expect(routerPost).toHaveBeenCalledWith(expect.any(String), {}, { preserveScroll: true });
});

it.each([
  ['CASE_MANAGER', 'OPEN', []],
  ['CASE_MANAGER', 'ARCHIVED', []],
  ['AGENCY', 'CLOSED', []],
])('hides archive for %s with status %s and referrals %j', (role, status, referrals) => {
  pageState.role = role;
  renderIndex({ cases: page({ data: [{ ...caseRow, status, referrals }] }) });
  fireEvent.click(screen.getByRole('button', { name: 'Actions for case CASE-001' }));
  expect(screen.queryByRole('menuitem', { name: 'Archive' })).not.toBeInTheDocument();
});

it.each(['CLOSED', 'REJECTED'])('explains blocked archive for %s cases', (status) => {
  renderIndex({ cases: page({ data: [{ ...caseRow, status, referrals: [{ status: 'PENDING' }] }] }) });
  fireEvent.click(screen.getByRole('button', { name: 'Actions for case CASE-001' }));
  const archive = screen.getByRole('menuitem', { name: 'Archive' });
  expect(archive).toHaveAttribute('aria-disabled', 'true');
  expect(archive).toHaveAccessibleDescription('Cannot archive while referrals are still active.');
  const tooltip = screen.getByRole('tooltip', { hidden: true });
  expect(tooltip).toHaveClass('group-hover:block', 'group-focus-within:block');
  archive.focus();
  expect(archive).toHaveFocus();
  fireEvent.click(archive);
  expect(routerPost).not.toHaveBeenCalled();
  expect(screen.queryByRole('button', { name: 'Confirm archive' })).not.toBeInTheDocument();
});

it('allows archiving a rejected case with rejected referrals', () => {
  renderIndex({ cases: page({ data: [{ ...caseRow, status: 'REJECTED', referrals: [{ status: 'REJECTED' }] }] }) });
  fireEvent.click(screen.getByRole('button', { name: 'Actions for case CASE-001' }));
  fireEvent.click(screen.getByRole('menuitem', { name: 'Archive' }));
  fireEvent.click(screen.getByRole('button', { name: 'Confirm archive' }));
  expect(route).toHaveBeenCalledWith('cases.archive', 'case-1');
  expect(routerPost).toHaveBeenCalled();
});

it('leaves filter validation toasts to the shared watcher without duplicating them', () => {
  renderIndex();
  fireEvent.click(screen.getByRole('button', { name: 'Closed' }));
  lastVisitOptions().onError({ status: 'The selected status is invalid.' });
  expect(toastError).not.toHaveBeenCalled();
});

describe('Case Index table loading', () => {
  it('shows skeleton and blocks repeated filters during a StrictMode visit', async () => {
    routerGet.mockImplementation((_url, _params, options) => options.onStart({}));
    const { container } = render(<StrictMode><CaseIndex cases={page()} filters={{}} stats={{}} categories={[{ id: 'cat-a', name: 'Employment' }]} /></StrictMode>);
    fireEvent.click(screen.getByRole('button', { name: 'Closed' }));
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'true');
    expect(screen.queryByText('CASE-001')).not.toBeInTheDocument();
    expect(container.querySelectorAll('tbody tr.animate-pulse')).toHaveLength(5);
    fireEvent.click(screen.getByRole('button', { name: 'Open' }));
    expect(routerGet).toHaveBeenCalledTimes(1);
    await act(async () => { lastVisitOptions().onFinish({}); });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'false');
    routerGet.mockReset();
  });
  // Behaviour that must survive the move to the shared hook: a visit marks the
  // table busy and the end of that visit clears it. The hook owns loading via
  // the onStart/onFinish/onCancel callbacks it injects into router.get.
  it.each(['finish', 'error', 'cancel'])('clears skeleton and unlocks category filters after %s', async (outcome) => {
    routerGet.mockImplementation((_url, _params, options) => options.onStart({}));
    const { container } = renderIndex({ categories: [{ id: 'cat-a', name: 'Employment' }] });
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));
    const checkbox = within(screen.getByRole('dialog', { name: /filters/i })).getByRole('checkbox', { name: 'Employment' });
    fireEvent.click(checkbox);
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'true');
    expect(screen.queryByText('CASE-001')).not.toBeInTheDocument();
    expect(container.querySelectorAll('tbody tr.animate-pulse')).toHaveLength(5);
    expect(checkbox).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Open' })).toBeDisabled();
    const options = lastVisitOptions();
    await act(async () => {
      if (outcome === 'error') options.onError({ category_ids: 'Invalid category' });
      if (outcome === 'cancel') options.onCancel({});
      else options.onFinish({});
    });
    expect(container.querySelector('[aria-busy]')).toHaveAttribute('aria-busy', 'false');
    expect(checkbox).toBeEnabled();
    expect(container.querySelectorAll('tbody tr.animate-pulse')).toHaveLength(0);
    expect(screen.getByText('CASE-001')).toBeVisible();
  });

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

  it('merges OFW name with client type and age into one column', () => {
    renderIndex({
      cases: page({ data: [{ ...caseRow, first_name: 'Maria', last_name: 'Santos', date_of_birth: '1990-01-01', age_at_intake: 35 }] }),
    });

    expect(screen.getByRole('columnheader', { name: /^OFW$/ })).toBeInTheDocument();
    expect(screen.getByText('Maria Santos')).toBeInTheDocument();
    // Secondary line carries type + age at intake.
    expect(screen.getByText(/OFW · 35/)).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^OFW Name$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Client Type$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^Age at intake$/ })).not.toBeInTheDocument();
  });

  it('shows case-owned categories without case-wide issue text', () => {
    renderIndex({
      cases: page({
        data: [{
          ...caseRow,
          categories: [{ id: 'c1', name: 'Employment' }],
        }],
      }),
    });

    expect(screen.getByRole('columnheader', { name: /^Categories$/ })).toBeInTheDocument();
    expect(screen.getByText('Employment')).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /Issue/i })).not.toBeInTheDocument();
  });

  it('keeps the register default to nine data columns plus actions', () => {
    renderIndex();

    // Address headers by accessible name: the sort glyph is aria-hidden, so it
    // never joins the name the way it joins textContent.
    const expectedHeaders = [
      'Case / Tracking ID',
      'OFW',
      'Categories',
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

  it('opens the existing review queue for a pending case', () => {
    renderIndex({ cases: page({ data: [{ ...caseRow, status: 'PENDING_REVIEW' }] }) });
    fireEvent.click(screen.getByRole('button', { name: 'CASE-001' }));
    expect(globalThis.route).toHaveBeenCalledWith('cases.review-pending', { review_case: 'case-1' });
  });

  it('makes the case reference a keyboard-reachable link to the case', () => {
    renderIndex();

    const reference = screen.getByRole('button', { name: 'CASE-001' });
    fireEvent.click(reference);

    expect(routerVisit).toHaveBeenCalledTimes(1);
    expect(String(routerVisit.mock.calls[0][0])).toContain('/cases');
  });
});

describe('Case Index quick status tabs', () => {
  it.each([['Open', 'OPEN'], ['Closed', 'CLOSED'], ['Archived', 'ARCHIVED'], ['Pending Review', 'PENDING_REVIEW']])('selects %s after the server echoes an array status', (label, status) => {
    const { rerender } = renderIndex();
    fireEvent.click(screen.getByRole('button', { name: label }));
    expect(routerGet.mock.calls.at(-1)[1]).toMatchObject({ status });
    rerender(<CaseIndex cases={page({ data: [{ ...caseRow, status }] })} filters={{ status: [status] }} stats={{}} />);
    expect(screen.getByRole('button', { name: label })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByText(status)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'All' }));
    expect(routerGet.mock.calls.at(-1)[1]).not.toHaveProperty('status');
  });

  it('offers Pending Review in the advanced filters', () => {
    renderIndex({ filters: { status: ['PENDING_REVIEW'] } });
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));
    expect(within(screen.getByRole('dialog', { name: /filters/i })).getByRole('checkbox', { name: 'Pending Review' })).toBeChecked();
  });
  it('shows Declined count and filters cases by REJECTED', () => {
    renderIndex({ stats: { declined_cases: 3 } });

    const declinedTab = screen.getByRole('button', { name: 'Declined' });
    expect(declinedTab).toHaveTextContent('3');
    expect(declinedTab).toHaveAttribute('aria-pressed', 'false');
    fireEvent.click(declinedTab);

    expect(routerGet.mock.calls.at(-1)[1]).toMatchObject({ status: 'REJECTED' });
  });
});

describe('Case Index advanced filter panel', () => {
  it('offers categories and combines selected categories with other filters', () => {
    renderIndex({ categories: [{ id: 'cat-a', name: 'Employment' }, { id: 'cat-b', name: 'Welfare' }], filters: { status: ['OPEN'], category_ids: ['cat-a'] } });
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));
    const panel = screen.getByRole('dialog', { name: /filters/i });
    expect(within(panel).getByRole('checkbox', { name: 'Employment' })).toBeChecked();
    fireEvent.click(within(panel).getByRole('checkbox', { name: 'Welfare' }));
    expect(routerGet.mock.calls.at(-1)[1]).toMatchObject({ status: ['OPEN'], category_ids: ['cat-a', 'cat-b'] });
  });
  it('replaces legacy category_id when toggling categories and clears the selection', () => {
    renderIndex({ categories: [{ id: 'cat-a', name: 'Employment' }], filters: { category_id: 'cat-a', category_ids: ['cat-a'] } });
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));
    const panel = screen.getByRole('dialog', { name: /filters/i });
    fireEvent.click(within(panel).getByRole('checkbox', { name: 'Employment' }));
    expect(routerGet.mock.calls.at(-1)[1]).not.toHaveProperty('category_id');
    expect(routerGet.mock.calls.at(-1)[1]).not.toHaveProperty('category_ids');
    fireEvent.click(within(panel).getByRole('button', { name: 'Clear all' }));
    expect(routerGet.mock.calls.at(-1)[1]).not.toHaveProperty('category_id');
    expect(routerGet.mock.calls.at(-1)[1]).not.toHaveProperty('category_ids');
  });

  it('offers Declined status filter even when status comes from local options', () => {
    renderIndex();
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));
    const panel = screen.getByRole('dialog', { name: /filters/i });
    expect(within(panel).getByRole('checkbox', { name: 'Declined' })).toBeInTheDocument();
  });
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
