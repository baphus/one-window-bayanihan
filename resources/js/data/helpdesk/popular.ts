import type { HelpdeskArticle } from "./types";

/**
 * Curated "Popular articles" shown on the /help landing page.
 *
 * CURATOR NOTES:
 * - Entries are article slugs from articles.ts; display order = array order.
 * - At most MAX_POPULAR_ARTICLES entries are rendered.
 * - A slug that no longer matches an article is skipped silently (warned in
 *   dev), so a stale entry can never break the page — but review this list
 *   whenever articles are added, renamed, or retired.
 */
export const popularArticles: string[] = [
  "your-case-journey-from-intake-to-resolution",
  "using-public-tracking-portal",
  "understanding-case-statuses-tracker-numbers",
  "providing-feedback-on-your-case",
  "getting-started-agency-focal",
  "getting-started-case-managers",
];

export const MAX_POPULAR_ARTICLES = 6;

/**
 * Resolve curated slugs to articles, preserving curated order.
 * Unknown slugs are skipped (dev-time warning) and the result is capped
 * at MAX_POPULAR_ARTICLES.
 */
export function resolvePopularArticles(
  articles: HelpdeskArticle[],
): HelpdeskArticle[] {
  const resolved: HelpdeskArticle[] = [];

  for (const slug of popularArticles) {
    const article = articles.find((a) => a.slug === slug);
    if (article) {
      resolved.push(article);
    } else if (import.meta.env?.DEV) {
      console.warn(`[helpdesk] popularArticles slug not found: "${slug}"`);
    }
  }

  return resolved.slice(0, MAX_POPULAR_ARTICLES);
}
