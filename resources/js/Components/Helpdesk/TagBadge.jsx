import clsx from 'clsx';

export default function TagBadge({ tag, tagName, href, active }) {
  const label = tagName ?? tag?.name;

  const classes = clsx(
    'inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-medium transition-colors',
    active
      ? 'bg-primary text-white'
      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
  );

  if (href) {
    return (
      <a href={href} className={classes}>
        {label}
      </a>
    );
  }

  return <span className={classes}>{label}</span>;
}
