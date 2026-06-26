import { useState, useEffect } from 'react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import MarkdownRenderer from '@/Components/Helpdesk/MarkdownRenderer';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import RelatedArticles from '@/Components/Helpdesk/RelatedArticles';
import TagBadge from '@/Components/Helpdesk/TagBadge';
import { formatDisplayDate } from '@/lib/utils';
import { articles } from '@/data/helpdesk/articles';
import { categories } from '@/data/helpdesk/categories';
import { tags } from '@/data/helpdesk/tags';

// ---------------------------------------------------------------------------
// Build hierarchical category tree for HelpdeskLayout sidebar
// ---------------------------------------------------------------------------
const hierarchicalCategories = (() => {
  const parents = categories.filter((c) => c.parentId === null);
  return parents.map((parent) => {
    const children = categories
      .filter((c) => c.parentId === parent.id)
      .map((child) => ({
        ...child,
        published_articles_count: articles.filter((a) => a.categoryId === child.id).length,
      }));
    const totalArticles = children.reduce((sum, c) => sum + c.published_articles_count, 0);
    return { ...parent, children, total_articles: totalArticles };
  });
})();

// ---------------------------------------------------------------------------
// Build breadcrumb trail by walking the category's parentId chain
// ---------------------------------------------------------------------------
function buildBreadcrumbs(categoryId) {
  const items = [];
  let current = categories.find((c) => c.id === categoryId);
  while (current) {
    items.unshift({ label: current.name, href: `/helpdesk?category=${current.slug}` });
    current = current.parentId ? categories.find((c) => c.id === current.parentId) : null;
  }
  return items;
}

// ---------------------------------------------------------------------------
// Compute related articles by shared tags (excluding current)
// ---------------------------------------------------------------------------
function computeRelated(article) {
  return articles
    .filter((a) => a.id !== article.id)
    .map((a) => ({
      ...a,
      _score: a.tagIds.filter((tid) => article.tagIds.includes(tid)).length,
    }))
    .filter((a) => a._score > 0)
    .sort((a, b) => b._score - a._score)
    .slice(0, 5);
}

export default function Show() {
  // ── Derive slug from the URL path ──────────────────────────────────────────
  const slug = window.location.pathname.replace('/helpdesk/', '').split('/')[0] || '';

  // ── Look up article by slug ────────────────────────────────────────────────
  const article = articles.find((a) => a.slug === slug);

  // ── Lazy-load markdown content via dynamic import ──────────────────────────
  const [content, setContent] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!article) return;

    setLoading(true);
    setContent(null);

    import(/* @vite-ignore */ `@/data/helpdesk/content/${slug}.ts`)
      .then((mod) => {
        setContent(mod.default);
        setLoading(false);
      })
      .catch(() => {
        setContent(null);
        setLoading(false);
      });
  }, [slug, article]);

  // ── 404: article not found ─────────────────────────────────────────────────
  if (!article) {
    return (
      <HelpdeskLayout title="Article Not Found" categories={hierarchicalCategories}>
        <div className="flex flex-col items-center justify-center py-16">
          <span className="material-symbols-outlined text-4xl text-slate-300 mb-4">
            search_off
          </span>
          <h2 className="text-lg font-semibold text-slate-900">Article not found</h2>
          <p className="text-sm text-slate-500 mt-1">
            The article you&apos;re looking for doesn&apos;t exist or has been removed.
          </p>
          <a
            href="/helpdesk"
            className="mt-4 text-sm font-medium text-primary hover:underline"
          >
            Back to Help Center
          </a>
        </div>
      </HelpdeskLayout>
    );
  }

  // ── Resolve category and tags ──────────────────────────────────────────────
  const category = categories.find((c) => c.id === article.categoryId);
  const resolvedTags = article.tagIds
    .map((id) => tags.find((t) => t.id === id))
    .filter(Boolean);

  // ── Breadcrumbs and related articles ───────────────────────────────────────
  const breadcrumbItems = buildBreadcrumbs(article.categoryId);
  const relatedArticles = computeRelated(article);

  // ── Reading time ───────────────────────────────────────────────────────────
  const wordCount = (content || '').split(/\s+/).filter(Boolean).length;
  const readTime = Math.max(1, Math.ceil(wordCount / 200));

  return (
    <HelpdeskLayout
      title={article.title}
      categories={hierarchicalCategories}
      activeSlug={category?.slug}
    >
      <Breadcrumbs items={breadcrumbItems} />

      <article>
        <header className="mb-8">
          {/* Category badge */}
          {category && (
            <a
              href={`/helpdesk?category=${category.slug}`}
              className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary hover:bg-primary/20 transition-colors mb-3"
            >
              {category.icon && (
                <span className="material-symbols-outlined text-sm">
                  {category.icon}
                </span>
              )}
              {category.name}
            </a>
          )}

          <h1 className="text-2xl font-bold text-slate-900 leading-tight">
            {article.title}
          </h1>

          <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <span>Published {formatDisplayDate(article.publishedAt)}</span>
            {!loading && content && <span>{readTime} min read</span>}
          </div>

          {article.excerpt && (
            <p className="mt-3 text-sm text-slate-600 leading-relaxed">
              {article.excerpt}
            </p>
          )}
        </header>

        {/* Tags */}
        {resolvedTags.length > 0 && (
          <div className="mb-6 flex flex-wrap gap-1.5">
            {resolvedTags.map((tag) => (
              <TagBadge key={tag.id} tag={tag} />
            ))}
          </div>
        )}

        {/* Lazy-loaded markdown content */}
        <div className="mb-10 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <div className="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-primary" />
              <span className="ml-3 text-sm text-slate-400">
                Loading content...
              </span>
            </div>
          ) : (
            <MarkdownRenderer content={content} />
          )}
        </div>
      </article>

      {/* Related articles */}
      {relatedArticles.length > 0 && (
        <div className="mt-8">
          <RelatedArticles currentArticle={article} allArticles={articles} />
        </div>
      )}
    </HelpdeskLayout>
  );
}
