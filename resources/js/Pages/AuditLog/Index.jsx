import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { AuditTimeline } from '@/Components/AuditTimeline';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import { useCallback, useMemo, useState } from 'react';

function toDateInput(date) {
  return date.toISOString().slice(0, 10);
}

function ExportDialog({ open, onClose, filterValues, defaultDays, maxDays }) {
  const today = useMemo(() => new Date(), []);
  const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - defaultDays);
    return toDateInput(d);
  });
  const [dateTo, setDateTo] = useState(() => toDateInput(today));
  const [error, setError] = useState('');

  if (!open) return null;

  const handleExport = () => {
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

    const params = new URLSearchParams();
    ['action', 'module', 'category', 'user_id', 'search'].forEach((key) => {
      if (filterValues?.[key]) params.set(key, filterValues[key]);
    });
    params.set('date_from', dateFrom);
    params.set('date_to', dateTo);

    setError('');
    window.location.href = `/audit-logs/export?${params.toString()}`;
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
      <div className="relative bg-white rounded-lg shadow-xl border border-slate-200 p-6 w-full max-w-md mx-4 owb-modal-animate">
        <h2 className="text-lg font-semibold text-slate-900 mb-1">Export audit logs</h2>
        <p className="text-sm text-slate-500 mb-4">
          Exports a CSV of the current filter selection. An explicit date range is required
          (up to {maxDays} days); the export itself is recorded in the audit log.
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

        <div className="flex justify-end gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Cancel
          </button>
          <button
            onClick={handleExport}
            className="px-4 py-2 bg-blue-900 rounded-md text-sm font-medium text-white hover:bg-blue-800 inline-flex items-center gap-1"
          >
            <span className="material-symbols-outlined text-[18px]">download</span>
            Export CSV
          </button>
        </div>
      </div>
    </div>
  );
}

export default function AuditLogIndex({
  logs,
  availableActions,
  availableModules,
  availableModulesLabels,
  availableCategories,
  activeCategories,
  canExport,
  isScoped = false,
  viewTitle = 'Audit Logs',
  viewSubtitle = 'Track all system activities and changes.',
  exportDefaultDays,
  exportMaxDays,
  filterValues,
}) {
  const [exportOpen, setExportOpen] = useState(false);
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  const handleFilterChange = useCallback((filters) => {
    const url = new URL(window.location);
    const filterKeys = ['action', 'module', 'category', 'user_id', 'date_from', 'date_to', 'search', 'per_page', 'page', 'cursor'];
    filterKeys.forEach(k => url.searchParams.delete(k));
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        url.searchParams.set(key, value);
      }
    });
    router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['logs', 'filterValues', 'activeCategories'] }));
  }, [withLoading]);

  const handleCursorChange = useCallback((cursorUrl) => {
    if (!cursorUrl) return;
    router.get(cursorUrl, {}, withLoading({ preserveState: true, preserveScroll: true, only: ['logs'] }));
  }, [withLoading]);

  const handlePerPageChange = useCallback((event) => {
    handleFilterChange({ ...(filterValues ?? {}), per_page: event.target.value });
  }, [filterValues, handleFilterChange]);

  /* Cursor pagination has no numeric page/current page metadata. */
  const pagination = null;
  /*
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['logs'] }));
  }, [withLoading]);

  const pagination = {
    total: logs.total,
    currentPage: logs.current_page,
    totalPages: logs.last_page,
    from: logs.from,
    to: logs.to,
    perPage: logs.per_page,
  };
  */

  return (
    <AppLayout title={viewTitle}>
      <Head title={viewTitle} />
      <div className="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div data-tour="audit-header" className="mb-8 flex items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className="hidden sm:flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-900 ring-1 ring-blue-100">
              <span className="material-symbols-outlined text-[24px]">history</span>
            </div>
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-bold text-slate-900">{viewTitle}</h1>
                {isScoped && (
                  <span
                    className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200"
                    title="You are viewing only the activity relevant to your role."
                  >
                    <span className="material-symbols-outlined text-[14px]">shield_person</span>
                    Scoped to your role
                  </span>
                )}
              </div>
              <p className="text-sm text-slate-500 mt-1">{viewSubtitle}</p>
            </div>
          </div>
          {canExport && (
            <button
              onClick={() => setExportOpen(true)}
              className="inline-flex flex-shrink-0 items-center gap-1 px-4 py-2 bg-white border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50 shadow-sm"
            >
              <span className="material-symbols-outlined text-[18px]">download</span>
              Export
            </button>
          )}
        </div>

        <div data-tour="audit-timeline" className="relative" aria-busy={tableLoading}>
          <AuditTimeline
            logs={logs.data}
            availableActions={availableActions ?? []}
            availableModules={availableModules ?? []}
            availableModulesLabels={availableModulesLabels ?? {}}
            availableCategories={availableCategories ?? []}
            activeCategories={activeCategories ?? []}
            filterValues={filterValues ?? {}}
            onFilterChange={handleFilterChange}
          />
          <div className="mt-6 flex items-center justify-between gap-4 text-sm text-slate-600">
            <label className="flex items-center gap-2">
              <span>Per page</span>
              <select value={filterValues?.per_page ?? logs.per_page ?? 15} onChange={handlePerPageChange}
                className="rounded-md border border-slate-300 bg-white px-2 py-1">
                {[15, 25, 50, 100].map(size => <option key={size} value={size}>{size}</option>)}
              </select>
            </label>
            <div className="flex gap-2">
              <button type="button" disabled={!logs.prev_page_url} onClick={() => handleCursorChange(logs.prev_page_url)}
                className="rounded-md border border-slate-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
              <button type="button" disabled={!logs.next_page_url} onClick={() => handleCursorChange(logs.next_page_url)}
                className="rounded-md border border-slate-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40">Next</button>
            </div>
          </div>
          {tableLoading && <TableLoadingOverlay variant="list" />}
        </div>
      </div>

      <ExportDialog
        open={exportOpen}
        onClose={() => setExportOpen(false)}
        filterValues={filterValues ?? {}}
        defaultDays={exportDefaultDays ?? 30}
        maxDays={exportMaxDays ?? 365}
      />
    </AppLayout>
  );
}
