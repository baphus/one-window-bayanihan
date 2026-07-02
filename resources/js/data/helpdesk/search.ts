import Fuse from "fuse.js";
import type { HelpdeskArticle } from "./types";
import { articles } from "./articles";
import { categories } from "./categories";
import { tags } from "./tags";

// ---------------------------------------------------------------------------
// SearchableArticle — flat version of HelpdeskArticle with resolved names
// so Fuse.js can search across them.
// ---------------------------------------------------------------------------
export interface SearchableArticle extends HelpdeskArticle {
  categoryName: string;
  tagNames: string[];
}

// ---------------------------------------------------------------------------
// Lazy-initialised Fuse index
// ---------------------------------------------------------------------------
let _index: Fuse<SearchableArticle> | null = null;

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
 * Build (or rebuild) the Fuse search index from the current articles,
 * categories and tags. Returns the Fuse instance for advanced usage.
 */
export function buildSearchIndex(): Fuse<SearchableArticle> {
  _index = new Fuse(buildSearchableArticles(), {
    keys: ["title", "excerpt", "content", "categoryName", "tagNames"],
    threshold: 0.4,
    distance: 100,
  });
  return _index;
}

function ensureIndex(): Fuse<SearchableArticle> {
  if (!_index) {
    return buildSearchIndex();
  }
  return _index;
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
  const trimmed = query.trim();

  if (!trimmed) {
    return articles.slice(0, limit);
  }

  const index = ensureIndex();
  return index
    .search(trimmed, { limit })
    .map((result) => articles.find((a) => a.id === result.item.id)!);
}
