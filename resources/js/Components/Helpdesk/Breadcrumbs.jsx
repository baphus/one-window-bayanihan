import { Link } from '@inertiajs/react';

export default function Breadcrumbs({ items }) {
  if (!items?.length) return null;

  return (
    <nav className="flex items-center gap-1.5 text-xs text-slate-500 mb-6">
      <Link href={route('helpdesk.index')} className="hover:text-primary transition-colors">
        Home
      </Link>
      {items.map((item, i) => (
        <span key={i} className="flex items-center gap-1.5">
          <span className="material-symbols-outlined text-sm">chevron_right</span>
          {i === items.length - 1 ? (
            <span className="font-medium text-slate-800">{item.label}</span>
          ) : (
            <Link href={item.href} className="hover:text-primary transition-colors">
              {item.label}
            </Link>
          )}
        </span>
      ))}
    </nav>
  );
}
