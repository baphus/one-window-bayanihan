export default function KpiCard({ title, value, accent, suffix = '', icon }) {
  return (
    <div className={`bg-white border border-[#cbd5e1] border-l-[4px] ${accent} rounded-[4px] px-4 py-4 shadow-sm`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">{title}</p>
          <p className="mt-2 text-[30px] leading-none font-black text-[#0f172a]">{value}{suffix}</p>
        </div>
        <span className="material-symbols-outlined text-[24px] text-slate-400">{icon}</span>
      </div>
    </div>
  );
}
