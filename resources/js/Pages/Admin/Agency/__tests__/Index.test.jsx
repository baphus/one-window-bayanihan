import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import Index from '../Index.jsx';

const router = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router, usePage: () => ({ props: { auth: { user: { role: 'ADMIN' } } } }) }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useTableVisitLoading', () => ({ default: () => ({ isLoading: false, withLoading: (options) => options }) }));
vi.mock('@/Hooks/usePersistedColumns', () => ({ default: () => [['name', 'short', 'is_default', 'referrals_count', 'is_active', 'actions'], vi.fn()] }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: () => null }));
vi.mock('@/Components/Admin/AgencyFormModal', () => ({ default: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({ RowContextMenu: () => null, RowContextMenuItem: () => null }));
vi.mock('lucide-react', () => ({ Building2: () => null, Users: () => null, CheckCircle: () => null, XCircle: () => null, Shield: () => null, MapPin: () => null, Phone: () => null }));

const props = {
  agencies: { data: [{ id: 'agency-1', name: 'DMW', short: 'DMW', is_default: false, is_active: true, referrals_count: 0 }], total: 1, from: 1, to: 1, current_page: 1, last_page: 1, per_page: 10 },
  filters: {},
  stats: { total: 1, active: 1, inactive: 0, default: 0 },
};

describe('Admin agency search requests', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    router.get.mockClear();
    globalThis.route = vi.fn((name, id) => `/${name}/${id}`);
    window.history.replaceState({}, '', '/admin/agencies');
  });

  afterEach(() => vi.useRealTimers());

  it('sends the entered search and resets page after the debounce', () => {
    render(<Index {...props} />);
    fireEvent.change(screen.getByPlaceholderText('Search by name, short code, or description...'), { target: { value: 'health' } });

    act(() => vi.advanceTimersByTime(400));

    expect(router.get).toHaveBeenCalledTimes(1);
    expect(router.get.mock.calls[0][0]).toContain('search=health');
    expect(router.get.mock.calls[0][0]).not.toContain('page=');
  });
});
