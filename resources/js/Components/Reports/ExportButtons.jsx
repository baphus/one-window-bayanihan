import { FileDown, FileSpreadsheet } from 'lucide-react';

const btnClass =
  'inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-emerald-700 bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';

// Forward the full active filter set so the exported file matches exactly what
// is shown on screen (date range, date scope, province, city, agency).
export default function ExportButtons({ fromDateISO, toDateISO, dateScope, province, city, agencyId, disabled = false }) {
  const params = {
    from: fromDateISO,
    to: toDateISO,
    ...(dateScope ? { date_scope: dateScope } : {}),
    ...(province ? { province } : {}),
    ...(city ? { city } : {}),
    ...(agencyId ? { agency_id: agencyId } : {}),
  };

  return (
    <div className="flex items-center gap-2">
      <button
        type="button"
        disabled={disabled}
        title={disabled ? 'Apply filters before exporting.' : undefined}
        onClick={() => window.open(route('reports.export-pdf', params))}
        className={btnClass}
      >
        <FileDown className="w-4 h-4" />
        Export PDF
      </button>
      <button
        type="button"
        disabled={disabled}
        title={disabled ? 'Apply filters before exporting.' : undefined}
        onClick={() => window.open(route('reports.export-excel', params))}
        className={btnClass}
      >
        <FileSpreadsheet className="w-4 h-4" />
        Export Excel
      </button>
    </div>
  );
}
