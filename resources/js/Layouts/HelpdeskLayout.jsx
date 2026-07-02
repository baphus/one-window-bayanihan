import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useEffect } from 'react';

import ChatBot from '@/Components/ChatBot';
import AppHeader from '@/Components/landing/AppHeader';
import { categories as categoryData, buildCategoryTree } from '@/data/helpdesk/categories';
import { articles } from '@/data/helpdesk/articles';
import { searchArticles } from '@/data/helpdesk/search';

function SearchBar({ query, onSearch, large }) {
  const [value, setValue] = useState(query || '');

  useEffect(() => {
    setValue(query || '');
  }, [query]);

  const suggestions = useMemo(() => {
    const trimmed = value.trim();
    if (!large || trimmed.length < 2) return [];
    return searchArticles(trimmed, 4);
  }, [value, large]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (value.trim()) {
      onSearch(value.trim());
    }
  };

  return (
    <div className={`w-full relative ${large ? 'max-w-3xl' : 'max-w-xl'}`}>
      <form onSubmit={handleSubmit}>
        <div className={`flex w-full border border-slate-200 bg-white ${large ? 'min-h-14' : 'min-h-11'}`}>
          <div className={`flex items-center justify-center border-r border-slate-200 px-4 text-primary ${large ? 'bg-primary/5' : 'bg-slate-50'}`}>
            <span className="material-symbols-outlined text-xl">search</span>
          </div>
          <input
            type="text"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder="Search the help center..."
            className={`min-w-0 flex-1 border-0 bg-transparent px-4 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0 ${
              large ? 'text-base' : 'text-sm'
            }`}
          />
          <button
            type="submit"
            className={`border-l border-slate-200 bg-primary px-5 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-[#00446f] ${large ? 'px-6' : ''}`}
          >
            Search
          </button>
        </div>
      </form>

      {large && suggestions.length > 0 && (
        <div className="absolute left-0 right-0 top-full z-50 mt-1 rounded-b-lg border border-t-0 border-slate-200 bg-white shadow-xl">
          <div className="border-b border-slate-200 px-4 py-2">
            <p className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
              Quick matches
            </p>
          </div>
          <div className="divide-y divide-slate-100">
            {suggestions.map((article) => {
              const category = categoryData.find((c) => c.id === article.categoryId);

              return (
                <Link
                  key={article.id}
                  href={`/helpdesk/${article.slug}`}
                  className="block px-4 py-3 transition-colors hover:bg-slate-50"
                >
                  <div className="flex items-start gap-3">
                    <span className="material-symbols-outlined mt-0.5 text-sm text-primary">article</span>
                    <div className="min-w-0 flex-1">
                      <p className="font-headline text-sm font-semibold text-slate-900">{article.title}</p>
                      <p className="mt-0.5 text-xs text-slate-500 line-clamp-1">{article.excerpt}</p>
                      {category && (
                        <p className="mt-1 font-label text-[11px] uppercase tracking-[0.12em] text-slate-400">
                          {category.name}
                        </p>
                      )}
                    </div>
                  </div>
                </Link>
              );
            })}
          </div>
          <div className="border-t border-slate-200 px-4 py-2">
            <Link
              href={route('helpdesk.search') + '?q=' + encodeURIComponent(value.trim())}
              className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary hover:underline"
            >
              View all results
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}

function CategoryNav({ categories, activeSlug }) {
  const hasActiveChild = (cat) => cat.children?.some((ch) => ch.slug === activeSlug);
  const getExpandedForActiveSlug = () => {
    if (!categories) return {};
    const map = {};
    categories.forEach((cat) => {
      if (cat.children?.length > 0 && (activeSlug === cat.slug || hasActiveChild(cat))) {
        map[cat.id] = true;
      }
    });
    return map;
  };

  const [expanded, setExpanded] = useState(getExpandedForActiveSlug);

  useEffect(() => {
    setExpanded((prev) => ({ ...prev, ...getExpandedForActiveSlug() }));
  }, [categories, activeSlug]);

  const toggle = (id) => setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));

  return (
    <nav className="space-y-1">
      <Link
        href="/helpdesk"
        className={`flex items-center gap-3 border-l-4 px-3 py-2.5 text-sm font-label transition-colors ${
          !activeSlug
            ? 'border-primary bg-primary/10 text-primary font-bold'
            : 'border-transparent text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:text-primary'
        }`}
      >
        <span className="material-symbols-outlined text-base">home</span>
        All Articles
      </Link>
      {categories?.map((cat) => {
        const isActive = activeSlug === cat.slug;
        const isParentActive = isActive || hasActiveChild(cat);
        const hasChildren = cat.children?.length > 0;
        const isExpanded = expanded[cat.id] ?? false;

        return (
          <div key={cat.id} className="border-b border-slate-200 last:border-b-0">
            <div className="group flex items-stretch gap-0">
              <Link
                href={`/helpdesk?category=${cat.slug}`}
                className={`flex flex-1 items-center gap-3 border-l-4 px-3 py-2.5 text-sm font-label transition-colors ${
                  isParentActive
                    ? 'border-primary bg-primary/10 text-primary font-bold'
                    : 'border-transparent text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:text-primary'
                }`}
              >
                {cat.icon && (
                  <span className="material-symbols-outlined text-base text-slate-400">
                    {cat.icon}
                  </span>
                )}
                <span className="min-w-0 truncate">{cat.name}</span>
              </Link>
              {hasChildren && (
                <button
                  onClick={() => toggle(cat.id)}
                  className={`flex items-center justify-center border-l border-slate-200 px-2.5 text-sm transition-colors ${
                    isParentActive
                      ? 'bg-primary/10 text-primary'
                      : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600'
                  }`}
                  title={isExpanded ? 'Collapse' : 'Expand'}
                >
                  <span className={`material-symbols-outlined text-base transition-transform duration-200 ${isExpanded ? 'rotate-90' : ''}`}>
                    {isExpanded ? 'expand_more' : 'chevron_right'}
                  </span>
                </button>
              )}
            </div>
            {hasChildren && isExpanded && (
              <div className="ml-4 border-l border-slate-200 py-1 pl-3">
                {cat.children.map((child) => {
                  const isChildActive = activeSlug === child.slug;
                  return (
                    <Link
                      key={child.id}
                      href={`/helpdesk?category=${child.slug}`}
                      className={`flex items-center gap-2 border-l-4 px-3 py-2 text-sm font-label transition-colors ${
                        isChildActive
                          ? 'border-primary bg-primary/10 text-primary font-bold'
                          : 'border-transparent text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-primary'
                      }`}
                    >
                      <span className="min-w-0 truncate">{child.name}</span>
                    </Link>
                  );
                })}
              </div>
            )}
          </div>
        );
      })}
    </nav>
  );
}

export default function HelpdeskLayout({ title, children, activeSlug, query, showSearchHero }) {
  // Always compute sidebar from the flat data module, not from page props.
  const parentCategories = useMemo(() => buildCategoryTree(categoryData, articles), []);

  const handleSearch = (q) => {
    router.visit('/helpdesk/search?q=' + encodeURIComponent(q));
  };

  return (
    <div className="min-h-screen bg-surface font-body text-on-surface">
      <Head title={`${title} - Help Center`} />

      <AppHeader onTrackCaseClick={() => router.visit(route('track.index'))} />

      <ChatBot />

      <div className="mx-auto max-w-7xl px-4 pb-2 pt-24 sm:px-6 lg:px-8">
        {showSearchHero && (
          <div className="mb-8 flex justify-center pt-4">
            <SearchBar query={query} onSearch={handleSearch} large />
          </div>
        )}
        {!showSearchHero && (
          <div className="mb-4 flex justify-end pt-3">
            <div className="w-full max-w-xs">
              <SearchBar query={query} onSearch={handleSearch} />
            </div>
          </div>
        )}
      </div>

      <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <div className="flex gap-8">
          <aside className="hidden w-72 flex-shrink-0 lg:block">
            <div className="sticky top-24 rounded-lg border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-200 bg-surface-container-low px-4 py-3">
                <h2 className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
                  Browse topics
                </h2>
                <p className="mt-1 text-xs text-slate-500">Navigate categories, subcategories, and articles.</p>
              </div>
              <div className="px-2 py-3">
                <CategoryNav categories={parentCategories} activeSlug={activeSlug} />
              </div>
            </div>
          </aside>

          <main className="min-w-0 flex-1">{children}</main>
        </div>
      </div>

      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
          <div className="flex flex-col items-center gap-4 text-center sm:flex-row sm:justify-between">
            <div>
              <p className="font-headline text-sm font-bold text-slate-900">Still need help?</p>
              <p className="text-xs text-slate-500">
                Contact our support team and we'll get back to you promptly.
              </p>
            </div>
            <Link
              href={route('contact')}
              className="rounded-none bg-primary px-6 py-2.5 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white"
            >
              Contact Support
            </Link>
          </div>
          <div className="mt-6 border-t border-slate-200 pt-4 text-center text-xs text-slate-400">
            &copy; {new Date().getFullYear()} DMW Region VII — One Window Bayanihan
          </div>
        </div>
      </footer>
    </div>
  );
}
