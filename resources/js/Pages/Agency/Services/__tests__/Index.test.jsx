import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Index from '../Index.jsx';

const router = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router, usePage: () => ({ props: { auth: { user: { role: 'AGENCY' } } } }) }));
vi.mock('@/Components/ui/KpiCard', () => ({ default: () => null }));
vi.mock('@/Components/ui/UnifiedTable', () => ({ UnifiedTable: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({ RowContextMenu: () => null, RowContextMenuItem: () => null }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));

const props = { services: [], allServices: [] };

describe('Agency services page', () => {
  beforeEach(() => {
    router.get.mockClear();
    globalThis.route = vi.fn((name, id) => `/${name}/${id}`);
    window.history.replaceState({}, '', '/agency/services');
  });

  it('opens the create-service modal when arriving with ?open=create and cleans the URL', () => {
    window.history.replaceState({}, '', '/agency/services?open=create');
    render(<Index {...props} />);

    expect(screen.getByText('Add New Service')).toBeInTheDocument();
    expect(window.location.search).toBe('');
  });

  it('does not open the create modal on a normal visit', () => {
    render(<Index {...props} />);

    expect(screen.queryByText('Add New Service')).not.toBeInTheDocument();
  });
});
