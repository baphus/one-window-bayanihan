import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AppSidebar, { navByRole } from '../AppSidebar';

const { mockPage } = vi.hoisted(() => ({ mockPage: { url: '/dashboard', props: { auth: { user: { id: 'u1', name: 'Test', role: 'CASE_MANAGER' } } } } }));
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage: () => mockPage,
}));
vi.mock('@/Components/AgencyLogo', () => ({ default: ({ name, logoUrl }) => <div data-testid="agency-logo" data-name={name} data-logo={logoUrl} /> }));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => <div /> }));
vi.mock('@/Components/PeerProfileModal', () => ({ default: () => null }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ clearAllDraftBackups: vi.fn() }));

globalThis.route = (name) => `/${name}`;

it('shows case trash only in admin navigation', () => {
    const routes = (role) => navByRole[role].flatMap((group) => group.items.map((item) => item.route));
    expect(routes('CASE_MANAGER')).not.toContain('cases.trash');
    expect(routes('ADMIN')).toContain('cases.trash');
});

describe('AppSidebar agency branding', () => {
    it('uses agency logo when present and keeps branding fallback when absent', () => {
        mockPage.props.auth.user = { id: 'a1', name: 'Focal', role: 'AGENCY', agency: { name: 'Agency One', logo_url: '/agency.png' } };
        const { unmount } = render(<AppSidebar />);
        expect(screen.getByTestId('agency-logo')).toHaveAttribute('data-logo', '/agency.png');
        unmount();
        mockPage.props.auth.user = { id: 'a2', name: 'Focal', role: 'AGENCY', agency: { name: 'Agency Two' } };
        render(<AppSidebar />);
        expect(screen.getByTestId('agency-logo')).toHaveAttribute('data-name', 'Agency Two');
        mockPage.props.auth.user = { id: 'u1', name: 'Test', role: 'CASE_MANAGER' };
    });
});

describe('AppSidebar collapsed navigation', () => {
    it('exposes each collapsed icon link name to assistive technology', () => {
        localStorage.setItem('owb-sidebar-collapsed', 'true');
        render(<AppSidebar />);
        expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('title', 'Dashboard');
        localStorage.removeItem('owb-sidebar-collapsed');
    });
});
