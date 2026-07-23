import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import StatusBadge from '@/Components/ui/StatusBadge';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import { useToast } from '@/Hooks/useToast';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';

function getClientName(caseFile) {
  if (caseFile.client) {
    const parts = [caseFile.client.first_name, caseFile.client.last_name].filter(Boolean);
    return parts.join(' ') || '\u2014';
  }
  return '\u2014';
}

function getDeletedByName(caseFile) {
  if (caseFile.user) return caseFile.user.name;
  return '\u2014';
}

export default function CaseTrash({ cases, filters: initialFilters = {} }) {
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [searchValue, setSearchValue] = useState(initialFilters?.search ?? '');
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();
  const toast = useToast();
  const searchTimeout = useRef(null);
  const form = useForm({});

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  function navigateWith(overrides) {
    const url = new URL(window.location);
    Object.entries(overrides).forEach(([k, v]) => {
      if (v) url.searchParams.set(k, v);
      else url.searchParams.delete(k);
    });
    url.searchParams.delete('page');
    router.get(url.toString(), {}, withLoading({ preserveState: true, replace: true }));
  }

  function handleSearchChange(value) {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      navigateWith({ search: value || undefined });
    }, 300);
  }

  function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, withLoading({ preserveState: true, replace: true }));
  }

  function handleRestore(caseId) {
    form.post(route('cases.restore', caseId), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Case restored successfully.');
        setRestoreTarget(null);
      },
      onError: (errors) => {
        const msg = Object.values(errors)[0] || 'Restore failed.';
        toast.error(msg);
        setRestoreTarget(null);
      },
    });
  }

  const activeFilterEntries = [
    { key: 'search', label: 'Search', value: initialFilters?.search },
  ].filter((f) => f.value);

  return (
    <AppLayout title="Case Trash">
      <Head title="Case Trash" />

      <div className="pb-6">
        <header data-tour="trash-header" className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">Case Trash</h1>
            <p className="text-sm text-slate-500 mt-0.5">
              Soft-deleted cases. Restore to return to active use, or cases are auto-purged after the retention period.
            </p>
          </div>
          <button
            onClick={() => router.visit(route('cases.index'))}
            className="h-[40px] px-4 bg-slate-100 text-slate-700 text-[13px] font-bold rounded-[3px] flex items-center gap-2 hover:bg-slate-200 transition-colors border border-slate-300"
          >
            <span className="material-symbols-outlined text-[17px]">arrow_back</span>
            Back to Cases
          </button>
        </header>

        <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
          {/* Filter bar */}
          <div data-tour="trash-filters" className="p-4 bg-slate-50 border-b border-slate-300 space-y-3">
            <div className="flex flex-wrap items-end gap-3">
              <div className="relative flex-1 min-w-[200px]">
                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">
                  search
                </span>
                <input
                  type="text"
                  value={searchValue}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  placeholder="Search by client name or case number..."
                  className="w-full h-[40px] pl-10 pr-10 bg-white border border-slate-300 rounded-[2px] text-[14px] text-slate-600 placeholder-slate-400 outline-none focus:ring-1 focus:ring-blue-900 transition-all"
                />
              </div>
            </div>

            {/* Active filter chips */}
            {activeFilterEntries.length > 0 && (
              <div className="flex flex-wrap items-center gap-4 text-[12px]">
                <span className="font-bold uppercase tracking-widest text-slate-400">Active Filters:</span>
                {activeFilterEntries.map(({ key, label, value }) => (
                  <span
                    key={key}
                    className="inline-flex items-center gap-1.5 px-3 py-1 bg-[#f0f7fc] text-blue-900 rounded-[2px] font-bold border border-[#d2e5f3]"
                  >
                    {label}: {key === 'search' ? `"${value}"` : value}
                    <button
                      onClick={() => {
                        setSearchValue('');
                        navigateWith({ search: undefined });
                      }}
                      className="flex items-center justify-center hover:opacity-75 transition-opacity"
                    >
                      <span className="material-symbols-outlined text-[15px]">close</span>
                    </button>
                  </span>
                ))}
              </div>
            )}
          </div>

          <div data-tour="trash-table" className="relative" aria-busy={tableLoading}>
            {cases.data.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">delete_outline</span>
                <p className="text-[14px] font-bold text-slate-700">Trash is Empty</p>
                <p className="mt-1 max-w-sm text-xs text-slate-500">
                  Deleted archived cases appear here. They can be restored within the retention period.
                </p>
              </div>
            ) : (
              <>
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-300">
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Case #</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Client</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Deleted</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Deleted By</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Reason</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200 bg-white">
                    {cases.data.map((caseFile) => (
                      <tr key={caseFile.id} className="hover:bg-slate-50 transition-colors">
                        <td className="px-5 py-4">
                          <span className="text-xs font-bold text-slate-700">{caseFile.case_number}</span>
                          <div className="mt-0.5">
                            <StatusBadge status="ARCHIVED" size="xs" />
                          </div>
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-700">
                          {getClientName(caseFile)}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-500">
                          {caseFile.deleted_at ? formatDisplayDateTime(caseFile.deleted_at) : '—'}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-500">
                          {getDeletedByName(caseFile)}
                        </td>
                        <td className="px-5 py-4 max-w-xs">
                          {caseFile.deletion_reason ? (
                            <p className="text-xs text-slate-600 line-clamp-2" title={caseFile.deletion_reason}>
                              {caseFile.deletion_reason}
                            </p>
                          ) : (
                            <span className="text-xs text-slate-400">—</span>
                          )}
                        </td>
                        <td className="px-5 py-4 text-right">
                          <button
                            onClick={() => setRestoreTarget(caseFile)}
                            disabled={form.processing}
                            className="min-h-[28px] px-4 bg-emerald-600 text-white hover:bg-emerald-700 text-[11px] font-bold rounded-[3px] transition-colors disabled:opacity-60 inline-flex items-center gap-1.5"
                          >
                            <span className="material-symbols-outlined text-[15px]">restore</span>
                            Restore
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* Pagination */}
                {cases.last_page > 1 && (
                  <div className="px-6 py-4 bg-slate-50 border-t border-slate-300 flex items-center justify-between">
                    <span className="text-xs text-slate-500">
                      Showing {cases.from}–{cases.to} of {cases.total} trashed cases
                    </span>
                    <div className="flex gap-2">
                      <button
                        onClick={() => goToPage(cases.current_page - 1)}
                        disabled={cases.current_page === 1}
                        className="px-3 py-1.5 text-[11px] font-bold rounded-[2px] border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        Prev
                      </button>
                      <button
                        onClick={() => goToPage(cases.current_page + 1)}
                        disabled={cases.current_page === cases.last_page}
                        className="px-3 py-1.5 text-[11px] font-bold rounded-[2px] border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        Next
                      </button>
                    </div>
                  </div>
                )}
              </>
            )}
            {tableLoading && <TableLoadingOverlay variant="table" />}
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={!!restoreTarget}
        title="Restore Case?"
        message={restoreTarget
          ? `Restore case "${restoreTarget.case_number}" from trash? It will return to ARCHIVED status and become visible again.`
          : ''}
        confirmLabel="Restore"
        cancelLabel="Cancel"
        tone="default"
        onConfirm={() => handleRestore(restoreTarget.id)}
        onCancel={() => setRestoreTarget(null)}
      />
    </AppLayout>
  );
}
