import { Link } from '@inertiajs/react';

export default function RelatedArticles({ currentArticle, allArticles = [] }) {
  if (!currentArticle || !allArticles.length) return null;

  const related = allArticles
    .filter((a) => a.id !== currentArticle.id)
    .map((a) => ({
      ...a,
      sharedTagCount: a.tagIds.filter((tid) => currentArticle.tagIds.includes(tid)).length,
    }))
    .filter((a) => a.sharedTagCount > 0)
    .sort((a, b) => b.sharedTagCount - a.sharedTagCount)
    .slice(0, 5);

  if (!related.length) return null;

  return (
    <div className="border border-slate-200 bg-white p-4">
      <h3 className="mb-3 font-label text-[11px] font-bold uppercase tracking-[0.18em] text-primary">
        Related Articles
      </h3>
      <div className="space-y-0">
        {related.map((article) => (
          <Link
            key={article.id}
            href={`/helpdesk/${article.slug}`}
            className="group block border-b border-slate-200 py-2.5 last:border-0"
          >
            <h4 className="font-headline text-sm font-medium text-slate-700 transition-colors group-hover:text-primary">
              {article.title}
            </h4>
            {article.excerpt && (
              <p className="mt-0.5 text-xs text-slate-400 line-clamp-1">{article.excerpt}</p>
            )}
          </Link>
        ))}
      </div>
    </div>
  );
}
