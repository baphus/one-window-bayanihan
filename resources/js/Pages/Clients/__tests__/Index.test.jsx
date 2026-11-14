import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ClientsIndex from '../Index.jsx';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  router: { get: routerGet, visit: vi.fn() },
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/UnifiedTable', () => ({
  UnifiedTable: ({ data, columns, emptyStateMessage, searchValue, searchPlaceholder, extraActions }) => (
    data.length === 0 ? <div>{emptyStateMessage}</div> : (
      <div>
        <input placeholder={searchPlaceholder} value={searchValue} readOnly />
        {extraActions}
        <table><tbody>{data.map((row) => (
          <tr key={`${row.kind}-${row.id}`}>
            {columns.map((column) => <td key={column.key}>{column.render(row)}</td>)}
          </tr>
        ))}</tbody></table>
      </div>
    )
  ),
}));
vi.mock('@/Components/Select', () => ({ default: ({ children, ...props }) => <select {...props}>{children}</select> }));
vi.mock('@/Hooks/useTableVisitLoading', () => ({ default: () => ({ isLoading: false, withLoading: (opts) => opts }) }));

function makeClient(overrides = {}) {
  return {
    kind: 'account', id: 'acc-1', name: 'Maria Santos', case_count: 2,
    open_case_count: 1, closed_case_count: 1, has_open: true, has_closed: true, latest_case_at: '2026-11-10T08:00:00Z',
    account_state: 'Active', current_age: 34, contact_number: '+63 912 345 6789', email: 'maria@example.com',
    address_city_municipality: 'Cebu City', address_province: 'Cebu',
    employment_country: 'Singapore', employment_status: 'Employed', show_url: '/clients/account/acc-1',
    ...overrides,
  };
}

function renderIndex(props = {}) {
  const defaults = {
    clients: { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0, per_page: 15 },
    kpis: { ofw_clients: 3, next_of_kin_clients: 2, vulnerable_clients: 1, returning_clients: 4 },
    filters: { search: '', status: 'Any' },
  };
  return render(<ClientsIndex {...defaults} {...props} />);
}

beforeEach(() => {
  globalThis.route = vi.fn((name) => name === 'clients.index' ? '/clients' : '/clients/account/acc-1');
  routerGet.mockClear();
});

describe('ClientsIndex', () => {
  it('renders header, source note, and empty state', () => {
    renderIndex();
    expect(screen.getByText('Clients')).toBeInTheDocument();
    expect(screen.getByText('Client information from most recent case; vulnerability indicators from eligible case history')).toBeInTheDocument();
    expect(screen.getByText('No clients found')).toBeInTheDocument();
  });

  it('shows core client information without account-link details', () => {
    renderIndex({ clients: { data: [makeClient()], current_page: 1, last_page: 1, from: 1, to: 1, total: 1, per_page: 15 } });
    expect(screen.getByLabelText('Age: 34')).toHaveTextContent('Age: 34');
    for (const text of ['Maria Santos', '+63 912 345 6789', 'maria@example.com', 'Cebu City, Cebu', 'Singapore', 'Employed']) {
      expect(screen.getByText(text)).toBeInTheDocument();
    }
    expect(screen.getByText('(1 open · 1 closed)')).toBeInTheDocument();
    expect(screen.getByText('—', { selector: 'span[aria-label="No vulnerability indicators"]' })).toBeInTheDocument();
    expect(screen.queryByText('Linked account')).not.toBeInTheDocument();
    expect(screen.queryByText('Unlinked case')).not.toBeInTheDocument();
    expect(screen.queryByText('ACTIVE')).not.toBeInTheDocument();
    expect(screen.queryByText('No account linked')).not.toBeInTheDocument();
  });

  it('shows a dash when current age is unknown', () => {
    renderIndex({ clients: { data: [makeClient({ current_age: null })], current_page: 1, last_page: 1, from: 1, to: 1, total: 1, per_page: 15 } });
    expect(screen.getByLabelText('Age: —')).toHaveTextContent('Age: —');
  });

  it('renders vulnerability indicators from case history as readable labels', () => {
    renderIndex({ clients: { data: [makeClient({ vulnerability_indicators: ['Senior Citizen', 'PWD'] })], current_page: 1, last_page: 1, from: 1, to: 1, total: 1, per_page: 15 } });
    expect(screen.getByRole('list', { name: 'Vulnerability indicators from case history' })).toHaveTextContent('Senior Citizen');
    expect(screen.getByRole('list', { name: 'Vulnerability indicators from case history' })).toHaveTextContent('PWD');
  });

  it('renders four KPI cards with their actual icons and counts', () => {
    renderIndex();
    for (const [label, value, icon] of [
      ['OFW Clients', '3', 'flight_takeoff'],
      ['Next of Kin Clients', '2', 'family_restroom'],
      ['Vulnerable Clients', '1', 'warning'],
      ['Returning Clients', '4', 'history'],
    ]) {
      const heading = screen.getByText(label);
      const card = heading.closest('article');
      expect(card).toHaveTextContent(value);
      expect(card.querySelector('.material-symbols-outlined')).toHaveTextContent(icon);
    }
    expect(screen.getByText('Can overlap with OFW clients')).toBeInTheDocument();
    expect(screen.getByText('Linked accounts with 2+ eligible cases')).toBeInTheDocument();
  });

  it('keeps status filter options and search control', () => {
    renderIndex({ clients: { data: [makeClient()], current_page: 1, last_page: 1, from: 1, to: 1, total: 1, per_page: 15 } });
    expect(screen.getByRole('combobox')).toHaveTextContent('Any');
    expect(screen.getByRole('combobox')).toHaveTextContent('Open');
    expect(screen.getByRole('combobox')).toHaveTextContent('Closed');
    expect(screen.getByRole('combobox')).toHaveTextContent('Both');
    expect(screen.getByPlaceholderText('Search by name, email or case number...')).toBeInTheDocument();
  });
});
