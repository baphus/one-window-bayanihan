export default function KpiCard({ title, value, accent, suffix = '', icon, trend, description, iconBg = 'bg-blue-50', iconColor = 'text-blue-900' }) {
  const iconWrapper = icon && typeof icon === 'string' ? (
    <span className={`p-1.5 rounded-lg ${iconBg}`}>
      <span className={`material-symbols-outlined text-lg ${iconColor}`}>{icon}</span>
    </span>
  ) : icon ? (
    <span className={`p-1.5 rounded-lg ${iconBg}`}>{icon}</span>
  ) : null;

  if (trend || description) {
    return (
      <div className="bg-white p-5 rounded-md border border-slate-200 flex flex-col justify-start relative">
        <div className="flex items-start justify-between mb-2">
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{title}</p>
          {iconWrapper}
        </div>
        <h3 className="text-2xl font-black text-slate-900">{value}</h3>
        {trend && (
          <span className="mt-1.5 text-[11px] font-bold text-blue-900 bg-blue-50 px-1.5 py-0.5 rounded self-start">
            {trend}
          </span>
        )}
        {description && <p className="mt-1.5 text-[10px] text-slate-400">{description}</p>}
      </div>
    );
  }

  return (
    <div className="bg-white p-5 rounded-md border border-slate-200">
      <div className="flex items-start justify-between mb-2">
        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{title}</p>
        {iconWrapper}
      </div>
      <p className="text-2xl font-black text-slate-900">{value}{suffix}</p>
    </div>
  );
}
