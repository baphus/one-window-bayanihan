export default function MetricCard({
  label,
  value,
  accent,
  description,
  valueTone,
  trailing,
  icon,
}) {
  return (
    <article className={`border border-[#d5dbe3] border-l-[3px] ${accent} bg-slate-50 px-4 py-3`}>
      <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
        {icon && <span className="mr-1.5 inline-flex items-center justify-center h-5 w-5 rounded bg-slate-100/80">{icon}</span>}
        {label}
      </p>
      <div className="mt-1 flex items-end justify-between">
        <p className={`text-[33px] font-black leading-none text-slate-900 ${valueTone ?? ''}`}>{value}</p>
        {trailing}
      </div>
      {description ? <p className="mt-1 text-[10px] font-semibold text-slate-500">{description}</p> : null}
    </article>
  );
}
