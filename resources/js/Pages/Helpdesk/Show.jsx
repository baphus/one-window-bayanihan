import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import MarkdownRenderer from '@/Components/Helpdesk/MarkdownRenderer';
import Breadcrumbs from '@/Components/Helpdesk/Breadcrumbs';
import FeedbackWidget from '@/Components/Helpdesk/FeedbackWidget';
import RelatedArticles from '@/Components/Helpdesk/RelatedArticles';
import TagBadge from '@/Components/Helpdesk/TagBadge';

export default function Show({ article, relatedArticles, categoryPath, categories }) {
  const breadcrumbItems = categoryPath.map((cat) => ({
    label: cat.label,
    href: `/helpdesk?category=${cat.slug}`,
  }));

  const wordCount = (article.content_markdown || '').split(/\s+/).filter(Boolean).length;
  const readTime = Math.max(1, Math.ceil(wordCount / 200));

  return (
    <HelpdeskLayout title={article.title} categories={categories}>
      <Breadcrumbs items={breadcrumbItems} />

      <article>
        <header className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900 leading-tight">{article.title}</h1>
          <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            {article.author && (
              <span>By {article.author.name}</span>
            )}
            <span>
              Updated {new Date(article.updated_at).toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
              })}
            </span>
            <span>{readTime} min read</span>
          </div>
          {article.excerpt && (
            <p className="mt-3 text-sm text-slate-600 leading-relaxed">{article.excerpt}</p>
          )}
        </header>

        {article.tags?.length > 0 && (
          <div className="mb-6 flex flex-wrap gap-1.5">
            {article.tags.map((tag) => (
              <TagBadge key={tag.id} tag={tag} />
            ))}
          </div>
        )}

        <div className="mb-10 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <MarkdownRenderer content={article.content_markdown} />
        </div>

        <div className="mb-8">
          <FeedbackWidget articleId={article.id} />
        </div>
      </article>

      {relatedArticles?.length > 0 && (
        <div className="mt-8">
          <RelatedArticles articles={relatedArticles} />
        </div>
      )}
    </HelpdeskLayout>
  );
}
