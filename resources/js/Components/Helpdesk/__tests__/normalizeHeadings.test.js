import { describe, it, expect } from 'vitest';
import { normalizeHeadings } from '../MarkdownRenderer';

describe('normalizeHeadings', () => {
  it('drops a leading # title (duplicates the page h1)', () => {
    const input = '# Article Title\n\nBody text.';
    expect(normalizeHeadings(input)).toBe('\nBody text.');
  });

  it('demotes non-leading h1 headings to h2', () => {
    const input = '# Title\n\nIntro.\n\n# Another Section\n\nMore.';
    expect(normalizeHeadings(input)).toBe('\nIntro.\n\n## Another Section\n\nMore.');
  });

  it('keeps h2+ headings untouched', () => {
    const input = '# Title\n\n## Section\n\n### Sub';
    expect(normalizeHeadings(input)).toBe('\n## Section\n\n### Sub');
  });

  it('does not touch # lines inside fenced code blocks', () => {
    const input = '# Title\n\n```bash\n# a comment\n```\n\nAfter.';
    expect(normalizeHeadings(input)).toBe('\n```bash\n# a comment\n```\n\nAfter.');
  });

  it('demotes a first h1 that appears after other content', () => {
    const input = 'Intro paragraph first.\n\n# Heading\n\nBody.';
    expect(normalizeHeadings(input)).toBe('Intro paragraph first.\n\n## Heading\n\nBody.');
  });
});
