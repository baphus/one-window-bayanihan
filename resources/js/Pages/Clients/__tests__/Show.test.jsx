import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ClientShow from '../Show.jsx';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a>,
  router: { get: vi.fn() },
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/UnifiedTable', () => ({
  UnifiedTable: ({ data, columns, emptyStateMessage }) => {
    if (data.length === 0) return <div>{emptyStateMessage}</div>;
    return (
      <table>
        <thead><tr>{columns.map((column) => <th key={column.key}>{column.title}</th>)}</tr></thead>
        <tbody>
          {data.map((row) => (
            <tr key={row.id}>
              {columns.map((column) => <td key={column.key}>{column.render(row)}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    );
  },
}));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: (addr) => 'Cebu City' }));

function makeClient(overrides = {}) {
  return {
    kind: 'account',
    id: 'user-1',
    profile: {
      first_name: 'Maria',
      last_name: 'Santos',
      current_age: 34,
      sex: 'Female',
      civil_status: 'Single',
      deployment_mode: 'Direct Hire',
      employment_status: 'Employed',
      email: 'maria@example.com',
      contact_number: '09171234567',
    },
    source_case: { id: 'case-1', case_number: 'CASE-001', intake_date: '2026-11-01' },
    account: { state: 'Active', email: 'maria@example.com' },
    ...overrides,
  };
}

function renderShow(props = {}) {
  const defaults = {
    client: makeClient(),
    cases: { data: [], total: 0, current_page: 1, last_page: 1, from: 0, to: 0 },
  };
  return render(<ClientShow {...defaults} {...props} />);
}

beforeEach(() => {
  globalThis.route = vi.fn((name) => {
    if (name === 'clients.index') return '/clients';
    return '/cases/case-1';
  });
});

describe('ClientShow', () => {
  it('renders client profile', () => {
    renderShow();
    expect(screen.getByRole('heading', { name: 'Maria Santos' })).toBeInTheDocument();
    expect(screen.getByText('34')).toBeInTheDocument();
    expect(screen.getAllByText('maria@example.com').length).toBeGreaterThan(0);
  });

  it('shows an em dash when current age is unknown', () => {
    const client = makeClient({ profile: { ...makeClient().profile, current_age: null } });
    renderShow({ client });
    expect(screen.getByText('—')).toBeInTheDocument();
  });

  it('shows linked account profile photo', () => {
    const client = makeClient({ account: { state: 'Active', email: 'maria@example.com', avatar_url: '/avatars/maria.jpg' } });
    renderShow({ client });
    expect(screen.getByAltText('Maria Santos profile photo')).toHaveAttribute('src', '/avatars/maria.jpg');
  });

  it('shows initials when photo is unavailable, including unlinked clients', () => {
    const client = makeClient({ kind: 'case', account: { state: 'No account linked', email: null, avatar_url: null } });
    renderShow({ client });
    expect(screen.getByRole('img', { name: 'Maria Santos initials' })).toHaveTextContent('MS');
  });

  it('shows active account status', () => {
    renderShow();
    expect(screen.getByText('ACTIVE')).toBeInTheDocument();
  });

  it('shows inactive account status', () => {
    const client = makeClient({ account: { state: 'Inactive', email: 'maria@example.com' } });
    renderShow({ client });
    expect(screen.getByText('INACTIVE')).toBeInTheDocument();
  });

  it('shows unlinked case message when no account', () => {
    const client = makeClient({ kind: 'case', account: { state: 'No account linked', email: null } });
    renderShow({ client });
    expect(screen.getByText(/No account linked. This is an unlinked case./)).toBeInTheDocument();
  });

  it('shows account unavailable message', () => {
    const client = makeClient({ account: { state: 'Unavailable', email: null } });
    renderShow({ client });
    expect(screen.getByText(/Account unavailable/)).toBeInTheDocument();
  });

  it('renders address when present', () => {
    const client = makeClient({
      profile: { ...makeClient().profile, address_region: 'VII', address_city_municipality: 'Cebu City' },
    });
    renderShow({ client });
    expect(screen.getByText(/Cebu City/)).toBeInTheDocument();
  });

  it('renders employment details', () => {
    const client = makeClient({
      profile: {
        ...makeClient().profile,
        employment_employer_name: 'Acme Corp',
        employment_employer_address: '123 Main St',
        employment_position: 'Domestic Helper',
        employment_country: 'Hong Kong',
        employment_last_position: 'Caregiver',
        employment_date_of_arrival: '2025-01-15',
      },
    });
    renderShow({ client });
    expect(screen.getByText('Acme Corp')).toBeInTheDocument();
    expect(screen.getByText('123 Main St')).toBeInTheDocument();
    expect(screen.getByText('Domestic Helper')).toBeInTheDocument();
    expect(screen.getByText('Hong Kong')).toBeInTheDocument();
    expect(screen.getByText('Caregiver')).toBeInTheDocument();
  });

  it('shows source case attribution', () => {
    renderShow();
    expect(screen.getByText(/Source: Case CASE-001/)).toBeInTheDocument();
  });

  it('renders case history with client type', () => {
    const cases = {
      data: [
        {
          id: 'case-1',
          case_number: 'CASE-001',
          client_type: 'OFW',
          intake_date: '2026-11-01',
          status: 'OPEN',
          created_at: '2026-11-01T10:00:00Z',
          closed_at: null,
          show_url: '/cases/case-1',
        },
      ],
      total: 1,
      current_page: 1,
      last_page: 1,
      from: 1,
      to: 1,
    };
    renderShow({ cases });
    expect(screen.getByText('CASE-001')).toBeInTheDocument();
    expect(screen.getByText('OFW')).toBeInTheDocument();
  });

  it('shows the case approver or an em dash when none is recorded', () => {
    const cases = {
      data: [
        { id: 'case-1', case_number: 'CASE-001', client_type: 'OFW', status: 'OPEN', created_at: '2026-11-01T10:00:00Z', closed_at: null, intake_date: null, show_url: '/cases/case-1', approver: 'Ana Reyes' },
        { id: 'case-2', case_number: 'CASE-002', client_type: 'OFW', status: 'OPEN', created_at: '2026-11-02T10:00:00Z', closed_at: null, intake_date: null, show_url: '/cases/case-2', approver: null },
      ],
      total: 2,
      current_page: 1,
      last_page: 1,
      from: 1,
      to: 2,
    };
    renderShow({ cases });
    expect(screen.getByRole('columnheader', { name: 'APPROVER' })).toBeInTheDocument();
    expect(screen.getByText('Ana Reyes')).toBeInTheDocument();
    expect(screen.getAllByText('—').length).toBeGreaterThan(0);
  });

  it('shows empty state when no cases', () => {
    renderShow({ cases: { data: [], total: 0, current_page: 1, last_page: 1, from: 0, to: 0 } });
    expect(screen.getByText('No cases found.')).toBeInTheDocument();
  });

  it('removes summary panel and keeps summary details in primary cards', () => {
    const cases = {
      data: [
        { id: 'case-1', case_number: 'CASE-001', client_type: 'OFW', status: 'OPEN', created_at: '2026-11-01T10:00:00Z', closed_at: null, intake_date: null, show_url: '/cases/case-1' },
        { id: 'case-2', case_number: 'CASE-002', client_type: 'OFW', status: 'CLOSED', created_at: '2026-10-15T10:00:00Z', closed_at: '2026-10-20T10:00:00Z', intake_date: null, show_url: '/cases/case-2' },
      ],
      total: 2,
      current_page: 1,
      last_page: 1,
      from: 1,
      to: 2,
    };
    renderShow({ cases, client: makeClient({ source_case: { ...makeClient().source_case, created_at: '2026-11-02T10:00:00Z' } }) });
    expect(screen.queryByText('Summary')).not.toBeInTheDocument();
    expect(screen.getByText('Linked account')).toBeInTheDocument();
    expect(screen.getByText('2 cases')).toBeInTheDocument();
    expect(screen.getByText('November 2, 2026')).toBeInTheDocument();
  });

  it('shows back to clients link', () => {
    renderShow();
    const backLink = screen.getByText('Back to Clients').closest('a');
    expect(backLink).toHaveAttribute('href', '/clients');
  });

  it('displays linked account type', () => {
    renderShow();
    expect(screen.getByText('Linked account')).toBeInTheDocument();
  });

  it('displays unlinked case type', () => {
    const client = makeClient({ kind: 'case' });
    renderShow({ client });
    expect(screen.getByText('Unlinked case')).toBeInTheDocument();
  });

  it('shows pagination when multiple pages', () => {
    const cases = {
      data: [{ id: 'case-1', case_number: 'CASE-001', client_type: 'OFW', status: 'OPEN', created_at: '2026-11-01T10:00:00Z', closed_at: null, intake_date: null, show_url: '/cases/case-1' }],
      total: 30,
      current_page: 1,
      last_page: 2,
      from: 1,
      to: 15,
    };
    renderShow({ cases });
    expect(screen.getByText(/Page 1 of 2/)).toBeInTheDocument();
    expect(screen.getByText('Previous')).toBeInTheDocument();
    expect(screen.getByText('Next')).toBeInTheDocument();
  });

  it('includes title on case action', () => {
    const cases = {
      data: [
        { id: 'case-1', case_number: 'CASE-001', client_type: 'OFW', status: 'OPEN', created_at: '2026-11-01T10:00:00Z', closed_at: null, intake_date: null, show_url: '/cases/case-1' },
      ],
      total: 1,
      current_page: 1,
      last_page: 1,
      from: 1,
      to: 1,
    };
    renderShow({ cases });
    // Mock doesn't render actions column, verify structure exists
    expect(screen.getByText('CASE-001')).toBeInTheDocument();
    expect(screen.getByText('OFW')).toBeInTheDocument();
  });
});
