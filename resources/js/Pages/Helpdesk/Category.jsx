import { Head } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import EmptyState from '@/Components/Helpdesk/EmptyState';

export default function Category({ category, articles, categories, categoryPath }) {
  const articleList = articles?.data ?? [];
  const pagination = articles ?? {};

  const breadcrumbItems = categoryPath.map((cat) => ({
    label: cat.label,
    href: cat.slug === category?.slug ? null : `/helpdesk?category=${cat.slug}`,
  }));

  return (
    <HelpdeskLayout title={category?.name || 'Category'} categories={categories} activeSlug={category?.slug} showSearchHero={false}>
      <Head title={`${category?.name || 'Category'} - Help Center`} />

      <Breadcrumbs items={breadcrumbItems} />

      {category && (
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">{category.name}</h1>
          {category.description && (
            <p className="mt-1 text-sm text-slate-500">{category.description}</p>
          )}
        </div>
      )}

      {articleList.length > 0 ? (
        <div className="space-y-3">
          {articleList.map((article) => (
            <ArticleCard key={article.id} article={article} />
          ))}
        </div>
      ) : (
        <EmptyState
          icon="folder_open"
          title={category ? 'No articles in this category' : 'Category not found'}
          description={
            category
              ? 'Articles will appear here once published. Check back soon!'
              : 'The category you are looking for does not exist.'
          }
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
    </HelpdeskLayout>
  );
}
