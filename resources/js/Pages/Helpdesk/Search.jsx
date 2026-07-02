import { useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import EmptyState from '@/Components/Helpdesk/EmptyState';
import TagBadge from '@/Components/Helpdesk/TagBadge';
import { searchArticles } from '@/data/helpdesk/search';
import { categories } from '@/data/helpdesk/categories';
import { tags } from '@/data/helpdesk/tags';

function getQueryFromUrl(url) {
  const search = url.includes('?') ? url.split('?')[1] : '';
  return new URLSearchParams(search).get('q') || '';
}

function resolveCategory(categoryId) {
  const cat = categories.find((c) => c.id === categoryId);
  return cat ? { id: cat.id, name: cat.name, icon: cat.icon, slug: cat.slug } : undefined;
}

function resolveTags(tagIds) {
  return tagIds
    .map((id) => tags.find((tag) => tag.id === id))
    .filter(Boolean);
}

function buildBadges(article) {
  const category = resolveCategory(article.categoryId);
  const resolvedTags = resolveTags(article.tagIds);

  return [
    category && (
      <span
        key={`category-${article.id}`}
        className="inline-flex items-center rounded-none border border-primary/20 bg-primary/10 px-2.5 py-0.5 font-label text-[11px] font-semibold uppercase tracking-[0.12em] text-primary"
      >
        {category.name}
      </span>
    ),
    ...resolvedTags.map((tag) => <TagBadge key={tag.id} tag={tag} />),
  ].filter(Boolean);
}

export default function Search() {
  const page = usePage();
  const routeQuery = page.props?.query ?? '';
  const urlQuery = getQueryFromUrl(page.url);
  const initialQuery = routeQuery || urlQuery;

  const [query, setQuery] = useState(initialQuery);

  useEffect(() => {
    setQuery(initialQuery);
  }, [initialQuery]);

  const results = useMemo(() => {
    const trimmed = query.trim();
    if (!trimmed) return [];

    return searchArticles(trimmed).map((article) => ({
      ...article,
      category: resolveCategory(article.categoryId),
      updated_at: article.publishedAt,
      badges: buildBadges(article),
    }));
  }, [query]);

  const hasSearched = query.trim().length > 0;

  return (
    <HelpdeskLayout
      title={query ? `Search: ${query}` : 'Search the Help Center'}
      query={query}
      showSearchHero={false}
    >
      <div className="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
              Search
            </p>
            <h1 className="mt-2 font-headline text-3xl font-bold text-slate-900">
              Search the help center
            </h1>
            <p className="mt-2 max-w-2xl text-sm text-slate-500">
              Search titles, article content, categories, and tags.
            </p>
          </div>

          <Link
            href={route('helpdesk.index')}
            className="inline-flex items-center justify-center rounded-none border border-primary bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-[#00446f]"
          >
            Browse all articles
          </Link>
        </div>
      </div>

      <div className="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div className="relative">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary">search</span>
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search articles, topics, and keywords..."
            className="w-full border border-slate-200 py-3 pl-10 pr-4 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
      </div>

      <div className="mb-6 flex flex-wrap items-center gap-3 text-xs text-slate-500">
        <span className="rounded-none border border-slate-200 bg-surface-container-low px-3 py-1 font-label font-semibold uppercase tracking-[0.14em] text-slate-700">
          {hasSearched ? `${results.length} result${results.length === 1 ? '' : 's'}` : 'Ready to search'}
        </span>
        <span>Search covers content, categories, and tags.</span>
      </div>

      {results.length > 0 ? (
        <div className="space-y-3">
          {results.map((article) => (
            <ArticleCard
              key={article.id}
              article={article}
              categoryName={article.category?.name}
              categoryIcon={article.category?.icon}
              badges={article.badges}
            />
          ))}
        </div>
      ) : hasSearched ? (
        <EmptyState
          icon="search_off"
          title="No results found"
          description={`We couldn't find any articles matching “${query}”. Try a different keyword, or browse by category.`}
          action={
            <div className="flex flex-col items-center gap-3 sm:flex-row">
              <Link
                href={route('helpdesk.index')}
                className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white"
              >
                Browse topics
              </Link>
              <Link
                href={route('helpdesk.search') + '?q=' + encodeURIComponent(query)}
                className="rounded-none border border-slate-200 bg-white px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
              >
                Retry search
              </Link>
              <Link
                href={route('contact')}
                className="rounded-none border border-slate-200 bg-white px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
              >
                Contact support
              </Link>
            </div>
          }
        />
      ) : (
        <div className="space-y-6">
          <EmptyState
            icon="search"
            title="Search the help center"
            description="Enter a search term to find articles, guides, and reference pages."
            action={
              <div className="flex flex-col items-center gap-3 sm:flex-row">
                <Link
                  href={route('helpdesk.index') + '#topics'}
                  className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white"
                >
                  Browse topics
                </Link>
                <Link
                  href={route('contact')}
                  className="rounded-none border border-slate-200 bg-white px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
                >
                  Contact support
                </Link>
              </div>
            }
          />

          <div>
            <h2 className="mb-3 font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
              Popular categories
            </h2>
            <div className="flex flex-wrap gap-2">
              {categories
                .filter((category) => category.parentId === null)
                .map((category) => (
                  <Link
                    key={category.id}
                    href={`/helpdesk?category=${category.slug}`}
                    className="inline-flex items-center gap-2 rounded-none border border-slate-200 bg-white px-3 py-1.5 font-label text-xs font-medium text-slate-700 transition-colors hover:border-primary/30 hover:text-primary"
                  >
                    {category.icon && (
                      <span className="material-symbols-outlined text-sm text-slate-400">
                        {category.icon}
                      </span>
                    )}
                    {category.name}
                  </Link>
                ))}
            </div>
          </div>
        </div>
      )}
    </HelpdeskLayout>
  );
}
