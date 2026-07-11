import { Link, usePage } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import MarkdownRenderer from '@/Components/Helpdesk/MarkdownRenderer';
import ArticleFeedback from '@/Components/Helpdesk/ArticleFeedback';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import RelatedArticles from '@/Components/Helpdesk/RelatedArticles';
import TagBadge from '@/Components/Helpdesk/TagBadge';
import { formatDisplayDate } from '@/lib/utils';
import { articles } from '@/data/helpdesk/articles';
import { categories } from '@/data/helpdesk/categories';
import { tags } from '@/data/helpdesk/tags';

// ---------------------------------------------------------------------------
// Build breadcrumb trail by walking the category's parentId chain
// ---------------------------------------------------------------------------
function buildBreadcrumbs(categoryId) {
  const items = [];
  let current = categories.find((c) => c.id === categoryId);
  while (current) {
    items.unshift({ label: current.name, href: `/help?category=${current.slug}` });
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
  // ── Read slug from Inertia page props ──────────────────────────────────────
  const { slug } = usePage().props;

  // ── Look up article by slug ────────────────────────────────────────────────
  const article = articles.find((a) => a.slug === slug);

  // ── Article content ────────────────────────────────────────────────────────
  const content = article?.content || null;

  // ── 404: article not found ─────────────────────────────────────────────────
  if (!article) {
    return (
      <HelpdeskLayout title="Article Not Found">
        <div className="rounded-lg border border-dashed border-slate-200 bg-white py-16 text-center">
          <span className="material-symbols-outlined mb-4 text-4xl text-primary/30">
            search_off
          </span>
          <h2 className="font-headline text-lg font-semibold text-slate-900">Article not found</h2>
          <p className="mt-1 text-sm text-slate-500">
            The article you&apos;re looking for doesn&apos;t exist or has been removed.
          </p>
          <Link
            href="/help"
            className="mt-4 inline-flex rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-[#00446f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            Back to Help Center
          </Link>
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
      activeSlug={category?.slug}
    >
      <Breadcrumbs items={breadcrumbItems} />

      <article>
        <header className="mb-8 border-l-4 border-primary bg-white p-6 shadow-sm">
          {/* Category badge */}
          {category && (
            <Link
              href={`/help?category=${category.slug}`}
              className="mb-3 inline-flex items-center gap-1 rounded-none bg-primary/10 px-3 py-1 font-label text-[11px] font-semibold uppercase tracking-[0.14em] text-primary transition-colors hover:bg-primary/20"
            >
              {category.icon && (
                <span className="material-symbols-outlined text-sm">
                  {category.icon}
                </span>
              )}
              {category.name}
            </Link>
          )}

          <h1 className="font-headline text-3xl font-bold leading-tight text-slate-900">
            {article.title}
          </h1>

          <div className="mt-3 flex flex-wrap items-center gap-3 font-label text-[11px] uppercase tracking-[0.14em] text-slate-500">
            <span>Published {formatDisplayDate(article.publishedAt)}</span>
            {content && <span>{readTime} min read</span>}
          </div>

          {article.excerpt && (
            <p className="mt-4 max-w-3xl text-sm leading-relaxed text-slate-600">
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

        {/* Markdown content */}
        <div className="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <MarkdownRenderer content={content} />
        </div>

        <ArticleFeedback slug={article.slug} />
      </article>

      {/* Related articles */}
      {relatedArticles.length > 0 && (
        <div className="mt-8">
          <RelatedArticles currentArticle={article} allArticles={articles} />
        </div>
      )}

      <div className="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="font-headline text-base font-bold text-slate-900">Need more help?</h2>
            <p className="mt-1 text-sm text-slate-500">
              Contact support if this article does not answer your question.
            </p>
          </div>
          <Link
            href={route('contact')}
            className="inline-flex items-center justify-center rounded-none border border-primary bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-[#00446f]"
          >
            Contact support
          </Link>
        </div>
      </div>
    </HelpdeskLayout>
  );
}
