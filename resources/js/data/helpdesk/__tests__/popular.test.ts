import { describe, it, expect } from 'vitest';
import type { HelpdeskArticle } from '../types';
import {
  popularArticles,
  resolvePopularArticles,
  MAX_POPULAR_ARTICLES,
} from '../popular';
import { articles } from '../articles';

function fakeArticle(slug: string): HelpdeskArticle {
  return {
    id: `id-${slug}`,
    title: slug,
    slug,
    excerpt: '',
    content: 'word '.repeat(50),
    categoryId: 'cat-1',
    tagIds: [],
    featured: false,
    publishedAt: '2025-01-01T00:00:00.000Z',
  };
}

describe('popularArticles curation', () => {
  it('every curated slug resolves to a real article', () => {
    const slugs = new Set(articles.map((a) => a.slug));
    for (const slug of popularArticles) {
      expect(slugs.has(slug), `curated slug "${slug}" has no matching article`).toBe(true);
    }
  });

  it('preserves curated order', () => {
    const resolved = resolvePopularArticles(articles);
    expect(resolved.map((a) => a.slug)).toEqual(
      popularArticles.slice(0, MAX_POPULAR_ARTICLES)
    );
  });

  it('skips stale slugs without throwing', () => {
    const pool = [fakeArticle(popularArticles[0])];
    // Only the first curated slug exists in this pool; the rest are "stale".
    const resolved = resolvePopularArticles(pool);
    expect(resolved.map((a) => a.slug)).toEqual([popularArticles[0]]);
  });

  it('returns empty list when nothing resolves (never breaks the page)', () => {
    expect(resolvePopularArticles([])).toEqual([]);
  });

  it(`caps the rendered list at ${MAX_POPULAR_ARTICLES}`, () => {
    const resolved = resolvePopularArticles(articles);
    expect(resolved.length).toBeLessThanOrEqual(MAX_POPULAR_ARTICLES);
  });
});
