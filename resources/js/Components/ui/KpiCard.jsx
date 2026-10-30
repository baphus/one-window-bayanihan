export default function KpiCard({
  title,
  label,
  value,
  suffix = '',
  icon = 'monitoring',
  iconBg = 'bg-primary-fixed',
  iconColor = 'text-primary',
  trend,
  description,
  hint,
  trailing,
  sparkline,
  tone = 'default',
  valueTone,
}) {
  const heading = title ?? label ?? '';
  const tones = {
    default: 'text-slate-900 dark:text-slate-100',
    positive: 'text-emerald-700 dark:text-emerald-400',
    attention: 'text-amber-700 dark:text-amber-400',
    alert: 'text-rose-700 dark:text-rose-400',
  };
  const sideContent = trailing || sparkline ? (
    <div className="flex flex-col items-end gap-1">{trailing}{sparkline}</div>
  ) : null;

  return (
    <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div className="mb-1.5 flex items-start justify-between gap-2">
        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{heading}</p>
        <span className={`rounded-lg p-1.5 ${iconBg}`} aria-hidden="true">
          <span className={`material-symbols-outlined text-lg ${iconColor}`}>{icon}</span>
        </span>
      </div>
      <div className="flex items-end justify-between gap-2">
        <p className={`text-2xl font-black leading-none ${valueTone || tones[tone] || tones.default}`}>{value ?? 0}{suffix}</p>
        {sideContent}
      </div>
      {(trend || hint || description) && (
        <p className="mt-1.5 text-[10px] text-slate-400">{trend || hint || description}</p>
      )}
    </article>
  );
}
