import { Link } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import EmptyState from '@/Components/Helpdesk/EmptyState';

export default function Category({ category, subcategories, articles, categories, categoryPath }) {
  const articleList = articles?.data ?? [];
  const pagination = articles ?? {};

  const breadcrumbItems = categoryPath.map((cat) => ({
    label: cat.label,
    href: cat.slug === category?.slug ? null : `/helpdesk?category=${cat.slug}`,
  }));

  return (
    <HelpdeskLayout title={category?.name || 'Category'} categories={categories} activeSlug={category?.slug} showSearchHero={false}>
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
          {subcategories.map((sub) => (
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
                  {sub.published_articles_count > 0 && (
                    <p className="mt-0.5 text-xs text-slate-500">
                      {sub.published_articles_count} article{sub.published_articles_count !== 1 ? 's' : ''}
                    </p>
                  )}
                  {sub.description && (
                    <p className="mt-0.5 text-xs text-slate-400 line-clamp-1">{sub.description}</p>
                  )}
                </div>
              </div>
            </Link>
          ))}
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

          {pagination?.last_page > 1 && (
            <div className="mt-8 flex items-center justify-center gap-2">
              {pagination.links?.map((link, i) => (
                <a
                  key={i}
                  href={link.url ? `/helpdesk?category=${category?.slug}&page=${new URL(link.url).searchParams.get('page') || 1}` : '#'}
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
