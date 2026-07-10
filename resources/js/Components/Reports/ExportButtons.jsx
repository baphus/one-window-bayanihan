import { FileDown, FileSpreadsheet } from 'lucide-react';

const btnClass =
  'inline-flex items-center gap-1.5 px-4 py-2 bg-[#0b5384] text-white hover:bg-[#09416a] text-[12px] font-bold rounded-md transition-colors border border-[#0b5384] disabled:opacity-60 disabled:cursor-not-allowed';

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
    <div className="flex items-center gap-2">
      <button
        type="button"
        onClick={() => window.open(route('reports.export-pdf', params))}
        className={btnClass}
      >
        <FileDown className="w-4 h-4" />
        Export PDF
      </button>
      <button
        type="button"
        onClick={() => window.open(route('reports.export-excel', params))}
        className={btnClass}
      >
        <FileSpreadsheet className="w-4 h-4" />
        Export Excel
      </button>
    </div>
  );
}
