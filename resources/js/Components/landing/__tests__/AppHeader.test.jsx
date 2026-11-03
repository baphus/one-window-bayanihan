import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const { user } = vi.hoisted(() => ({ user: { name: 'Juan Cruz', role: 'OFW', avatar_url: '/private/avatar.jpg' } }));

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage: () => ({ url: '/', props: { auth: { user }, roles: { OFW: 'OFW' } } }),
}));
vi.mock('@/utils/safeRoute', () => ({ default: (_name, _params, fallback) => fallback }));

global.route = (name) => `/${name}`;

import AppHeader from '../AppHeader';

describe('AppHeader', () => {
    it('shows authenticated user avatar photo in header', () => {
        render(<AppHeader />);

        expect(screen.getByRole('img', { name: 'Juan Cruz' })).toHaveAttribute('src', '/private/avatar.jpg');
    });
});
