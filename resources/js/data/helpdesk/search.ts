import type { HelpdeskArticle } from "./types";
import { articles } from "./articles";
import { categories } from "./categories";
import { tags } from "./tags";

// ---------------------------------------------------------------------------
// SearchableArticle — flat version of HelpdeskArticle with resolved names
// so search can match across them.
// ---------------------------------------------------------------------------
export interface SearchableArticle extends HelpdeskArticle {
  categoryName: string;
  tagNames: string[];
}

function buildSearchableArticles(): SearchableArticle[] {
  return articles.map((article) => ({
    ...article,
    categoryName:
      categories.find((c) => c.id === article.categoryId)?.name ?? "",
    tagNames: article.tagIds.map(
      (id) => tags.find((t) => t.id === id)?.name ?? "",
    ),
  }));
}

/**
 * Build the searchable article list. Kept as a named export for callers
 * that pre-resolve the corpus; searchArticles builds it lazily per call
 * (ponytail: corpus is small and static — no index cache until profiling says so).
 */
export function buildSearchIndex(): SearchableArticle[] {
  return buildSearchableArticles();
}

function haystack(article: SearchableArticle): string {
  return `${article.title} ${article.excerpt} ${article.content} ${article.categoryName} ${article.tagNames.join(" ")}`.toLowerCase();
}

/**
 * Search helpdesk articles by title, excerpt, content, category name, and tag names.
 *
 * @param query  Search string (whitespace-trimmed). Empty string returns all articles.
 * @param limit  Maximum number of results (default: 20).
 * @returns      Matching HelpdeskArticle objects (not SearchableArticle).
 */
export function searchArticles(
  query: string,
  limit: number = 20,
): HelpdeskArticle[] {
  const trimmed = query.trim().toLowerCase();

  if (!trimmed) {
    return articles.slice(0, limit);
  }

  const terms = trimmed.split(/\s+/);
  const matchesAll = (text: string) => terms.every((term) => text.includes(term));

  return (
    buildSearchableArticles()
      .filter((article) => matchesAll(haystack(article)))
      // Title hits first, then excerpt/category/tags, then body-only hits.
      .sort((left, right) => {
        const rank = (article: SearchableArticle) =>
          matchesAll(article.title.toLowerCase())
            ? 0
            : matchesAll(
                `${article.excerpt} ${article.categoryName} ${article.tagNames.join(" ")}`.toLowerCase(),
              )
              ? 1
              : 2;
        return rank(left) - rank(right);
      })
      .slice(0, limit)
      .map((result) => articles.find((a) => a.id === result.id)!)
  );
}
