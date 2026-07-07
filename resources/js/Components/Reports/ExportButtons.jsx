import { FileDown, FileSpreadsheet } from 'lucide-react';

const btnClass =
  'inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200';

// Forward the full active filter set so the exported file matches exactly what
// is shown on screen (date range, date scope, province, city).
export default function ExportButtons({ fromDateISO, toDateISO, dateScope, province, city }) {
  const params = {
    from: fromDateISO,
    to: toDateISO,
    ...(dateScope ? { date_scope: dateScope } : {}),
    ...(province ? { province } : {}),
    ...(city ? { city } : {}),
  };

  return (
    <div className="flex items-center gap-3">
      <button
        type="button"
        onClick={() => window.open(route('reports.export-pdf', params))}
        className={btnClass}
      >
        <FileDown className="h-3.5 w-3.5" />
        Export PDF
      </button>
      <button
        type="button"
        onClick={() => window.open(route('reports.export-excel', params))}
        className={btnClass}
      >
        <FileSpreadsheet className="h-3.5 w-3.5" />
        Export Excel
      </button>
    </div>
  );
}
