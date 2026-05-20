import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useForm } from '@inertiajs/react';

function SearchBar({ query, onSearch }) {
  const [value, setValue] = useState(query || '');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (value.trim()) {
      onSearch(value.trim());
    }
  };

  return (
    <form onSubmit={handleSubmit} className="relative w-full max-w-2xl">
      <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl">
        search
      </span>
      <input
        type="text"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder="Search the help center..."
        className="w-full rounded-lg border border-slate-200 bg-white py-3 pl-12 pr-4 text-sm text-slate-900 placeholder-slate-400 shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
      />
    </form>
  );
}

function CategoryNav({ categories, activeSlug }) {
  return (
    <nav className="space-y-1">
      <Link
        href={route('helpdesk.index')}
        className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
          !activeSlug
            ? 'bg-primary/10 text-primary'
            : 'text-slate-600 hover:bg-slate-100'
        }`}
      >
        <span className="material-symbols-outlined text-lg">home</span>
        All Articles
      </Link>
      {categories?.map((cat) => (
        <div key={cat.id}>
          <Link
            href={`/helpdesk?category=${cat.slug}`}
            className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
              activeSlug === cat.slug
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
            {cat.published_articles_count > 0 && (
              <span className="ml-auto text-xs text-slate-400">
                {cat.published_articles_count}
              </span>
            )}
          </Link>
          {cat.children?.length > 0 && (
            <div className="ml-4 space-y-1">
              {cat.children.map((child) => (
                <Link
                  key={child.id}
                  href={`/helpdesk?category=${child.slug}`}
                  className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors ${
                    activeSlug === child.slug
                      ? 'bg-primary/10 text-primary'
                      : 'text-slate-500 hover:bg-slate-100'
                  }`}
                >
                  {child.name}
                </Link>
              ))}
            </div>
          )}
        </div>
      ))}
    </nav>
  );
}

export default function HelpdeskLayout({ title, children, categories, activeSlug, query }) {
  const { auth } = usePage().props;

  const handleSearch = (q) => {
    window.location.href = route('helpdesk.search', { q });
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <Head title={`${title} - Help Center`} />

      <header className="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
          <Link href="/helpdesk" className="flex items-center gap-2">
            <span className="material-symbols-outlined text-primary text-2xl">help</span>
            <span className="text-lg font-bold text-slate-900">Help Center</span>
          </Link>
          <div className="flex items-center gap-4">
            <div className="hidden sm:block">
              <SearchBar query={query} onSearch={handleSearch} />
            </div>
            {auth.user ? (
              <Link
                href={route('dashboard')}
                className="rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
              >
                Dashboard
              </Link>
            ) : (
              <Link
                href={route('login')}
                className="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700"
              >
                Sign In
              </Link>
            )}
          </div>
        </div>
        <div className="border-t border-slate-100 sm:hidden">
          <div className="mx-auto max-w-7xl px-4 py-2">
            <SearchBar query={query} onSearch={handleSearch} />
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="flex gap-8">
          <aside className="hidden w-64 flex-shrink-0 lg:block">
            <div className="sticky top-24">
              <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                Categories
              </h2>
              <CategoryNav categories={categories} activeSlug={activeSlug} />
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
