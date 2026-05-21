export default function KpiCard({ title, value, accent, suffix = '', icon, trend, description }) {
  if (trend || description) {
    return (
      <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col justify-start relative">
        <p className="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 mb-1">{title}</p>
        <div className="flex items-baseline gap-1.5 mb-1.5">
          <h3 className="text-2xl font-black text-slate-900">{value}</h3>
        </div>
        {trend && (
          <span className="text-[9px] font-bold uppercase tracking-widest text-blue-900 bg-blue-50 px-1.5 py-0.5 rounded self-start mb-1.5 inline-block">
            {trend}
          </span>
        )}
        <p className="text-[10px] font-medium text-slate-400 font-label mt-auto">{description}</p>
        {icon && <div className="absolute top-4 right-4">{icon}</div>}
      </div>
    );
  }

  return (
    <div className={`bg-white border border-[#cbd5e1] border-l-[4px] ${accent} rounded-[4px] px-4 py-4 shadow-sm`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">{title}</p>
          <p className="mt-2 text-[30px] leading-none font-black text-[#0f172a]">{value}{suffix}</p>
        </div>
        {icon && typeof icon === 'string' ? (
          <span className="material-symbols-outlined text-[24px] text-slate-400">{icon}</span>
        ) : (
          icon
        )}
      </div>
    </div>
  );
}
