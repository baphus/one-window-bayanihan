import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import HelpdeskLayout from '../HelpdeskLayout';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { visit },
    usePage: () => ({ props: { helpdeskArticles: [] } }),
}));
vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Onboarding/useChecklistVisitTracking', () => ({ default: () => {} }));

globalThis.route = (name, params) => name === 'helpdesk.search' && params?.q
    ? `/help/search?q=${encodeURIComponent(params.q)}`
    : `/${name}`;

describe('Helpdesk search URL navigation', () => {
    beforeEach(() => visit.mockClear());

    it('synchronizes submitted search query to URL without preserving stale page state', () => {
        render(<HelpdeskLayout title="Help Center"><div /></HelpdeskLayout>);
        fireEvent.change(screen.getByRole('textbox', { name: 'Search the help center' }), { target: { value: 'passport help' } });
        fireEvent.click(screen.getByRole('button', { name: 'Search' }));

        expect(visit).toHaveBeenCalledWith('/help/search?q=passport%20help', { preserveState: false });
    });
});
