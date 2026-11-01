import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Search from '../Search';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    router: { get },
    usePage: () => ({ url: '/help/search?q=old', props: { query: 'old', helpdeskArticles: [] } }),
}));
vi.mock('@/Layouts/HelpdeskLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/Helpdesk/ArticleListRow', () => ({ default: () => null }));
vi.mock('@/Components/Helpdesk/EmptyState', () => ({ default: () => <div /> }));

globalThis.route = (name) => `/${name}`;

describe('Helpdesk search page', () => {
    it('submits edited query through Inertia so URL and result query stay synchronized', () => {
        render(<Search />);
        const input = screen.getByRole('textbox', { name: 'Search articles, topics, and keywords' });
        fireEvent.change(input, { target: { value: 'new query' } });
        fireEvent.submit(input.closest('form'));

        expect(get).toHaveBeenCalledWith('/helpdesk.search', { q: 'new query' }, { preserveState: false, preserveScroll: true });
    });
});
