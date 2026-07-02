import { Link } from '@inertiajs/react';

export default function Breadcrumbs({ items }) {
  if (!items?.length) return null;

  return (
    <nav className="mb-6 flex items-center gap-1.5 border-b border-slate-200 pb-3 text-xs text-slate-500">
      <Link href={route('helpdesk.index')} className="font-label uppercase tracking-[0.14em] transition-colors hover:text-primary">
        Home
      </Link>
      {items.map((item, i) => (
        <span key={i} className="flex items-center gap-1.5">
          <span className="material-symbols-outlined text-sm text-slate-400">chevron_right</span>
          {i === items.length - 1 ? (
            <span className="font-label font-semibold uppercase tracking-[0.12em] text-slate-800">{item.label}</span>
          ) : (
            <Link href={item.href} className="font-label uppercase tracking-[0.14em] transition-colors hover:text-primary">
              {item.label}
            </Link>
          )}
        </span>
      ))}
    </nav>
  );
}
