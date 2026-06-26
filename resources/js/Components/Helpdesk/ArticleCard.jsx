import clsx from 'clsx';
import { formatDisplayDate } from '@/lib/utils';

export default function ArticleCard({ article, variant = 'default', categoryName, categoryIcon }) {
  if (variant === 'compact') {
    return (
      <a
        href={`/helpdesk/${article.slug}`}
        className="group block border-b border-slate-100 py-3 last:border-0"
      >
        <h4 className="text-sm font-semibold text-slate-800 group-hover:text-primary transition-colors">
          {article.title}
        </h4>
        {article.excerpt && (
          <p className="mt-0.5 text-xs text-slate-500 line-clamp-1">{article.excerpt}</p>
        )}
      </a>
    );
  }

  return (
    <a
      href={`/helpdesk/${article.slug}`}
      className={clsx(
        'group block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-primary/30 hover:shadow-md',
        variant === 'featured' && 'border-l-4 border-l-primary'
      )}
    >
      <div className="flex items-start gap-3">
        {categoryIcon && (
          <span className="material-symbols-outlined mt-0.5 text-2xl text-slate-400">
            {categoryIcon}
          </span>
        )}
        <div className="min-w-0 flex-1">
          <h3 className="text-sm font-semibold text-slate-900 group-hover:text-primary transition-colors">
            {article.title}
          </h3>
          {article.excerpt && (
            <p className="mt-1 text-xs text-slate-500 line-clamp-2">{article.excerpt}</p>
          )}
          <div className="mt-3 flex items-center gap-3 text-[10px] text-slate-400">
            {categoryName && (
              <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">
                {categoryName}
              </span>
            )}
            <span>
              Updated {formatDisplayDate(article.publishedAt)}
            </span>
          </div>
        </div>
      </div>
    </a>
  );
}
