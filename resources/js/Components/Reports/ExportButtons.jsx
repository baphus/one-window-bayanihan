import { useState } from 'react';
import { FileDown, FileSpreadsheet, Loader2 } from 'lucide-react';
import { useToast } from '@/Hooks/useToast';

const btnClass =
  'inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-emerald-700 bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';

// Forward the full active filter set so the exported file matches exactly what
// is shown on screen (date range, date scope, province, city, agency).
export default function ExportButtons({ fromDateISO, toDateISO, dateScope, province, city, agencyId, disabled = false }) {
  const toast = useToast();
  // Which format is currently in flight: 'pdf' | 'excel' | null.
  const [exporting, setExporting] = useState(null);

  const params = {
    from: fromDateISO,
    to: toDateISO,
    ...(dateScope ? { date_scope: dateScope } : {}),
    ...(province ? { province } : {}),
    ...(city ? { city } : {}),
    ...(agencyId ? { agency_id: agencyId } : {}),
  };

  // Both endpoints queue a job and answer with JSON, so they cannot be opened
  // in a tab like a file download — go through the shared async-export helper,
  // which reports progress by toast and leaves a notification when ready.
  const startExport = async (format, routeName) => {
    const { dispatchAsyncExport } = await import('@/lib/async-export');

    await dispatchAsyncExport(route(routeName, params), toast, {
      onStart: () => setExporting(format),
      onDone: () => setExporting(null),
    });
  };

  const busy = exporting !== null;

  return (
    <div className="flex items-center gap-2">
      <button
        type="button"
        disabled={disabled || busy}
        title={disabled ? 'Apply filters before exporting.' : undefined}
        onClick={() => startExport('pdf', 'reports.export-pdf')}
        className={btnClass}
      >
        {exporting === 'pdf'
          ? <Loader2 className="w-4 h-4 animate-spin" />
          : <FileDown className="w-4 h-4" />}
        Export PDF
      </button>
      <button
        type="button"
        disabled={disabled || busy}
        title={disabled ? 'Apply filters before exporting.' : undefined}
        onClick={() => startExport('excel', 'reports.export-excel')}
        className={btnClass}
      >
        {exporting === 'excel'
          ? <Loader2 className="w-4 h-4 animate-spin" />
          : <FileSpreadsheet className="w-4 h-4" />}
        Export Excel
      </button>
    </div>
  );
}
