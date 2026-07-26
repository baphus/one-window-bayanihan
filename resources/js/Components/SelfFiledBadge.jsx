export default function SelfFiledBadge({ className = '' }) {
  return (
    <span className={`inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 ${className}`}>
      <span className="material-symbols-outlined text-[12px]">person</span>
      Self-Filed
    </span>
  );
}
