import { Link } from '@inertiajs/react';

export function readTimeMinutes(article) {
  const wordCount = (article?.content || '').split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.ceil(wordCount / 200));
}

export default function ArticleListRow({ article, meta }) {
  return (
    <li className="border-b border-slate-200 last:border-b-0">
      <Link
        href={`/help/${article.slug}`}
        className="group flex min-w-0 items-baseline justify-between gap-4 px-1 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
      >
        <span className="min-w-0">
          <span className="block font-headline text-sm font-semibold text-slate-800 group-hover:text-primary group-hover:underline">
            {article.title}
          </span>
          {article.excerpt && (
            <span className="mt-0.5 block text-sm text-slate-500 line-clamp-1">{article.excerpt}</span>
          )}
        </span>
        <span className="flex-shrink-0 whitespace-nowrap text-xs text-slate-600">
          {meta || `${readTimeMinutes(article)} min read`}
        </span>
      </Link>
    </li>
  );
}
