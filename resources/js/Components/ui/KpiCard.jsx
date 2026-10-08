export default function KpiCard({
  title,
  label,
  value,
  suffix = '',
  icon,
  iconBg = 'bg-blue-50',
  iconColor = 'text-blue-900',
  trend,
  description,
  trailing,
  sparkline,
  valueTone = '',
}) {
  const heading = title ?? label ?? '';

  const iconWrapper = icon && typeof icon === 'string' ? (
    <span className={`p-1.5 rounded-lg ${iconBg}`}>
      <span className={`material-symbols-outlined text-lg ${iconColor}`}>{icon}</span>
    </span>
  ) : icon ? (
    <span className={`p-1.5 rounded-lg ${iconBg}`}>{icon}</span>
  ) : null;

  const sideContent = trailing || sparkline ? (
    <div className="flex flex-col items-end gap-1">
      {trailing}
      {sparkline}
    </div>
  ) : null;

  return (
    <article className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div className="flex items-start justify-between mb-2">
        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-400">{heading}</p>
        {iconWrapper}
      </div>
      <div className="flex items-end justify-between">
        <p className={`text-2xl font-black text-slate-900 dark:text-slate-100 ${sideContent ? 'leading-none' : ''} ${valueTone}`}>{value}{suffix}</p>
        {sideContent}
      </div>
      {trend && (
        <span className="mt-1.5 text-[11px] font-bold text-blue-900 bg-blue-50 px-1.5 py-0.5 rounded self-start">
          {trend}
        </span>
      )}
      {description && <p className="mt-1.5 text-[10px] text-slate-400 dark:text-slate-400">{description}</p>}
    </article>
  );
}
