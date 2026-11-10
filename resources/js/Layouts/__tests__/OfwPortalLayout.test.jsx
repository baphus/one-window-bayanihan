import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import OfwPortalLayout from '../OfwPortalLayout';

let pageProps;
let pageUrl = '/my-cases';
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage: () => ({ url: pageUrl, props: pageProps }),
}));
vi.mock('@/Components/ToastProvider', () => ({ FlashMessageWatcher: () => null }));
vi.mock('@/Components/OfwNotificationBell', () => ({ default: () => null }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ clearAllDraftBackups: vi.fn() }));

describe('OFW portal navigation', () => {
    it.each([
        ['/my-cases', 'My Requests'],
        ['/my-cases/example-id', 'My Requests'],
        ['/my-cases/example-id/documents', 'My Requests'],
        ['/my-cases/profile', 'Profile'],
        ['/my-cases/notifications', 'Notifications'],
        ['/my-cases-other', null],
    ])('marks only expected item active at %s in desktop and mobile navigation', (url, expectedLabel) => {
        pageUrl = url;
        pageProps = { auth: { user: { name: 'Aida Santos' } } };
        render(<OfwPortalLayout><div /></OfwPortalLayout>);

        const desktopNav = screen.getByRole('navigation', { name: 'Citizen portal' });
        const links = [...desktopNav.querySelectorAll('a')];
        expect(links.filter((link) => link.getAttribute('aria-current') === 'page').map((link) => link.querySelector('span.min-w-0').textContent.trim()))
            .toEqual(expectedLabel ? [expectedLabel] : []);

        fireEvent.click(screen.getByRole('button', { name: 'Open menu' }));
        const mobileNav = screen.getByRole('complementary', { name: 'Citizen portal' }).querySelector('nav');
        expect([...mobileNav.querySelectorAll('a')].filter((link) => link.getAttribute('aria-current') === 'page').map((link) => link.querySelector('span.min-w-0').textContent.trim()))
            .toEqual(expectedLabel ? [expectedLabel] : []);
    });
});

describe('OFW portal avatar', () => {
    it('shows shared auth avatar, falls back on image failure, and reacts to changed photo', () => {
        pageProps = { auth: { user: { name: 'Aida Santos', avatar_url: '/avatar.jpg' } } };
        const { rerender } = render(<OfwPortalLayout><div /></OfwPortalLayout>);

        const image = screen.getByRole('img', { name: 'Aida Santos profile photo' });
        expect(image).toHaveAttribute('src', '/avatar.jpg');
        fireEvent.error(image);
        expect(screen.getByText('A', { selector: 'span' })).toBeInTheDocument();

        pageProps = { auth: { user: { name: 'Aida Santos', avatar_url: '/new-avatar.jpg' } } };
        rerender(<OfwPortalLayout><div /></OfwPortalLayout>);
        expect(screen.getByRole('img', { name: 'Aida Santos profile photo' })).toHaveAttribute('src', '/new-avatar.jpg');
    });
});
