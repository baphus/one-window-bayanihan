import { useMemo } from 'react';
import { Link, usePage } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import TagBadge from '@/Components/Helpdesk/TagBadge';
import EmptyState from '@/Components/Helpdesk/EmptyState';

import { articles as allArticles } from '@/data/helpdesk/articles';
import {
  categories as allCategories,
  buildCategoryTree,
  getDescendantCategoryIds,
} from '@/data/helpdesk/categories';
import { tags as allTags } from '@/data/helpdesk/tags';

function sortByPublishedDesc(a, b) {
  return new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime();
}

function groupByCategory(articles) {
  return articles.reduce((acc, article) => {
    if (!acc[article.categoryId]) {
      acc[article.categoryId] = [];
    }
    acc[article.categoryId].push(article);
    return acc;
  }, {});
}

function findCategoryBySlug(slug) {
  return allCategories.find((category) => category.slug === slug);
}

function scopeTree(tree, selectedCategory) {
  if (!selectedCategory) return tree;

  if (!selectedCategory.parentId) {
    return tree.filter((parent) => parent.id === selectedCategory.id);
  }

  return tree
    .filter((parent) => parent.id === selectedCategory.parentId)
    .map((parent) => ({
      ...parent,
      children: parent.children.filter((child) => child.id === selectedCategory.id),
    }));
}

export default function Index() {
  const { auth, category: categorySlug } = usePage().props;
  const user = auth?.user;

  const categoryTree = useMemo(
    () => buildCategoryTree(allCategories, allArticles),
    []
  );
  const selectedCategory = useMemo(
    () => (categorySlug ? findCategoryBySlug(categorySlug) : null),
    [categorySlug]
  );

  const selectedCategoryIds = useMemo(() => {
    if (!selectedCategory) return null;

    return new Set([
      selectedCategory.id,
      ...getDescendantCategoryIds(allCategories, selectedCategory.id),
    ]);
  }, [selectedCategory]);

  const visibleArticles = useMemo(() => {
    if (!selectedCategoryIds) return allArticles;
    return allArticles.filter((article) => selectedCategoryIds.has(article.categoryId));
  }, [selectedCategoryIds]);

  const visibleTree = useMemo(() => scopeTree(categoryTree, selectedCategory), [
    categoryTree,
    selectedCategory,
  ]);

  const articlesByCategory = useMemo(
    () => groupByCategory(visibleArticles),
    [visibleArticles]
  );

  const quickTasks = [
    {
      title: 'Track your case',
      description: 'Open the public tracking portal for case updates and status checks.',
      href: route('track.index'),
      icon: 'confirmation_number',
      emphasis: true,
    },
    {
      title: 'Browse help topics',
      description: 'Jump to the help center topics and browse by category.',
      href: `${route('helpdesk.index')}#topics`,
      icon: 'topic',
    },
    {
      title: 'Contact support',
      description: 'Send a message if you need help finding the right guide.',
      href: route('contact'),
      icon: 'support_agent',
    },
  ];

  const roleGreeting = {
    OFW: { title: 'OFW Resources', icon: 'badge' },
    CASE_MANAGER: { title: 'Case Manager Guides', icon: 'folder' },
    AGENCY: { title: 'Agency Partner Guides', icon: 'handshake' },
    ADMIN: { title: 'Administration', icon: 'settings' },
  };

  const roleInfo = user ? roleGreeting[user.role] : null;

  if (categorySlug && !selectedCategory) {
    return (
      <HelpdeskLayout title="Help Center" activeSlug={categorySlug} showSearchHero>
        <EmptyState
          icon="folder_off"
          title="Category not found"
          description="The category you are looking for does not exist."
          action={
            <div className="flex flex-col items-center gap-3 sm:flex-row">
              <Link
                href={route('helpdesk.index')}
                className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white"
              >
                Browse all articles
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
      </HelpdeskLayout>
    );
  }

  return (
    <HelpdeskLayout title="Help Center" activeSlug={categorySlug} showSearchHero>
      <div className="mb-8 space-y-6">
        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div className="grid gap-5 lg:grid-cols-[1.4fr_0.6fr] lg:items-end">
            <div>
              <p className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
                Help Center
              </p>
              <h1 className="mt-2 font-headline text-3xl font-bold text-slate-900">
                How can we help you?
              </h1>
              <p className="mt-2 max-w-3xl text-sm text-slate-500">
                Browse the full help center by category, subcategory, and article, or use search to
                find the exact guide you need.
              </p>
            </div>

            <div className="flex flex-wrap gap-2 lg:justify-end">
              <Link
                href={route('helpdesk.search')}
                className="inline-flex items-center gap-2 rounded-none border border-primary bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white transition-colors hover:bg-[#00446f]"
              >
                <span className="material-symbols-outlined text-sm">search</span>
                Search articles
              </Link>
            </div>
          </div>
        </div>

        {!selectedCategory && (
          <div className="grid gap-3 md:grid-cols-3">
            {quickTasks.map((task) => (
              <Link
                key={task.title}
                href={task.href}
                className={`group rounded-lg border border-slate-200 bg-white p-4 transition-colors hover:border-primary/30 hover:bg-surface-container-low ${
                  task.emphasis ? 'border-primary bg-primary/5' : ''
                }`}
              >
                <div className="flex items-start gap-3">
                  <div className={`flex h-11 w-11 items-center justify-center border ${task.emphasis ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-surface-container-low text-primary'}`}>
                    <span className="material-symbols-outlined text-[20px]">{task.icon}</span>
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="font-headline text-sm font-bold text-slate-900">{task.title}</p>
                    <p className="mt-1 text-xs leading-relaxed text-slate-500">{task.description}</p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}

        {roleInfo && (
          <div className="border-l-4 border-primary bg-primary/10 p-4">
            <div className="flex items-center gap-3">
              <span className="material-symbols-outlined text-2xl text-primary">{roleInfo.icon}</span>
              <div>
                <p className="font-headline text-sm font-bold text-slate-900">{roleInfo.title}</p>
                <p className="text-xs text-slate-500">Quick access to guides tailored for your role.</p>
              </div>
            </div>
          </div>
        )}

        {selectedCategory && (
          <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
                  Browsing category
                </p>
                <div className="mt-1 flex items-center gap-2">
                  {selectedCategory.icon && (
                    <span className="material-symbols-outlined text-xl text-slate-500">
                      {selectedCategory.icon}
                    </span>
                  )}
                  <h2 className="font-headline text-lg font-bold text-slate-900">{selectedCategory.name}</h2>
                </div>
                {selectedCategory.description && (
                  <p className="mt-1 text-sm text-slate-500">{selectedCategory.description}</p>
                )}
              </div>

              <Link
                href={route('helpdesk.index')}
                className="inline-flex items-center justify-center rounded-none border border-slate-200 px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition-colors hover:border-primary/30 hover:text-primary"
              >
                Clear filter
              </Link>
            </div>
          </div>
        )}
      </div>

      {visibleTree.length > 0 ? (
        <div id="topics" className="space-y-10 scroll-mt-28">
          {visibleTree.map((parent) => {
            const parentArticles = (articlesByCategory[parent.id] || []).sort(sortByPublishedDesc);

            return (
              <section
                key={parent.id}
                className="overflow-hidden rounded-lg border border-slate-200 bg-white"
              >
                <div className="border-b border-slate-200 px-5 py-5 sm:px-6">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                      <div className="flex items-center gap-3">
                        {parent.icon && (
                          <span className="material-symbols-outlined text-2xl text-primary">
                            {parent.icon}
                          </span>
                        )}
                        <div>
                          <h2 className="font-headline text-xl font-bold text-slate-900">{parent.name}</h2>
                          <p className="mt-1 max-w-3xl text-sm text-slate-500">{parent.description}</p>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 text-xs text-slate-500">
                      <Link
                        href={`/help?category=${parent.slug}`}
                        className="rounded-none border border-slate-200 px-3 py-1 font-label text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700 transition-colors hover:border-primary/30 hover:text-primary"
                      >
                        Open category
                      </Link>
                    </div>
                  </div>
                </div>

                <div className="space-y-6 p-5 sm:p-6">
                  {parentArticles.length > 0 && (
                    <div>
                      <div className="mb-3 flex items-center justify-between gap-3">
                        <h3 className="font-label text-[11px] font-bold uppercase tracking-[0.18em] text-slate-600">Category articles</h3>
                      </div>
                      <div className="grid gap-3 lg:grid-cols-2">
                        {parentArticles.map((article) => (
                          <ArticleCard key={article.id} article={article} variant="compact" />
                        ))}
                      </div>
                    </div>
                  )}

                  {parent.children.length > 0 ? (
                    <div className="grid gap-4 xl:grid-cols-2">
                      {parent.children.map((child) => {
                        const childArticles = (articlesByCategory[child.id] || []).sort(
                          sortByPublishedDesc
                        );

                        return (
                          <div key={child.id} className="rounded-lg border border-slate-200 bg-surface-container-low p-4">
                            <div className="flex items-start justify-between gap-3">
                              <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                  {child.icon && (
                                    <span className="material-symbols-outlined text-lg text-primary">
                                      {child.icon}
                                    </span>
                                  )}
                                  <h3 className="font-headline text-sm font-bold text-slate-900">{child.name}</h3>
                                </div>
                                {child.description && (
                                  <p className="mt-1 text-xs text-slate-500">{child.description}</p>
                                )}
                              </div>

                              <Link
                                href={`/help?category=${child.slug}`}
                                className="rounded-none border border-slate-200 bg-white px-2.5 py-1 font-label text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700 transition-colors hover:border-primary/30 hover:text-primary"
                              >
                                Open
                              </Link>
                            </div>

                            <div className="mt-4 bg-white px-3">
                              {childArticles.length > 0 ? (
                                childArticles.map((article) => (
                                  <ArticleCard
                                    key={article.id}
                                    article={article}
                                    variant="compact"
                                  />
                                ))
                              ) : (
                                <div className="py-4 text-xs text-slate-500">
                                  No published articles yet.
                                </div>
                              )}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                    <EmptyState
                      icon="library_books"
                      title="No subcategories yet"
                      description="This category will appear here once subcategories and articles are added."
                      action={
                        <div className="flex flex-col items-center gap-3 sm:flex-row">
                          <Link
                            href={route('helpdesk.search')}
                            className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white"
                          >
                            Search help center
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
                  )}
                </div>
              </section>
            );
          })}
        </div>
      ) : (
        <EmptyState
          icon="menu_book"
          title="Help center is being set up"
          description="Articles will appear here once published. Check back soon!"
          action={
            <div className="flex flex-col items-center gap-3 sm:flex-row">
              <Link
                href={route('helpdesk.search')}
                className="rounded-none bg-primary px-4 py-2 font-label text-xs font-semibold uppercase tracking-[0.18em] text-white"
              >
                Search help center
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
      )}

      {allTags.length > 0 && (
        <section className="mt-10">
          <h2 className="mb-3 font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
            Browse by Tag
          </h2>
          <div className="flex flex-wrap gap-2">
            {allTags.map((tag) => (
              <TagBadge key={tag.id} tag={tag} />
            ))}
          </div>
        </section>
      )}

    </HelpdeskLayout>
  );
}
