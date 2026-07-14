import { Link } from '@inertiajs/react';

export default function Breadcrumbs({ items }) {
  if (!items?.length) return null;

  return (
    <nav
      aria-label="Breadcrumb"
      className="mb-6 flex flex-wrap items-center gap-1.5 border-b border-slate-200 pb-3 text-sm text-slate-500"
    >
      <Link
        href={route('helpdesk.index')}
        className="transition-colors hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
      >
        Help Center
      </Link>
      {items.map((item, i) => (
        <span key={i} className="flex items-center gap-1.5">
          <span className="material-symbols-outlined text-sm text-slate-600" aria-hidden="true">
            chevron_right
          </span>
          {i === items.length - 1 ? (
            <span aria-current="page" className="font-semibold text-slate-800">
              {item.label}
            </span>
          ) : (
            <Link
              href={item.href}
              className="transition-colors hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
              {item.label}
            </Link>
          )}
        </span>
      ))}
    </nav>
  );
}
