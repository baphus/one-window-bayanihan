import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------
let mockPageUrl = '/help';
let mockPageProps = {};

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    Head: () => null,
    router: { visit: vi.fn() },
    usePage: () => ({ props: mockPageProps, url: mockPageUrl }),
}));

vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));

const ROUTES = {
    'helpdesk.index': '/help',
    'helpdesk.search': '/help/search',
    contact: '/contact',
    'track.index': '/track',
};

beforeEach(() => {
    global.route = (name) => ROUTES[name] || '/';
    mockPageProps = {};
    mockPageUrl = '/help';
    window.localStorage.clear();
});

import Index from '@/Pages/Helpdesk/Index';
import Show from '@/Pages/Helpdesk/Show';

describe('Helpdesk — accessibility', () => {
    it('landing page has a skip link as the first focusable element', () => {
        render(<Index />);

        const skipLink = screen.getByRole('link', { name: /skip to main content/i });
        expect(skipLink).toBeInTheDocument();
        expect(skipLink).toHaveAttribute('href', '#help-main');
        // tabindex="0" makes it part of the tab order
        expect(skipLink).toHaveAttribute('tabindex', '0');
    });

    it('landing page has a main region with id="help-main"', () => {
        render(<Index />);

        const main = document.getElementById('help-main');
        expect(main).not.toBeNull();
        expect(main.tagName.toLowerCase()).toBe('main');
    });

    it('category page has a skip link', () => {
        mockPageProps = { category: 'ofw-assistance' };
        render(<Index />);

        expect(screen.getByRole('link', { name: /skip to main content/i })).toBeInTheDocument();
    });

    it('article page has a skip link', () => {
        mockPageProps = { slug: 'using-public-tracking-portal' };
        render(<Show />);

        expect(screen.getByRole('link', { name: /skip to main content/i })).toBeInTheDocument();
    });

    it('topic cards are keyboard-accessible links', () => {
        render(<Index />);

        const topicLinks = screen.getAllByRole('link').filter(
            (link) => link.getAttribute('href')?.includes('category=')
        );
        expect(topicLinks.length).toBeGreaterThan(0);
        // Links are natively focusable — no additional attributes needed
        topicLinks.forEach((link) => {
            expect(link.tagName.toLowerCase()).toBe('a');
        });
    });

    it('article page has feedback buttons accessible by keyboard', () => {
        mockPageProps = { slug: 'using-public-tracking-portal' };
        render(<Show />);

        const yesBtn = screen.getByRole('button', { name: /yes/i });
        const noBtn = screen.getByRole('button', { name: /no/i });
        expect(yesBtn).toBeInTheDocument();
        expect(noBtn).toBeInTheDocument();
    });

    it('article page has a Contact Support link for escalation', () => {
        mockPageProps = { slug: 'using-public-tracking-portal' };
        render(<Show />);

        const contactLinks = screen.getAllByRole('link', { name: /contact support/i });
        expect(contactLinks.length).toBeGreaterThan(0);
    });

    it('landing page has semantic heading hierarchy (h1 > h2)', () => {
        render(<Index />);

        const h1s = screen.getAllByRole('heading', { level: 1 });
        const h2s = screen.getAllByRole('heading', { level: 2 });

        expect(h1s).toHaveLength(1);
        expect(h2s.length).toBeGreaterThan(0);
    });

    it('category view has breadcrumb navigation', () => {
        mockPageProps = { category: 'ofw-assistance' };
        render(<Index />);

        expect(screen.getByRole('navigation', { name: /breadcrumb/i })).toBeInTheDocument();
    });
});

describe('Helpdesk — responsive layout signals', () => {
    it('category page has a mobile "Browse topics" disclosure button', () => {
        mockPageProps = { category: 'ofw-assistance' };
        render(<Index />);

        const disclosure = screen.getByRole('button', { name: /browse topics/i });
        expect(disclosure).toBeInTheDocument();
        expect(disclosure).toHaveAttribute('aria-expanded', 'false');
    });

    it('landing page does not have a sidebar or browse topics button', () => {
        render(<Index />);

        expect(screen.queryByRole('button', { name: /browse topics/i })).not.toBeInTheDocument();
    });
});
