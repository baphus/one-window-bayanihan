export default function MetricCard({
  label,
  value,
  accent,
  description,
  valueTone,
  trailing,
}) {
  return (
    <article className={`border border-[#d5dbe3] border-l-[3px] ${accent} bg-[#f8fafc] px-4 py-3`}>
      <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">{label}</p>
      <div className="mt-1 flex items-end justify-between">
        <p className={`text-[33px] font-black leading-none text-[#0f172a] ${valueTone ?? ''}`}>{value}</p>
        {trailing}
      </div>
      {description ? <p className="mt-1 text-[10px] font-semibold text-slate-500">{description}</p> : null}
    </article>
  );
}
