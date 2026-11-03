import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import OfwPortalLayout from '../OfwPortalLayout';

let pageProps;
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage: () => ({ url: '/my-cases', props: pageProps }),
}));
vi.mock('@/Components/ToastProvider', () => ({ FlashMessageWatcher: () => null }));
vi.mock('@/Components/OfwNotificationBell', () => ({ default: () => null }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ clearAllDraftBackups: vi.fn() }));

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
