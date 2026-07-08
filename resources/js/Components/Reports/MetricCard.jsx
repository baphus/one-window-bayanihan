export default function MetricCard({
  label,
  value,
  accent,
  description,
  valueTone,
  trailing,
  icon,
  sparkline,
}) {
  return (
    <article
      className={`border border-[#d5dbe3] border-l-[3px] ${accent} bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/60`}
    >
      <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
        {icon && (
          <span className="mr-1.5 inline-flex items-center justify-center h-5 w-5 rounded bg-slate-100/80 dark:bg-slate-800">
            {icon}
          </span>
        )}
        {label}
      </p>
      <div className="mt-1 flex items-end justify-between">
        <p className={`text-[33px] font-black leading-none text-slate-900 dark:text-slate-100 ${valueTone ?? ''}`}>
          {value}
        </p>
        <div className="flex flex-col items-end gap-1">
          {trailing}
          {sparkline}
        </div>
      </div>
      {description ? (
        <p className="mt-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400">{description}</p>
      ) : null}
    </article>
  );
}
