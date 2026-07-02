import clsx from 'clsx';
import { Link } from '@inertiajs/react';
import { formatDisplayDate } from '@/lib/utils';

export default function ArticleCard({
  article,
  variant = 'default',
  categoryName,
  categoryIcon,
  badges = [],
}) {
  if (variant === 'compact') {
    return (
      <Link
        href={`/helpdesk/${article.slug}`}
        className="group block border-b border-outline-variant py-3 last:border-0"
      >
        <h4 className="font-headline text-sm font-semibold text-slate-800 transition-colors group-hover:text-primary">
          {article.title}
        </h4>
        {article.excerpt && (
          <p className="mt-0.5 text-xs text-slate-500 line-clamp-1">{article.excerpt}</p>
        )}
      </Link>
    );
  }

  return (
    <Link
      href={`/helpdesk/${article.slug}`}
      className={clsx(
        'group block rounded-none border border-outline-variant bg-white p-5 transition-all hover:border-primary hover:bg-surface-container-low',
        variant === 'featured' && 'border-l-4 border-l-primary'
      )}
    >
      <div className="flex items-start gap-3">
        {categoryIcon && (
          <span className="material-symbols-outlined mt-0.5 text-2xl text-primary/70">
            {categoryIcon}
          </span>
        )}
        <div className="min-w-0 flex-1">
          <h3 className="font-headline text-sm font-semibold text-slate-900 transition-colors group-hover:text-primary">
            {article.title}
          </h3>
          {article.excerpt && (
            <p className="mt-1 text-xs text-slate-500 line-clamp-2">{article.excerpt}</p>
          )}
          {badges.length > 0 && <div className="mt-3 flex flex-wrap gap-1.5">{badges}</div>}
          <div className="mt-3 flex items-center gap-3 text-[10px] text-slate-500">
            {categoryName && (
              <span className="rounded-none border border-outline-variant bg-surface-container-low px-2 py-0.5 font-label font-semibold uppercase tracking-[0.12em] text-slate-600">
                {categoryName}
              </span>
            )}
            <span>Updated {formatDisplayDate(article.publishedAt)}</span>
          </div>
        </div>
      </div>
    </Link>
  );
}
