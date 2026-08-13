import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

function toDateInput(date) {
  return date.toISOString().slice(0, 10);
}

// Keep the Export button disabled until the browser finishes handling the
// download: `window` focus fires when a file download completes, and the
// safety timeout guarantees the button can never stay stuck.
const SAFETY_TIMEOUT_MS = 60_000;

export default function ExportDialog({
  open,
  onClose,
  title,
  activeFilters,
  maxDays = 365,
  defaultDays = 90,
  countUrlBuilder,
  onExport,
}) {
  const today = useMemo(() => new Date(), []);
  const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - defaultDays);
    return toDateInput(d);
  });
  const [dateTo, setDateTo] = useState(() => toDateInput(today));
  const [error, setError] = useState('');
  const [exporting, setExporting] = useState(false);
  const [rowCount, setRowCount] = useState(null);
  const [countState, setCountState] = useState('idle'); // 'idle' | 'loading' | 'ready' | 'error'
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

  // Reset the in-flight guard whenever the dialog is closed/reopened.
  useEffect(() => {
    if (!open) clearPending();
  }, [open]);

  // The server-computed page prop was stale the moment filters changed. Fetch
  // the live count every time the dialog opens and whenever the date range in
  // the modal changes, so the preview reflects the exact export that will run.
  const loadCount = useCallback(async (from, to) => {
    if (!countUrlBuilder) {
      setCountState('idle');
      return;
    }
    const url = countUrlBuilder(from, to);
    if (!url) {
      setCountState('idle');
      return;
    }
    setCountState('loading');
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`Count request failed: ${res.status}`);
      const data = await res.json();
      setRowCount(typeof data.count === 'number' ? data.count : null);
      setCountState('ready');
    } catch {
      setCountState('error');
    }
  }, [countUrlBuilder]);

  useEffect(() => {
    if (!open) return;
    loadCount(dateFrom, dateTo);
  }, [open, dateFrom, dateTo, loadCount]);

  if (!open) return null;

  const handleExport = () => {
    if (exporting) return;
    if (!dateFrom || !dateTo) {
      setError('Both start and end dates are required.');
      return;
    }
    const from = new Date(dateFrom);
    const to = new Date(dateTo);
    if (from > to) {
      setError('The start date must be before the end date.');
      return;
    }
    const diffDays = Math.round((to - from) / (1000 * 60 * 60 * 24));
    if (diffDays > maxDays) {
      setError(`The export range may not exceed ${maxDays} days.`);
      return;
    }

    setError('');
    setExporting(true);
    onExport({ dateFrom, dateTo });
    timeoutRef.current = window.setTimeout(clearPending, SAFETY_TIMEOUT_MS);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <div className="relative bg-white rounded-lg shadow-xl border border-slate-200 p-6 w-full max-w-md mx-4 owb-modal-animate">
        <h2 className="text-lg font-semibold text-slate-900 mb-1">{title}</h2>
        <p className="text-sm text-slate-500 mb-4">
          Choose a date range for the export. An explicit date range is required
          (up to {maxDays} days).
        </p>

        <div className="flex items-center gap-2 mb-4">
          <div className="flex-1">
            <label className="block text-xs font-medium text-slate-600 mb-1">From</label>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="w-full py-2 px-3 border border-slate-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div className="flex-1">
            <label className="block text-xs font-medium text-slate-600 mb-1">To</label>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="w-full py-2 px-3 border border-slate-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        {error && (
          <p className="text-sm text-red-600 mb-3" role="alert">{error}</p>
        )}

        {/* Active filter chips */}
        <div className="mb-4">
          <p className="text-xs font-medium text-slate-600 mb-2">Filters applied</p>
          <div className="flex flex-wrap gap-2">
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
              Date range: {dateFrom} &rarr; {dateTo}
            </span>
            {activeFilters.map((filter, index) => (
              <span
                key={index}
                className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200"
              >
                {filter.label}: {filter.value}
              </span>
            ))}
            {activeFilters.length === 0 && (
              <span className="inline-flex items-center text-xs text-slate-400">
                No page filters &mdash; the date range above limits this export.
              </span>
            )}
          </div>
        </div>

        {/* Row count preview */}
        <div className="mb-4">
          <p className="text-xs text-slate-500">
            {countState === 'loading' && 'Loading\u2026'}
            {countState === 'ready' && `Approximately ${rowCount} ${rowCount === 1 ? 'row' : 'rows'} will be exported for the selected date range.`}
            {countState === 'error' && 'Could not load the row count. You can still export.'}
          </p>
        </div>

        <div className="flex justify-end gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Cancel
          </button>
          <button
            onClick={handleExport}
            disabled={exporting}
            className="px-4 py-2 bg-blue-900 rounded-md text-sm font-medium text-white hover:bg-blue-800 inline-flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span className="material-symbols-outlined text-[18px]">download</span>
            Export
          </button>
        </div>
      </div>
    </div>
  );
}
