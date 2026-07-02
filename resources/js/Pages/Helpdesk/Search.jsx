import { useState, useEffect } from 'react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import EmptyState from '@/Components/Helpdesk/EmptyState';
import { searchArticles } from '@/data/helpdesk/search';
import { categories } from '@/data/helpdesk/categories';

function resolveCategory(categoryId) {
  const cat = categories.find((c) => c.id === categoryId);
  return cat ? { name: cat.name, icon: cat.icon } : undefined;
}

function enrichArticle(article) {
  return {
    ...article,
    category: resolveCategory(article.categoryId),
    updated_at: article.publishedAt,
  };
}

export default function Search() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [hasSearched, setHasSearched] = useState(false);

  // Read initial query from URL on mount
  useEffect(() => {
    const q = new URLSearchParams(window.location.search).get('q') || '';
    setQuery(q);
    if (q.trim()) {
      const hits = searchArticles(q);
      setResults(hits.map(enrichArticle));
      setHasSearched(true);
    }
  }, []);

  // Debounced search when query changes (300ms)
  useEffect(() => {
    if (!query.trim()) {
      setResults([]);
      setHasSearched(false);
      return;
    }

    const timer = setTimeout(() => {
      const hits = searchArticles(query);
      setResults(hits.map(enrichArticle));
      setHasSearched(true);
    }, 300);

    return () => clearTimeout(timer);
  }, [query]);

  return (
    <HelpdeskLayout
      title={query ? `Search: ${query}` : 'Search Results'}
      query={query}
      showSearchHero={false}
    >
      <div className="mb-6">
        <div className="relative">
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            search
          </span>
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search articles..."
            className="w-full rounded-lg border border-slate-200 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
      </div>

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">
          {query ? (
            <>
              Search results for &ldquo;<span className="text-primary">{query}</span>&rdquo;
            </>
          ) : (
            'Search the help center'
          )}
        </h1>
        {hasSearched && results.length > 0 && (
          <p className="mt-1 text-sm text-slate-500">
            {results.length} article{results.length > 1 ? 's' : ''} found
          </p>
        )}
      </div>

      {results.length > 0 ? (
        <div className="space-y-3">
          {results.map((article) => (
            <ArticleCard key={article.id} article={article} />
          ))}
        </div>
      ) : hasSearched ? (
        <EmptyState
          icon="search_off"
          title="No results found"
          description={`We couldn't find any articles matching "${query}". Try different keywords or browse by category.`}
          action={
            <a
              href="/helpdesk"
              className="rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
            >
              Browse All Articles
            </a>
          }
        />
      ) : (
        <EmptyState
          icon="search"
          title="Search the help center"
          description="Enter a search term to find articles in the help center."
        />
      )}
    </HelpdeskLayout>
  );
}
