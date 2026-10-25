import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import Index from '../Index.jsx';

const router = vi.hoisted(() => ({ get: vi.fn() }));
const UserFormModalMock = vi.hoisted(() => ({ default: vi.fn(() => null) }));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router, usePage: () => ({ props: { auth: { user: { role: 'ADMIN' } }, roles: { ADMIN: 'ADMIN', AGENCY: 'AGENCY', CASE_MANAGER: 'CASE_MANAGER', OFW: 'OFW' } } }) }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useTableVisitLoading', () => ({ default: () => ({ isLoading: false, withLoading: (options) => options }) }));
vi.mock('@/Hooks/usePersistedColumns', () => ({ default: () => [['name', 'email', 'role', 'agency', 'email_verified', 'status', 'actions'], vi.fn()] }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: () => null }));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => null }));
vi.mock('@/Components/Admin/UserFormModal', () => UserFormModalMock);
vi.mock('@/Components/PeerProfileModal', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({ RowContextMenu: () => null, RowContextMenuItem: () => null }));
vi.mock('lucide-react', () => ({ Users: () => null, UserCheck: () => null, Briefcase: () => null, Building2: () => null, Shield: () => null, Plane: () => null }));

const props = {
  users: { data: [{ id: 'user-1', name: 'Ada User', email: 'ada@example.test', role: 'ADMIN', agency: null, email_verified_at: null, is_active: true }], total: 1, from: 1, to: 1, current_page: 1, last_page: 1, per_page: 10 },
  filters: {},
  stats: { total: 1, active: 1, case_managers: 0, agency_focals: 0, admins: 1 },
  agencies: [],
  pendingInvites: [],
};

describe('Admin user search requests', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    router.get.mockClear();
    UserFormModalMock.default.mockClear();
    globalThis.route = vi.fn((name, id) => `/${name}/${id}`);
    window.history.replaceState({}, '', '/admin/users');
  });

  afterEach(() => vi.useRealTimers());

  it('sends the entered search and resets page after the debounce', () => {
    render(<Index {...props} />);
    fireEvent.change(screen.getByPlaceholderText('Search by name, email, or occupation...'), { target: { value: 'ada' } });

    act(() => vi.advanceTimersByTime(400));

    expect(router.get).toHaveBeenCalledTimes(1);
    expect(router.get.mock.calls[0][0]).toContain('search=ada');
    expect(router.get.mock.calls[0][0]).not.toContain('page=');
  });

  it('opens the create-user modal when arriving with ?open=create and cleans the URL', () => {
    window.history.replaceState({}, '', '/admin/users?open=create');
    render(<Index {...props} />);

    expect(UserFormModalMock.default).toHaveBeenCalledTimes(1);
    expect(UserFormModalMock.default.mock.calls[0][0].initialMode).toBe('create');
    expect(window.location.search).toBe('');
  });
});

describe('Citizen accounts in the users list', () => {
  beforeEach(() => {
    router.get.mockClear();
    UserFormModalMock.default.mockClear();
    globalThis.route = vi.fn((name, id) => `/${name}/${id}`);
    window.history.replaceState({}, '', '/admin/users');
  });

  const citizen = (id, name, type) => ({
    id,
    name,
    email: `${id}@example.test`,
    role: 'OFW',
    agency: null,
    email_verified_at: null,
    is_active: true,
    client: { id: `client-${id}`, type, first_name: name.split(' ')[0], last_name: name.split(' ')[1], ofw_client: null },
  });

  it('offers the Citizen role and Client Type filters', () => {
    render(<Index {...props} />);

    // The advanced filter panel only mounts once its toggle is clicked.
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));

    expect(screen.getByRole('option', { name: 'Citizen' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Next of Kin' })).toBeInTheDocument();
  });

  it('labels a next-of-kin account as NOK and keeps them out of the edit modal', () => {
    render(
      <Index
        {...props}
        users={{ ...props.users, data: [citizen('u1', 'Nora Kin', 'NEXT_OF_KIN')] }}
      />,
    );

    // Role column shows the account role, the Client column carries the split.
    expect(screen.getByText('Citizen')).toBeInTheDocument();
    expect(screen.queryByText('Edit')).not.toBeInTheDocument();
    expect(screen.getByText('View')).toBeInTheDocument();
  });

  it('renders the client-type split in the stat tiles', () => {
    render(<Index {...props} stats={{ ...props.stats, citizen_ofw: 4, citizen_nok: 2 }} />);

    expect(screen.getByText('Client (OFW)')).toBeInTheDocument();
    expect(screen.getByText('Client (NOK)')).toBeInTheDocument();
    expect(screen.getByText('4')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });
});
