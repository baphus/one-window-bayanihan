export function CardSection({ title, children, className = '' }) {
  return (
    <section className={`rounded-[3px] border border-[#d8dee8] bg-white p-4 shadow-sm ${className}`}>
      {title && <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">{title}</h3>}
      {children}
    </section>
  );
}

export function MetaTile({ label, value, subtext }) {
  return (
    <div className="rounded-[3px] border border-slate-200 bg-slate-50 px-3 py-2">
      <p className="text-[10px] font-extrabold uppercase tracking-[0.1em] text-slate-500">{label}</p>
      <p className="mt-1 text-[13px] font-semibold text-slate-800">{value}</p>
      {subtext && <p className="text-[11px] text-slate-500 mt-0.5">{subtext}</p>}
    </div>
  );
}

export function InfoCell({ label, value, fullRow = false }) {
  return (
    <div className={`border-b border-r border-[#d8dee8] px-3 py-2 ${fullRow ? 'md:col-span-3' : ''}`}>
      <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">{label}</p>
      <div className="mt-1 text-[12px] font-semibold text-slate-700">{value || '-'}</div>
    </div>
  );
}

export function SubsectionCard({ title, children }) {
  return (
    <div className="space-y-2.5">
      <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">{title}</h4>
      {children}
    </div>
  );
}
