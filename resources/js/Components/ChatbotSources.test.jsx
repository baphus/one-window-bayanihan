import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import ChatbotSources from './ChatbotSources';

vi.mock('@inertiajs/react', () => ({ Link: ({ children, ...props }) => <a {...props}>{children}</a> }));
beforeEach(() => { global.route = (_name, slug) => `/helpdesk/${slug}`; });

test('shows specific article links once and ignores supplied URLs', () => {
    const article = { source_type: 'helpdesk', slug: 'using-public-tracking-portal', article_title: 'Using the Public Tracking Portal', url: 'https://untrusted.example' };
    render(<ChatbotSources sources={[article, article, { source_type: 'helpdesk', slug: 'glossary-of-terms', article_title: 'Glossary' }]} />);
    expect(screen.getAllByRole('link')).toHaveLength(2);
    expect(screen.getByRole('link', { name: article.article_title })).toHaveAttribute('href', '/helpdesk/using-public-tracking-portal');
});

test('renders directory evidence as a label and tolerates legacy empty sources', () => {
    const { rerender } = render(<ChatbotSources />);
    expect(screen.queryByText('Sources')).not.toBeInTheDocument();
    rerender(<ChatbotSources sources={[{ source_type: 'reference', slug: 'owwa', heading: 'OWWA — Agency directory' }]} />);
    expect(screen.getByText('OWWA — Agency directory')).toBeInTheDocument();
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
});
