import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import EmptyState from '@/Components/Helpdesk/EmptyState';
import { articles as allArticles } from '@/data/helpdesk/articles';
import { categories as flatCategories } from '@/data/helpdesk/categories';

/**
 * Build a category tree from flat data, computing article counts for the sidebar.
 */
function buildCategoryTree(flatCats, articles) {
  return flatCats
    .filter((c) => c.parentId === null)
    .map((parent) => {
      const children = flatCats.filter((k) => k.parentId === parent.id);
      const childrenWithCounts = children.map((child) => ({
        ...child,
        published_articles_count: articles.filter((a) => a.categoryId === child.id).length,
      }));
      const descendantIds = [parent.id, ...children.map((k) => k.id)];
      const totalArticles = articles.filter((a) => descendantIds.includes(a.categoryId)).length;
      return {
        ...parent,
        children: childrenWithCounts,
        total_articles: totalArticles,
        published_articles_count: totalArticles,
      };
    });
}

export default function Category() {
  const slug =
    typeof window !== 'undefined'
      ? new URLSearchParams(window.location.search).get('category')
      : null;

  const { category, subcategories, articleList, categoriesWithTree } = useMemo(() => {
    const cat = flatCategories.find((c) => c.slug === slug) ?? null;

    // Direct children of the current category
    const children = cat ? flatCategories.filter((c) => c.parentId === cat.id) : [];

    // All category IDs that qualify (current + descendants)
    const descendantIds = cat ? [cat.id, ...children.map((c) => c.id)] : [];

    // Filter articles by descendant categories
    const filtered = cat ? allArticles.filter((a) => descendantIds.includes(a.categoryId)) : [];

    // Resolve article.category and derive updated_at from publishedAt
    const enriched = filtered.map((article) => {
      const articleCat = flatCategories.find((c) => c.id === article.categoryId);
      return {
        ...article,
        category: articleCat ? { name: articleCat.name, icon: articleCat.icon } : undefined,
        updated_at: article.publishedAt,
      };
    });

    const tree = buildCategoryTree(flatCategories, allArticles);

    return {
      category: cat,
      subcategories: children,
      articleList: enriched,
      categoriesWithTree: tree,
    };
  }, [slug]);

  const breadcrumbItems = category ? [{ label: category.name }] : [];

  return (
    <HelpdeskLayout
      title={category?.name || 'Category'}
      categories={categoriesWithTree}
      activeSlug={category?.slug}
      showSearchHero={false}
    >
      <Breadcrumbs items={breadcrumbItems} />

      {category && (
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">{category.name}</h1>
          {category.description && (
            <p className="mt-1 text-sm text-slate-500">{category.description}</p>
          )}
          {!subcategories?.length && (
            <p className="mt-1 text-sm text-slate-400">
              {articleList.length} article{articleList.length !== 1 ? 's' : ''}
            </p>
          )}
        </div>
      )}

      {subcategories?.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {subcategories.map((sub) => {
            const subArticleCount = allArticles.filter((a) => a.categoryId === sub.id).length;
            return (
              <Link
                key={sub.id}
                href={`/helpdesk?category=${sub.slug}`}
                className="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-primary/30 hover:shadow-md"
              >
                <div className="flex items-center gap-3">
                  {sub.icon && (
                    <span className="material-symbols-outlined text-2xl text-slate-400">
                      {sub.icon}
                    </span>
                  )}
                  <div>
                    <h3 className="text-sm font-semibold text-slate-900 group-hover:text-primary transition-colors">
                      {sub.name}
                    </h3>
                    {subArticleCount > 0 && (
                      <p className="mt-0.5 text-xs text-slate-500">
                        {subArticleCount} article{subArticleCount !== 1 ? 's' : ''}
                      </p>
                    )}
                    {sub.description && (
                      <p className="mt-0.5 text-xs text-slate-400 line-clamp-1">
                        {sub.description}
                      </p>
                    )}
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      ) : category ? (
        <>
          {articleList.length > 0 ? (
            <div className="space-y-3">
              {articleList.map((article) => (
                <ArticleCard key={article.id} article={article} />
              ))}
            </div>
          ) : (
            <EmptyState
              icon="folder_open"
              title="No articles in this category"
              description="Articles will appear here once published. Check back soon!"
            />
          )}
        </>
      ) : (
        <EmptyState
          icon="folder_off"
          title="Category not found"
          description="The category you are looking for does not exist."
        />
      )}
    </HelpdeskLayout>
  );
}
