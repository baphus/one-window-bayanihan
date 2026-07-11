import { useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleListRow from '@/Components/Helpdesk/ArticleListRow';
import EmptyState from '@/Components/Helpdesk/EmptyState';
import { searchArticles } from '@/data/helpdesk/search';
import { categories } from '@/data/helpdesk/categories';

function getQueryFromUrl(url) {
  const search = url.includes('?') ? url.split('?')[1] : '';
  return new URLSearchParams(search).get('q') || '';
}

function categoryName(categoryId) {
  return categories.find((c) => c.id === categoryId)?.name;
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
    return searchArticles(trimmed);
  }, [query]);

  const hasSearched = query.trim().length > 0;

  return (
    <HelpdeskLayout
      title={query ? `Search: ${query}` : 'Search the Help Center'}
      query={query}
      showCompactSearch={false}
    >
      <header className="mb-6">
        <h1 className="font-headline text-2xl font-bold text-slate-900 sm:text-3xl">
          Search the help center
        </h1>
        <p className="mt-2 max-w-2xl text-sm text-slate-500">
          Search titles, article content, categories, and tags.
        </p>
      </header>

      <div className="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div className="relative" role="search">
          <span
            className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary"
            aria-hidden="true"
          >
            search
          </span>
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search articles, topics, and keywords..."
            aria-label="Search articles, topics, and keywords"
            className="w-full border border-slate-200 py-3 pl-10 pr-4 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
      </div>

      {hasSearched && (
        <p className="mb-4 text-sm text-slate-500" aria-live="polite">
          {results.length} result{results.length === 1 ? '' : 's'} for{' '}
          <span className="font-semibold text-slate-800">“{query.trim()}”</span>
        </p>
      )}

      {results.length > 0 ? (
        <ul className="border border-slate-200 bg-white px-4 py-1">
          {results.map((article) => (
            <ArticleListRow
              key={article.id}
              article={article}
              meta={categoryName(article.categoryId)}
            />
          ))}
        </ul>
      ) : hasSearched ? (
        <EmptyState
          icon="search_off"
          title="No results found"
          description={`We couldn't find any articles matching “${query}”. Try a different keyword, or browse by topic.`}
          action={
            <div className="flex flex-col items-center gap-3 sm:flex-row">
              <Link
                href={route('helpdesk.index')}
                className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                Browse topics
              </Link>
              <Link
                href={route('contact')}
                className="rounded-none border border-slate-200 bg-white px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                Contact support
              </Link>
            </div>
          }
        />
      ) : (
        <EmptyState
          icon="search"
          title="Search the help center"
          description="Enter a search term to find articles, guides, and reference pages."
          action={
            <Link
              href={route('helpdesk.index')}
              className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            >
              Browse topics
            </Link>
          }
        />
      )}
    </HelpdeskLayout>
  );
}
