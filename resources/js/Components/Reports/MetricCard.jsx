export default function MetricCard({
  label,
  value,
  description,
  valueTone,
  trailing,
  icon,
  sparkline,
}) {
  return (
    <article
      className={`bg-white p-4 rounded-xl border border-slate-200 shadow-sm dark:border-slate-700 dark:bg-slate-900`}
    >
      <div className="flex items-start justify-between mb-2">
        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-400">
          {label}
        </p>
        {icon && (
          <span className="p-1.5 rounded-lg bg-slate-100/80 dark:bg-slate-800">
            {icon}
          </span>
        )}
      </div>
      <div className="flex items-end justify-between">
        <p className={`text-2xl font-black leading-none text-slate-900 dark:text-slate-100 ${valueTone ?? ''}`}>
          {value}
        </p>
        <div className="flex flex-col items-end gap-1">
          {trailing}
          {sparkline}
        </div>
      </div>
      {description ? (
        <p className="mt-0.5 text-[10px] text-slate-400 dark:text-slate-400">{description}</p>
      ) : null}
    </article>
  );
}
