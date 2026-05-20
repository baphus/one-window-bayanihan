import { Head, Link } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import EmptyState from '@/Components/Helpdesk/EmptyState';

export default function Search({ query, results, categories }) {
  const articles = results?.data ?? [];
  const pagination = results ?? {};

  return (
    <HelpdeskLayout title="Search Results" categories={categories} query={query}>
      <Head>
        <title>{query ? `Search: ${query} - Help Center` : 'Search - Help Center'}</title>
      </Head>

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">
          {query ? (
            <>
              Search results for "<span className="text-primary">{query}</span>"
            </>
          ) : (
            'Search the help center'
          )}
        </h1>
        {results?.total > 0 && (
          <p className="mt-1 text-sm text-slate-500">
            {results.total} article{results.total > 1 ? 's' : ''} found
          </p>
        )}
      </div>

      {articles.length > 0 ? (
        <div className="space-y-3">
          {articles.map((article) => (
            <ArticleCard key={article.id} article={article} />
          ))}
        </div>
      ) : (
        <EmptyState
          icon="search_off"
          title="No results found"
          description={
            query
              ? `We couldn't find any articles matching "${query}". Try different keywords or browse by category.`
              : 'Enter a search term to find articles in the help center.'
          }
          action={
            <Link
              href={route('helpdesk.index')}
              className="rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
            >
              Browse All Articles
            </Link>
          }
        />
      )}

      {pagination?.last_page > 1 && (
        <div className="mt-8 flex items-center justify-center gap-2">
          {pagination.links?.map((link, i) => (
            <Link
              key={i}
              href={link.url ? `/helpdesk/search?q=${query}&page=${new URL(link.url).searchParams.get('page') || 1}` : '#'}
              dangerouslySetInnerHTML={{ __html: link.label }}
              className={`rounded-md px-3 py-1.5 text-xs font-semibold ${
                link.active
                  ? 'bg-primary text-white'
                  : link.url
                  ? 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-100'
                  : 'text-slate-300 cursor-default'
              }`}
            />
          ))}
        </div>
      )}
    </HelpdeskLayout>
  );
}
