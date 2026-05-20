import { Link } from '@inertiajs/react';

export default function RelatedArticles({ articles }) {
  if (!articles?.length) return null;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-4">
      <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">
        Related Articles
      </h3>
      <div className="space-y-0">
        {articles.map((article) => (
          <Link
            key={article.id}
            href={route('helpdesk.show', article.slug)}
            className="group block border-b border-slate-100 py-2.5 last:border-0"
          >
            <h4 className="text-sm font-medium text-slate-700 group-hover:text-primary transition-colors">
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
