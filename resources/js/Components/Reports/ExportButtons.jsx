import { useEffect, useRef, useState } from 'react';
import { FileDown, FileSpreadsheet } from 'lucide-react';

const btnClass =
  'inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-emerald-700 bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';

// Keep the buttons disabled until the browser finishes handling the export.
// For file downloads the page stays open, so `window` focus (fires when the
// download completes) re-enables the buttons; the safety timeout guarantees
// they can never stay stuck even if focus never fires.
const SAFETY_TIMEOUT_MS = 60_000;

// Forward the full active filter set so the exported file matches exactly what
// is shown on screen (date range, date scope, province, city, agency).
export default function ExportButtons({ fromDateISO, toDateISO, dateScope, province, city, agencyId, disabled = false }) {
  const [exporting, setExporting] = useState(false);
  const timeoutRef = useRef(null);

  const clearPending = () => {
    if (timeoutRef.current) {
      window.clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
    setExporting(false);
  };

  useEffect(() => {
    window.addEventListener('focus', clearPending);
    return () => {
      window.removeEventListener('focus', clearPending);
      if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
    };
  }, []);

  const params = {
    from: fromDateISO,
    to: toDateISO,
    ...(dateScope ? { date_scope: dateScope } : {}),
    ...(province ? { province } : {}),
    ...(city ? { city } : {}),
    ...(agencyId ? { agency_id: agencyId } : {}),
  };

  const startExport = (routeName) => {
    if (exporting) return;
    setExporting(true);
    window.location.href = route(routeName, params);
    timeoutRef.current = window.setTimeout(clearPending, SAFETY_TIMEOUT_MS);
  };

  const isDisabled = disabled || exporting;

  return (
    <div className="flex items-center gap-2">
      <button
        type="button"
        disabled={isDisabled}
        title={disabled ? 'Apply filters before exporting.' : undefined}
        onClick={() => startExport('reports.export-pdf')}
        className={btnClass}
      >
        <FileDown className="w-4 h-4" />
        Export PDF
      </button>
      <button
        type="button"
        disabled={isDisabled}
        title={disabled ? 'Apply filters before exporting.' : undefined}
        onClick={() => startExport('reports.export-excel')}
        className={btnClass}
      >
        <FileSpreadsheet className="w-4 h-4" />
        Export Excel
      </button>
    </div>
  );
}
