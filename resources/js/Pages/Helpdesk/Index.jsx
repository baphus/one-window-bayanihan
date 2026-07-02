import { useMemo } from 'react';
import { Link, usePage } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import TagBadge from '@/Components/Helpdesk/TagBadge';
import EmptyState from '@/Components/Helpdesk/EmptyState';

import { articles as allArticles } from '@/data/helpdesk/articles';
import { categories as allCategories } from '@/data/helpdesk/categories';
import { tags as allTags } from '@/data/helpdesk/tags';

export default function Index() {
  const { auth, category: categorySlug } = usePage().props;
  const user = auth?.user;

  // ── Resolve category name/icon for each article, with optional category filter ─
  const articlesWithCategory = useMemo(() => {
    let filtered = allArticles;

    if (categorySlug) {
      const cat = allCategories.find((c) => c.slug === categorySlug);
      if (cat) {
        const descendantIds = [
          cat.id,
          ...allCategories.filter((c) => c.parentId === cat.id).map((c) => c.id),
        ];
        filtered = allArticles.filter((a) => descendantIds.includes(a.categoryId));
      } else {
        filtered = [];
      }
    }

    return filtered.map((article) => {
      const category = allCategories.find((c) => c.id === article.categoryId);
      return {
        ...article,
        updated_at: article.publishedAt,
        category: category ? { name: category.name, icon: category.icon } : undefined,
      };
    });
  }, [categorySlug, allArticles, allCategories]);

  // ── Featured articles (3 with featured: true) ──────────────────────────────
  const featuredArticles = articlesWithCategory.filter((a) => a.featured);

  // ── Non-featured articles for recent / popular sections ────────────────────
  const nonFeatured = articlesWithCategory.filter((a) => !a.featured);
  const recentArticles = [...nonFeatured].sort(
    (a, b) => new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime()
  );

  // ── Build hierarchical categories with article counts ──────────────────────
  const parentCategories = allCategories.filter((c) => c.parentId === null);

  const hierarchicalCategories = parentCategories.map((parent) => {
    const children = allCategories
      .filter((c) => c.parentId === parent.id)
      .map((child) => {
        const count = allArticles.filter((a) => a.categoryId === child.id).length;
        return { ...child, published_articles_count: count };
      });
    const totalArticles = children.reduce((sum, c) => sum + c.published_articles_count, 0);
    return {
      ...parent,
      children,
      total_articles: totalArticles,
    };
  });

  // ── Role-based greeting ────────────────────────────────────────────────────
  const roleGreeting = {
    OFW: { title: 'OFW Resources', icon: 'badge' },
    CASE_MANAGER: { title: 'Case Manager Guides', icon: 'folder' },
    AGENCY: { title: 'Agency Partner Guides', icon: 'handshake' },
    ADMIN: { title: 'Administration', icon: 'settings' },
  };

  const roleInfo = user ? roleGreeting[user.role] : null;

  const hasContent = featuredArticles.length > 0 || hierarchicalCategories.length > 0;

  return (
    <HelpdeskLayout title="Help Center" categories={hierarchicalCategories} showSearchHero>
      <div className="mb-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-slate-900">How can we help you?</h1>
          <p className="mt-1 text-sm text-slate-500">
            Search our knowledge base for guides, FAQs, and procedures.
          </p>
        </div>

        {roleInfo && (
          <div className="mb-6 rounded-lg border-l-4 border-l-primary bg-blue-50 p-4">
            <div className="flex items-center gap-3">
              <span className="material-symbols-outlined text-2xl text-primary">{roleInfo.icon}</span>
              <div>
                <p className="text-sm font-semibold text-slate-900">{roleInfo.title}</p>
                <p className="text-xs text-slate-500">
                  Quick access to guides tailored for your role.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      {categorySlug && (() => {
          const cat = allCategories.find((c) => c.slug === categorySlug);
          if (!cat) {
            return (
              <div className="mb-8 rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm">
                <span className="material-symbols-outlined text-3xl text-slate-300 mb-2">folder_off</span>
                <h2 className="text-lg font-semibold text-slate-900">Category not found</h2>
                <p className="mt-1 text-sm text-slate-500">
                  The category you are looking for does not exist.
                </p>
                <a href="/helpdesk" className="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                  Browse all articles
                </a>
              </div>
            );
          }
          return (
            <div className="mb-8">
              <div className="mb-1 flex items-center gap-2">
                {cat.icon && (
                  <span className="material-symbols-outlined text-xl text-slate-500">{cat.icon}</span>
                )}
                <h1 className="text-xl font-bold text-slate-900">{cat.name}</h1>
              </div>
              {cat.description && <p className="text-sm text-slate-500">{cat.description}</p>}
            </div>
          );
        })()}

      {featuredArticles.length > 0 && (
        <section className="mb-10">
          <h2 className="mb-4 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
            Featured Articles
          </h2>
          <div className="grid gap-4 sm:grid-cols-2">
            {featuredArticles.map((article) => (
              <ArticleCard key={article.id} article={article} variant="featured" />
            ))}
          </div>
        </section>
      )}

      {hierarchicalCategories.length > 0 && (
        <section className="mb-10">
          <h2 className="mb-4 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
            Browse by Category
          </h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {hierarchicalCategories.map((cat) => (
              <Link
                key={cat.id}
                href={`/helpdesk?category=${cat.slug}`}
                className="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-primary/30 hover:shadow-md"
              >
                <div className="flex items-center gap-3">
                  {cat.icon && (
                    <span className="material-symbols-outlined text-2xl text-slate-400">
                      {cat.icon}
                    </span>
                  )}
                  <div>
                    <h3 className="text-sm font-semibold text-slate-900 group-hover:text-primary transition-colors">
                      {cat.name}
                    </h3>
                    {cat.total_articles > 0 && (
                      <p className="text-xs text-slate-400">
                        {cat.total_articles} article{cat.total_articles !== 1 ? 's' : ''}
                      </p>
                    )}
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}

      <div className="mb-10 grid gap-8 lg:grid-cols-2">
        {recentArticles.length > 0 && (
          <section>
            <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
              Recently Updated
            </h2>
            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              {recentArticles.map((article) => (
                <ArticleCard key={article.id} article={article} variant="compact" />
              ))}
            </div>
          </section>
        )}


      </div>

      {allTags.length > 0 && (
        <section className="mb-10">
          <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
            Browse by Tag
          </h2>
          <div className="flex flex-wrap gap-2">
            {allTags.map((tag) => (
              <TagBadge key={tag.id} tag={tag} />
            ))}
          </div>
        </section>
      )}

      {!hasContent && (
        <EmptyState
          icon="menu_book"
          title="Help center is being set up"
          description="Articles will appear here once published. Check back soon!"
        />
      )}
    </HelpdeskLayout>
  );
}
