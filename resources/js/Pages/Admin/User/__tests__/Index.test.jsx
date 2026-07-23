import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import Index from '../Index.jsx';

const router = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router, usePage: () => ({ props: { auth: { user: { role: 'ADMIN' } } } }) }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useTableVisitLoading', () => ({ default: () => ({ isLoading: false, withLoading: (options) => options }) }));
vi.mock('@/Hooks/usePersistedColumns', () => ({ default: () => [['name', 'email', 'role', 'agency', 'email_verified', 'status', 'actions'], vi.fn()] }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: () => null }));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => null }));
vi.mock('@/Components/Admin/UserFormModal', () => ({ default: () => null }));
vi.mock('@/Components/PeerProfileModal', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({ RowContextMenu: () => null, RowContextMenuItem: () => null }));
vi.mock('lucide-react', () => ({ Users: () => null, UserCheck: () => null, Briefcase: () => null, Building2: () => null, Shield: () => null }));

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
    globalThis.route = vi.fn((name, id) => `/${name}/${id}`);
    window.history.replaceState({}, '', '/admin/users');
  });

  afterEach(() => vi.useRealTimers());

  it('sends the entered search and resets page after the debounce', () => {
    render(<Index {...props} />);
    fireEvent.change(screen.getByPlaceholderText('Search by name, email, or position...'), { target: { value: 'ada' } });

    act(() => vi.advanceTimersByTime(400));

    expect(router.get).toHaveBeenCalledTimes(1);
    expect(router.get.mock.calls[0][0]).toContain('search=ada');
    expect(router.get.mock.calls[0][0]).not.toContain('page=');
  });
});
