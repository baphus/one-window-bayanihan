export function CardSection({ title, description, children, className = '', ...props }) {
  return (
    <section {...props} className={`rounded-[3px] border border-surface-variant bg-white p-4 shadow-sm ${className}`}>
      {title && <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-3">{title}</h3>}
      {description && <p className="mt-1 text-sm text-slate-500 mb-3">{description}</p>}
      {children}
    </section>
  );
}

export function MetaTile({ label, value, subtext }) {
  return (
    <div className="rounded-[3px] border border-slate-200 bg-slate-50 px-3 py-2">
      <p className="text-[10px] font-extrabold uppercase tracking-[0.1em] text-slate-500">{label}</p>
      <p className="mt-1 text-[13px] font-semibold text-slate-800 whitespace-pre-wrap">{value}</p>
      {subtext && <p className="text-[11px] text-slate-500 mt-0.5">{subtext}</p>}
    </div>
  );
}

export function InfoCell({ label, value, fullRow = false }) {
  return (
    <div className={`border-b border-r border-surface-variant px-3 py-2 ${fullRow ? 'md:col-span-3' : ''}`}>
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

export function CardHeader({ title, meta, actions }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#dce3eb] px-5 py-4">
      <div className="flex min-w-0 items-baseline gap-2">
        <h3 className="text-[15px] font-bold text-[#172333]">{title}</h3>
        {meta && <span className="text-[12px] text-slate-500">{meta}</span>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function InfoField({ label, value, fallback = 'N/A' }) {
  return (
    <div className="min-w-0">
      <p className="text-[12px] font-medium text-slate-500">{label}</p>
      <p className="mt-0.5 break-words text-[13px] font-semibold text-slate-800">{value || fallback}</p>
    </div>
  );
}
