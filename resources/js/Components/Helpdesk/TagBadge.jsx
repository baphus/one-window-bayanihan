import { Link } from '@inertiajs/react';
import clsx from 'clsx';

export default function TagBadge({ tag, href, active }) {
  const classes = clsx(
    'inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-medium transition-colors',
    active
      ? 'bg-primary text-white'
      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
  );

  if (href) {
    return (
      <Link href={href} className={classes}>
        {tag.name}
      </Link>
    );
  }

  return <span className={classes}>{tag.name}</span>;
}
