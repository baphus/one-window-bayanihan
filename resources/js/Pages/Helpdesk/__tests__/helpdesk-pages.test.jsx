import { render, screen, within } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mocks: Inertia, heavy layout children, markdown renderer, ziggy route()
// ---------------------------------------------------------------------------
let mockPageProps = {};
let mockPageUrl = '/help';

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children, ...props }) => (
    <a href={typeof href === 'string' ? href : '#'} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { visit: vi.fn() },
  usePage: () => ({ props: mockPageProps, url: mockPageUrl }),
}));

vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/Helpdesk/MarkdownRenderer', () => ({
  default: ({ content }) => <div data-testid="markdown">{content ? 'content' : 'empty'}</div>,
}));

// Scalability scenario (spec: "Article count doubles"): serve the page a
// doubled articles array and assert landing density is unchanged.
vi.mock('@/data/helpdesk/articles', async (importOriginal) => {
  const mod = await importOriginal();
  const doubled = [
    ...mod.articles,
    ...mod.articles.map((a) => ({
      ...a,
      id: `${a.id}-copy`,
      slug: `${a.slug}-copy`,
    })),
  ];
  return { ...mod, articles: doubled };
});

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

import Index from '../Index';
import Show from '../Show';
import Search from '../Search';
import { categories } from '@/data/helpdesk/categories';
import { articles } from '@/data/helpdesk/articles';
import { MAX_POPULAR_ARTICLES } from '@/data/helpdesk/popular';
import { audienceEntries } from '@/data/helpdesk/audiences';

const TOP_LEVEL_COUNT = categories.filter((c) => c.parentId === null).length;

describe('Helpdesk landing page (/help)', () => {
  it('renders without console errors', () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    render(<Index />);
    expect(errorSpy).not.toHaveBeenCalled();
    errorSpy.mockRestore();
  });

  it('has exactly one h1', () => {
    render(<Index />);
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
  });

  it('has exactly one search input (single entry point)', () => {
    render(<Index />);
    expect(screen.getAllByRole('textbox')).toHaveLength(1);
  });

  it('does not render the sidebar or mobile topics navigation', () => {
    render(<Index />);
    expect(screen.queryByRole('navigation', { name: /help topics/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /browse topics/i })).not.toBeInTheDocument();
  });

  it('renders one topic card per top-level category, with article counts', () => {
    render(<Index />);
    const topics = screen.getByRole('heading', { name: /browse topics/i }).closest('section');
    const links = within(topics).getAllByRole('link');
    expect(links).toHaveLength(TOP_LEVEL_COUNT);
    expect(within(topics).getAllByText(/\d+ articles?/)).toHaveLength(TOP_LEVEL_COUNT);
  });

  it('renders the audience row identically regardless of auth', () => {
    mockPageProps = { auth: { user: { role: 'ADMIN' } } };
    render(<Index />);
    const section = screen.getByRole('heading', { name: /find help for you/i }).closest('section');
    const links = within(section).getAllByRole('link');
    expect(links).toHaveLength(audienceEntries.length);
    expect(links.map((l) => l.getAttribute('href'))).toEqual(audienceEntries.map((e) => e.href));
  });

  it('caps popular articles even with doubled article data (scalability)', () => {
    render(<Index />);
    const popular = screen.getByRole('heading', { name: /popular articles/i }).closest('section');
    const items = within(popular).getAllByRole('listitem');
    expect(items.length).toBeLessThanOrEqual(MAX_POPULAR_ARTICLES);
  });

  it('landing density is invariant: no per-article sections despite doubled data', () => {
    // With 64+ articles in the mocked module, the landing page still renders
    // only: hero + 3 fixed sections (audience, topics, popular).
    render(<Index />);
    const headings = screen.getAllByRole('heading', { level: 2 });
    expect(headings.map((h) => h.textContent)).toEqual([
      'Find help for you',
      'Browse topics',
      'Popular articles',
    ]);
  });

  it('has exactly one Contact Support CTA', () => {
    render(<Index />);
    expect(screen.getAllByRole('link', { name: /contact support/i })).toHaveLength(1);
  });
});

describe('Helpdesk category view (/help?category=...)', () => {
  it('renders parent category with subcategory group headings', () => {
    mockPageProps = { category: 'ofw-assistance' };
    render(<Index />);
    expect(screen.getByRole('heading', { level: 1, name: 'OFW Assistance' })).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: /breadcrumb/i })).toBeInTheDocument();
    // One h2 per subcategory of OFW Assistance
    expect(screen.getByRole('heading', { level: 2, name: /case submission/i })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 2, name: /ofw rights/i })).toBeInTheDocument();
    // Sidebar returns on browse pages (desktop nav + mobile disclosure)
    expect(screen.getAllByRole('navigation', { name: /help topics/i }).length).toBeGreaterThanOrEqual(1);
  });

  it('renders subcategory view with breadcrumb back to parent', () => {
    mockPageProps = { category: 'case-submission' };
    render(<Index />);
    expect(screen.getByRole('heading', { level: 1, name: 'Case Submission' })).toBeInTheDocument();
    const breadcrumb = screen.getByRole('navigation', { name: /breadcrumb/i });
    expect(within(breadcrumb).getByRole('link', { name: 'OFW Assistance' })).toBeInTheDocument();
  });

  it('shows not-found state for an unknown category slug', () => {
    mockPageProps = { category: 'does-not-exist' };
    render(<Index />);
    expect(screen.getByText(/category not found/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /back to help center/i })).toHaveAttribute('href', '/help');
  });
});

describe('Helpdesk article page (/help/{slug})', () => {
  it('renders article with single h1, feedback widget, and contact escalation', () => {
    mockPageProps = { slug: 'using-public-tracking-portal' };
    render(<Show />);
    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('article')).toBeInTheDocument();
    expect(screen.getByText(/was this article helpful/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /yes/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /no/i })).toBeInTheDocument();
  });

  it('shows not-found state for an unknown article slug', () => {
    mockPageProps = { slug: 'nope-not-real' };
    render(<Show />);
    expect(screen.getByText(/article not found/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /back to help center/i })).toHaveAttribute('href', '/help');
  });
});

describe('Helpdesk search page (/help/search)', () => {
  it('renders results as list rows inside the browse layout', () => {
    mockPageProps = { query: 'referral' };
    mockPageUrl = '/help/search?q=referral';
    render(<Search />);
    expect(screen.getByRole('heading', { level: 1, name: /search the help center/i })).toBeInTheDocument();
    expect(screen.getAllByRole('listitem').length).toBeGreaterThan(0);
    // Browse layout: sidebar nav present
    expect(screen.getAllByRole('navigation', { name: /help topics/i }).length).toBeGreaterThanOrEqual(1);
    // Its own search input only (layout compact search suppressed)
    expect(screen.getAllByRole('textbox')).toHaveLength(1);
  });
});
