import clsx from 'clsx';

const colors = {
  standard: 'border-slate-200 bg-slate-50 text-slate-600',
  intervention: 'border-purple-200 bg-purple-50 text-purple-700',
  DEFAULT: 'border-slate-200 bg-white text-slate-600',
};

const sizes = {
  sm: 'text-[10px] px-2 py-[3px]',
  md: 'text-[11px] px-2.5 py-1',
};

function normalizeType(type) {
  return String(type ?? '')
    .trim()
    .toLowerCase();
}

export default function TypeBadge({ type, size = 'sm' }) {
  const normalizedType = normalizeType(type);

  const classes = clsx(
    'inline-flex items-center gap-1 rounded-[2px] border font-extrabold uppercase tracking-wide',
    sizes[size] ?? sizes.sm,
    colors[normalizedType] ?? colors.DEFAULT
  );

  return (
    <span className={classes}>
      {normalizedType || 'Unknown'}
    </span>
  );
}
