import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AppSidebar from '../AppSidebar';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage: () => ({ url: '/dashboard', props: { auth: { user: { id: 'u1', name: 'Test', role: 'CASE_MANAGER' } } } }),
}));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => <div /> }));
vi.mock('@/Components/PeerProfileModal', () => ({ default: () => null }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ clearAllDraftBackups: vi.fn() }));

globalThis.route = (name) => `/${name}`;

describe('AppSidebar collapsed navigation', () => {
    it('exposes each collapsed icon link name to assistive technology', () => {
        localStorage.setItem('owb-sidebar-collapsed', 'true');
        render(<AppSidebar />);
        expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('title', 'Dashboard');
        localStorage.removeItem('owb-sidebar-collapsed');
    });
});
