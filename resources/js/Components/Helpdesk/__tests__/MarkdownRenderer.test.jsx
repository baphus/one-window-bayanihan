import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import MarkdownRenderer, { normalizeHeadings } from '../MarkdownRenderer';

describe('MarkdownRenderer', () => {
    it('renders without error with content', () => {
        const { container } = render(<MarkdownRenderer content="# Hello\n\nWorld" />);
        expect(container.querySelector('.md-renderer')).not.toBeNull();
    });

    it('renders fallback when content is null', () => {
        const { container } = render(<MarkdownRenderer content={null} />);
        expect(container.textContent).toContain('No content available');
    });

    it('renders fallback when content is empty string', () => {
        const { container } = render(<MarkdownRenderer content="" />);
        expect(container.textContent).toContain('No content available');
    });

    it('heading anchor links get aria-hidden="true" (accessibility)', () => {
        const markdown = `## What you need\n\nSome content\n\n## Steps\n\nMore content`;
        const { container } = render(<MarkdownRenderer content={markdown} />);

        const anchorLinks = container.querySelectorAll('a[href^="#"]');
        anchorLinks.forEach((link) => {
            if (!link.textContent?.trim()) {
                expect(link.getAttribute('aria-hidden')).toBe('true');
                expect(link.getAttribute('tabindex')).toBe('-1');
            }
        });
    });
});

describe('normalizeHeadings', () => {
    it('drops the leading # heading (duplicates article title)', () => {
        const input = '# Title\n\nContent here';
        const result = normalizeHeadings(input);
        expect(result).not.toContain('# Title');
        expect(result).toContain('Content here');
    });

    it('demotes # headings after content to ##', () => {
        const input = 'Some content\n\n# Another heading\n\nMore content';
        const result = normalizeHeadings(input);
        expect(result).toContain('## Another heading');
        expect(result).not.toMatch(/^# Another heading/m);
    });

    it('does not modify headings inside fenced code blocks', () => {
        const input = '```\n# This is code\n```';
        const result = normalizeHeadings(input);
        expect(result).toContain('# This is code');
    });

    it('preserves ## and ### headings unchanged', () => {
        const input = '## Keep this\n\n### And this';
        const result = normalizeHeadings(input);
        expect(result).toContain('## Keep this');
        expect(result).toContain('### And this');
    });
});
