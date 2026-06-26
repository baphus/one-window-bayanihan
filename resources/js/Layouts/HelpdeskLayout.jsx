import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';

import ChatBot from '@/Components/ChatBot';
import AppHeader from '@/Components/landing/AppHeader';
import { categories as categoryData } from '@/data/helpdesk/categories';
import { articles } from '@/data/helpdesk/articles';

function SearchBar({ query, onSearch, large }) {
  const [value, setValue] = useState(query || '');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (value.trim()) {
      onSearch(value.trim());
    }
  };

  return (
    <form onSubmit={handleSubmit} className={`relative w-full ${large ? 'max-w-2xl' : 'max-w-xl'}`}>
      <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">
        search
      </span>
      <input
        type="text"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder="Search the help center..."
        className={`w-full rounded-lg border border-slate-200 bg-white text-slate-900 placeholder-slate-400 shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary ${
          large ? 'py-4 pl-12 pr-4 text-base' : 'py-2.5 pl-10 pr-3 text-sm'
        }`}
      />
    </form>
  );
}

function CategoryNav({ categories, activeSlug }) {
  const hasActiveChild = (cat) => cat.children?.some((ch) => ch.slug === activeSlug);
  const [expanded, setExpanded] = useState(() => {
    if (!categories) return {};
    const map = {};
    categories.forEach((cat) => {
      if (cat.children?.length > 0 && (activeSlug === cat.slug || hasActiveChild(cat))) {
        map[cat.id] = true;
      }
    });
    return map;
  });

  const toggle = (id) => setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));

  return (
    <nav className="space-y-1">
      <Link
        href="/helpdesk"
        className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
          !activeSlug
            ? 'bg-primary/10 text-primary'
            : 'text-slate-600 hover:bg-slate-100'
        }`}
      >
        <span className="material-symbols-outlined text-lg">home</span>
        All Articles
      </Link>
      {categories?.map((cat) => {
        const isActive = activeSlug === cat.slug;
        const hasChildren = cat.children?.length > 0;
        const isExpanded = expanded[cat.id] ?? false;

        return (
          <div key={cat.id}>
            <div className="group flex items-center gap-0">
              <Link
                href={`/helpdesk?category=${cat.slug}`}
                className={`flex flex-1 items-center gap-2 rounded-l-md px-3 py-2 text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary/10 text-primary'
                    : 'text-slate-600 hover:bg-slate-100'
                }`}
              >
                {cat.icon && (
                  <span className="material-symbols-outlined text-lg text-slate-400">
                    {cat.icon}
                  </span>
                )}
                <span>{cat.name}</span>
                {cat.articleCount > 0 && (
                  <span className="ml-auto text-xs text-slate-400">
                    {cat.articleCount}
                  </span>
                )}
              </Link>
              {hasChildren && (
                <button
                  onClick={() => toggle(cat.id)}
                  className={`flex items-center justify-center rounded-r-md px-1.5 py-2 text-sm transition-colors ${
                    isActive
                      ? 'bg-primary/10 text-primary'
                      : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'
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
              <div className="ml-4 space-y-1">
                {cat.children.map((child) => {
                  const isChildActive = activeSlug === child.slug;
                  return (
                    <Link
                      key={child.id}
                      href={`/helpdesk?category=${child.slug}`}
                      className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors ${
                        isChildActive
                          ? 'bg-primary/10 text-primary'
                          : 'text-slate-500 hover:bg-slate-100'
                      }`}
                    >
                      <span>{child.name}</span>
                      {child.articleCount > 0 && (
                        <span className="ml-auto text-xs text-slate-400">{child.articleCount}</span>
                      )}
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

export default function HelpdeskLayout({ title, children, categories: _categories, activeSlug, query, showSearchHero }) {
  // Always compute sidebar from the flat data module, not from the prop
  // (pages may pass a pre-computed parent-only tree which lacks child info)
  const { parentCategories } = useMemo(() => {
    const counts = {};
    articles.forEach(a => {
      counts[a.categoryId] = (counts[a.categoryId] || 0) + 1;
    });

    const children = categoryData.filter(c => c.parentId !== null);
    const topLevel = categoryData.filter(c => c.parentId === null);

    return {
      parentCategories: topLevel.map(parent => ({
        ...parent,
        children: children
          .filter(c => c.parentId === parent.id)
          .map(child => ({
            ...child,
            articleCount: counts[child.id] || 0,
          })),
        articleCount: (counts[parent.id] || 0) + children
          .filter(c => c.parentId === parent.id)
          .reduce((sum, child) => sum + (counts[child.id] || 0), 0),
      })),
    };
  }, [articles]);

  const handleSearch = (q) => {
    window.location.href = '/helpdesk/search?q=' + encodeURIComponent(q);
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <Head title={`${title} - Help Center`} />

      <AppHeader onTrackCaseClick={() => window.location.href = route('track.index')} />

      <ChatBot />

      <div className="mx-auto max-w-7xl px-4 pt-6 pb-2 sm:px-6 lg:px-8">
        {showSearchHero && (
          <div className="mb-6 flex justify-center">
            <SearchBar query={query} onSearch={handleSearch} large />
          </div>
        )}
        {!showSearchHero && (
          <div className="mb-4 flex justify-end">
            <div className="w-full max-w-xs">
              <SearchBar query={query} onSearch={handleSearch} />
            </div>
          </div>
        )}
      </div>

      <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <div className="flex gap-8">
          <aside className="hidden w-64 flex-shrink-0 lg:block">
            <div className="sticky top-24">
              <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                Categories
              </h2>
              <CategoryNav categories={parentCategories} activeSlug={activeSlug} />
            </div>
          </aside>

          <main className="min-w-0 flex-1">{children}</main>
        </div>
      </div>

      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
          <div className="flex flex-col items-center gap-4 text-center sm:flex-row sm:justify-between">
            <div>
              <p className="text-sm font-semibold text-slate-900">Still need help?</p>
              <p className="text-xs text-slate-500">
                Contact our support team and we'll get back to you promptly.
              </p>
            </div>
            <Link
              href={route('contact')}
              className="rounded-md bg-primary px-6 py-2.5 text-xs font-semibold uppercase tracking-widest text-white"
            >
              Contact Support
            </Link>
          </div>
          <div className="mt-6 border-t border-slate-100 pt-4 text-center text-xs text-slate-400">
            &copy; {new Date().getFullYear()} DMW Region VII — One Window Bayanihan
          </div>
        </div>
      </footer>
    </div>
  );
}
