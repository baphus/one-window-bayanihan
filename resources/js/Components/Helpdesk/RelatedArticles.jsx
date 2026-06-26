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
    <div className="rounded-lg border border-slate-200 bg-white p-4">
      <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
        Related Articles
      </h3>
      <div className="space-y-0">
        {related.map((article) => (
          <a
            key={article.id}
            href={`/helpdesk/${article.slug}`}
            className="group block border-b border-slate-100 py-2.5 last:border-0"
          >
            <h4 className="text-sm font-medium text-slate-700 group-hover:text-primary transition-colors">
              {article.title}
            </h4>
            {article.excerpt && (
              <p className="mt-0.5 text-xs text-slate-400 line-clamp-1">{article.excerpt}</p>
            )}
          </a>
        ))}
      </div>
    </div>
  );
}
