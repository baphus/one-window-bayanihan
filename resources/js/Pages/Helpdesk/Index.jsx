import { useMemo } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import HelpdeskLayout, { SearchBar } from '@/Layouts/HelpdeskLayout';
import ArticleListRow from '@/Components/Helpdesk/ArticleListRow';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import EmptyState from '@/Components/Helpdesk/EmptyState';

import { articles as allArticles } from '@/data/helpdesk/articles';
import {
  categories as allCategories,
  buildCategoryTree,
  getDescendantCategoryIds,
} from '@/data/helpdesk/categories';
import { resolvePopularArticles } from '@/data/helpdesk/popular';
import { audienceEntries } from '@/data/helpdesk/audiences';

function sortByPublishedDesc(a, b) {
  return new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime();
}

function findCategoryBySlug(slug) {
  return allCategories.find((category) => category.slug === slug);
}

function handleSearch(q) {
  router.visit('/help/search?q=' + encodeURIComponent(q));
}

// ---------------------------------------------------------------------------
// Landing view (/help with no category) — a router, not a library:
// search hero, audience row, topic cards, curated popular list.
// The single contact CTA is the layout footer.
// ---------------------------------------------------------------------------
function LandingView({ categoryTree }) {
  const popular = useMemo(() => resolvePopularArticles(allArticles), []);
  return (
    <div className="mx-auto max-w-5xl">
      <div data-tour="helpdesk-header" className="flex flex-col items-center pb-10 pt-6 text-center">
        <p className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
          Help Center
        </p>
        <h1 className="mt-2 font-headline text-3xl font-bold text-slate-900 sm:text-4xl">
          How can we help you?
        </h1>
        <p className="mt-3 max-w-2xl text-sm text-slate-500 sm:text-base">
          Search our guides, or browse by topic below.
        </p>
        <div data-tour="helpdesk-search" className="mt-6 flex w-full justify-center">
          <SearchBar onSearch={handleSearch} large />
        </div>
      </div>

      <section data-tour="helpdesk-audiences" aria-labelledby="audience-heading" className="mb-10">
        <h2 id="audience-heading" className="mb-3 font-headline text-lg font-bold text-slate-900">
          Find help for you
        </h2>
        <div className="grid gap-3 sm:grid-cols-3">
          {audienceEntries.map((entry) => (
            <Link
              key={entry.key}
              href={entry.href}
              className="group flex min-w-0 items-start gap-3 border border-slate-200 bg-white p-4 transition-colors hover:border-primary/40 hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
              <span
                className="flex h-11 w-11 flex-shrink-0 items-center justify-center border border-slate-200 bg-surface-container-low text-primary"
                aria-hidden="true"
              >
                <span className="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">{entry.icon}</span>
              </span>
              <span className="min-w-0">
                <span className="block font-headline text-sm font-bold text-slate-900 group-hover:text-primary">
                  {entry.label}
                </span>
                <span className="mt-1 block text-sm leading-relaxed text-slate-500">
                  {entry.description}
                </span>
              </span>
            </Link>
          ))}
        </div>
      </section>

      <section data-tour="helpdesk-categories" aria-labelledby="topics-heading" className="mb-10">
        <h2 id="topics-heading" className="mb-3 font-headline text-lg font-bold text-slate-900">
          Browse topics
        </h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {categoryTree.map((category) => (
            <Link
              key={category.id}
              href={`/help?category=${category.slug}`}
              className="group min-w-0 border border-slate-200 bg-white p-5 transition-colors hover:border-primary/40 hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
              <div className="flex items-center gap-3">
                {category.icon && (
                  <span className="material-symbols-outlined text-2xl text-primary" aria-hidden="true">
                    {category.icon}
                  </span>
                )}
                <span className="font-headline text-base font-bold text-slate-900 group-hover:text-primary">
                  {category.name}
                </span>
              </div>
              <p className="mt-2 text-sm leading-relaxed text-slate-500 line-clamp-2">
                {category.description}
              </p>
              <p className="mt-3 text-xs font-semibold text-slate-600">
                {category.articleCount} article{category.articleCount === 1 ? '' : 's'}
              </p>
            </Link>
          ))}
        </div>
      </section>

      {popular.length > 0 && (
        <section data-tour="helpdesk-popular" aria-labelledby="popular-heading" className="mb-6">
          <h2 id="popular-heading" className="mb-3 font-headline text-lg font-bold text-slate-900">
            Popular articles
          </h2>
          <ul className="border border-slate-200 bg-white px-4 py-1">
            {popular.map((article) => (
              <ArticleListRow key={article.id} article={article} />
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Category view (/help?category=slug) — header + plain grouped article lists.
// ---------------------------------------------------------------------------
function ArticleList({ articles }) {
  return (
    <ul className="border border-slate-200 bg-white px-4 py-1">
      {articles.map((article) => (
        <ArticleListRow key={article.id} article={article} />
      ))}
    </ul>
  );
}

function CategoryView({ category, categoryTree }) {
  const isSubcategory = Boolean(category.parentId);
  const parent = isSubcategory
    ? allCategories.find((c) => c.id === category.parentId)
    : null;

  const breadcrumbItems = isSubcategory
    ? [
        { label: parent?.name || 'Category', href: `/help?category=${parent?.slug}` },
        { label: category.name },
      ]
    : [{ label: category.name }];

  const descendantIds = useMemo(
    () => new Set([category.id, ...getDescendantCategoryIds(allCategories, category.id)]),
    [category.id]
  );

  const totalCount = useMemo(
    () => allArticles.filter((a) => descendantIds.has(a.categoryId)).length,
    [descendantIds]
  );

  const directArticles = useMemo(
    () => allArticles.filter((a) => a.categoryId === category.id).sort(sortByPublishedDesc),
    [category.id]
  );

  const subcategories = useMemo(() => {
    if (isSubcategory) return [];
    const node = categoryTree.find((c) => c.id === category.id);
    return node?.children || [];
  }, [categoryTree, category.id, isSubcategory]);

  const articlesForSubcategory = (subcategoryId) =>
    allArticles.filter((a) => a.categoryId === subcategoryId).sort(sortByPublishedDesc);

  return (
    <div>
      <Breadcrumbs items={breadcrumbItems} />

      <header className="mb-8">
        <div className="flex items-center gap-3">
          {category.icon && (
            <span className="material-symbols-outlined text-3xl text-primary" aria-hidden="true">
              {category.icon}
            </span>
          )}
          <h1 className="font-headline text-2xl font-bold text-slate-900 sm:text-3xl">
            {category.name}
          </h1>
        </div>
        {category.description && (
          <p className="mt-2 max-w-3xl text-sm text-slate-500 sm:text-base">{category.description}</p>
        )}
        <p className="mt-2 text-xs font-semibold text-slate-600">
          {totalCount} article{totalCount === 1 ? '' : 's'}
        </p>
      </header>

      {totalCount === 0 ? (
        <EmptyState
          icon="library_books"
          title="No articles here yet"
          description="Articles for this topic will appear here once published."
          action={
            <div className="flex flex-col items-center gap-3 sm:flex-row">
              <Link
                href={route('helpdesk.search')}
                className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                Search help center
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
        <div className="space-y-8">
          {directArticles.length > 0 && <ArticleList articles={directArticles} />}

          {subcategories.map((sub) => {
            const subArticles = articlesForSubcategory(sub.id);

            return (
              <section key={sub.id} aria-labelledby={`subcategory-${sub.id}`}>
                <div className="mb-3 flex items-baseline justify-between gap-3">
                  <h2 id={`subcategory-${sub.id}`} className="font-headline text-lg font-bold text-slate-900">
                    <Link
                      href={`/help?category=${sub.slug}`}
                      className="transition-colors hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    >
                      {sub.name}
                    </Link>
                  </h2>
                  <span className="text-xs text-slate-600">
                    {subArticles.length} article{subArticles.length === 1 ? '' : 's'}
                  </span>
                </div>
                {sub.description && (
                  <p className="mb-3 text-sm text-slate-500">{sub.description}</p>
                )}
                {subArticles.length > 0 ? (
                  <ArticleList articles={subArticles} />
                ) : (
                  <p className="border border-slate-200 bg-white px-4 py-4 text-sm text-slate-500">
                    No published articles yet.
                  </p>
                )}
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default function Index() {
  const { category: categorySlug } = usePage().props;

  const categoryTree = useMemo(() => buildCategoryTree(allCategories, allArticles), []);
  const selectedCategory = useMemo(
    () => (categorySlug ? findCategoryBySlug(categorySlug) : null),
    [categorySlug]
  );

  // Unknown category slug → not-found state within the browse layout.
  if (categorySlug && !selectedCategory) {
    return (
      <HelpdeskLayout title="Help Center" activeSlug={categorySlug}>
        <EmptyState
          icon="folder_off"
          title="Category not found"
          description="The category you are looking for does not exist."
          action={
            <div className="flex flex-col items-center gap-3 sm:flex-row">
              <Link
                href={route('helpdesk.index')}
                className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
              >
                Back to Help Center
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
      </HelpdeskLayout>
    );
  }

  // Landing page: no sidebar, no compact search — the hero search is the
  // single entry point. Density here never grows with article count.
  if (!selectedCategory) {
    return (
      <HelpdeskLayout title="Help Center" showSidebar={false} showCompactSearch={false}>
        <LandingView categoryTree={categoryTree} />
      </HelpdeskLayout>
    );
  }

  return (
    <HelpdeskLayout title={selectedCategory.name} activeSlug={categorySlug}>
      <CategoryView category={selectedCategory} categoryTree={categoryTree} />
    </HelpdeskLayout>
  );
}
