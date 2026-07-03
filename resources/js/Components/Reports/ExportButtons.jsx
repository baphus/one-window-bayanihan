import { FileDown } from 'lucide-react';

const btnClass =
  'inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700';

export default function ExportButtons({ fromDateISO, toDateISO }) {
  return (
    <button
      type="button"
      onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
      className={btnClass}
    >
      <FileDown className="h-3.5 w-3.5" />
      Export PDF
    </button>
  );
}
