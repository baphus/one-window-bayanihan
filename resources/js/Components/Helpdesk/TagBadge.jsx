import clsx from 'clsx';
import { Link } from '@inertiajs/react';

export default function TagBadge({ tag, tagName, href, active }) {
  const label = tagName ?? tag?.name;

  const classes = clsx(
    'inline-flex rounded-none px-2.5 py-0.5 font-label text-[11px] font-medium uppercase tracking-[0.12em] transition-colors',
    active
      ? 'bg-primary text-white'
      : 'border border-outline-variant bg-surface-container-low text-slate-600 hover:border-primary/30 hover:text-primary'
  );

  if (href) {
    return (
      <Link href={href} className={classes}>
        {label}
      </Link>
    );
  }

  return <span className={classes}>{label}</span>;
}
