import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import OfwLayout from '../OfwLayout';

let pageProps;
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage: () => ({ url: '/my-cases', props: pageProps }),
}));
vi.mock('@/Components/ToastProvider', () => ({ FlashMessageWatcher: () => null }));
vi.mock('@/utils/safeRoute', () => ({ default: (_name, _params, fallback) => fallback }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ clearAllDraftBackups: vi.fn() }));

describe('OFW layout avatar', () => {
    it('shows the shared auth profile photo in the header', () => {
        pageProps = { auth: { user: { name: 'Aida Santos', avatar_url: '/avatar.jpg' } } };
        render(<OfwLayout><div /></OfwLayout>);

        const image = screen.getByRole('img', { name: 'Aida Santos profile photo' });
        expect(image).toHaveAttribute('src', '/avatar.jpg');
    });
});
