import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup } from '@testing-library/react';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }) => <a href={href}>{children}</a>,
  router: { get: vi.fn(), visit: vi.fn(), post: vi.fn() },
  usePage: () => ({ props: { auth: { user: { id: 'u-1', role: 'ADMIN', name: 'Admin User' } } }, url: '/cases/trash' }),
  useForm: () => ({ processing: false, post: vi.fn(), data: {}, setData: vi.fn(), errors: {} }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({
  default: ({ open, title, message, onConfirm, onCancel, confirmLabel }) => open ? (
    <div data-testid="confirm-dialog">
      <p>{title}</p>
      <p>{message}</p>
      <button onClick={onConfirm}>{confirmLabel}</button>
      <button onClick={onCancel}>Cancel</button>
    </div>
  ) : null,
}));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ui/TableLoadingOverlay', () => ({ default: () => null }));
vi.mock('@/Hooks/useTableVisitLoading', () => ({ default: () => ({ isLoading: false, withLoading: (opts) => opts }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/lib/utils', () => ({
  formatDisplayDate: (d) => d,
  formatDisplayDateTime: (d) => d,
}));

// Global route() helper
globalThis.route = (name, params) => `/mocked/${name}/${params || ''}`;

import CaseTrash from '../Trash';

afterEach(cleanup);

const emptyCases = { data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0 };

const casesWithData = {
  data: [
    {
      id: 'case-1',
      case_number: 'CASE-20260724-0001',
      client: { first_name: 'Juan', last_name: 'Dela Cruz' },
      user: { name: 'Admin User' },
      deleted_at: '2026-07-20T00:00:00.000Z',
      deletion_reason: 'Duplicate record — resolved on another case',
    },
    {
      id: 'case-2',
      case_number: 'CASE-20260724-0002',
      client: { first_name: 'Maria', last_name: 'Santos' },
      user: { name: 'Case Manager' },
      deleted_at: '2026-07-22T00:00:00.000Z',
      deletion_reason: 'Incorrect entry — data migrated to proper case',
    },
  ],
  current_page: 1,
  last_page: 1,
  from: 1,
  to: 2,
  total: 2,
};

describe('Case/Trash page', () => {
  it('renders empty state when no trashed cases', () => {
    render(<CaseTrash cases={emptyCases} />);
    expect(screen.getByText('Trash is Empty')).toBeInTheDocument();
  });

  it('renders table with trashed cases', () => {
    render(<CaseTrash cases={casesWithData} />);
    expect(screen.getByText('CASE-20260724-0001')).toBeInTheDocument();
    expect(screen.getByText('Juan Dela Cruz')).toBeInTheDocument();
    expect(screen.getByText('CASE-20260724-0002')).toBeInTheDocument();
    expect(screen.getByText('Maria Santos')).toBeInTheDocument();
  });

  it('shows deletion reason in table', () => {
    render(<CaseTrash cases={casesWithData} />);
    expect(screen.getByText(/Duplicate record/)).toBeInTheDocument();
    expect(screen.getByText(/Incorrect entry/)).toBeInTheDocument();
  });

  it('shows restore buttons for each case', () => {
    render(<CaseTrash cases={casesWithData} />);
    const restoreButtons = screen.getAllByText('Restore');
    expect(restoreButtons).toHaveLength(2);
  });

  it('opens confirm dialog when restore is clicked', () => {
    render(<CaseTrash cases={casesWithData} />);
    const restoreButtons = screen.getAllByText('Restore');
    fireEvent.click(restoreButtons[0]);
    expect(screen.getByTestId('confirm-dialog')).toBeInTheDocument();
    expect(screen.getByText(/Restore Case/)).toBeInTheDocument();
  });

  it('has search input', () => {
    render(<CaseTrash cases={emptyCases} />);
    expect(screen.getByPlaceholderText(/Search by client name or case number/)).toBeInTheDocument();
  });

  it('shows page title', () => {
    render(<CaseTrash cases={emptyCases} />);
    expect(screen.getByText('Case Trash')).toBeInTheDocument();
  });
});
