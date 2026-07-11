import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import StatusBadge from '@/Components/ui/StatusBadge';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import { formatDisplayDate } from '@/lib/utils';
import { Delete } from 'lucide-react';

function timeAgo(dateStr) {
  if (!dateStr) return '—';
  const now = new Date();
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) return '—';
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} min ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 30) return `${diffDays}d ago`;
  return formatDisplayDate(dateStr);
}

function ageColor(dateStr) {
  if (!dateStr) return 'bg-slate-100 text-slate-500';
  const diffDays = (new Date() - new Date(dateStr)) / (1000 * 60 * 60 * 24);
  if (diffDays < 1) return 'bg-green-100 text-green-700';
  if (diffDays <= 7) return 'bg-amber-100 text-amber-700';
  return 'bg-red-100 text-red-700';
}

function getDraftClientName(draft) {
  if (draft.client) {
    return `${draft.client.first_name} ${draft.client.last_name}`;
  }
  if (draft.draft_client_data?.first_name || draft.draft_client_data?.last_name) {
    return `${draft.draft_client_data.first_name ?? ''} ${draft.draft_client_data.last_name ?? ''}`.trim();
  }
  return '\u2014';
}

export default function DraftIndex({ drafts, filters: initialFilters = {} }) {
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [publishing, setPublishing] = useState(null);
  const [contextMenu, setContextMenu] = useState(null);
  const [searchValue, setSearchValue] = useState(initialFilters?.search ?? '');
  const [dateFrom, setDateFrom] = useState(initialFilters?.date_from ?? '');
  const [dateTo, setDateTo] = useState(initialFilters?.date_to ?? '');

  const searchTimeout = useRef(null);

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
    router.get(url.toString(), {}, { preserveState: true, replace: true });
  }

  function handleSearchChange(value) {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      navigateWith({ search: value || undefined });
    }, 300);
  }

  function handleDateChange(key, value) {
    if (key === 'date_from') setDateFrom(value);
    if (key === 'date_to') setDateTo(value);
    navigateWith({ [key]: value || undefined });
  }

  function removeFilter(key) {
    if (key === 'search') setSearchValue('');
    if (key === 'date_from') setDateFrom('');
    if (key === 'date_to') setDateTo('');
    navigateWith({ [key]: undefined });
  }

  function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, { preserveState: true, replace: true });
  }

  function handlePublish(id) {
    setPublishing(id);
    router.post(route('cases.publish', id), {}, {
      onFinish: () => setPublishing(null),
    });
  }

  function handleDelete(id) {
    router.delete(route('cases.drafts.destroy', id), {
      preserveScroll: true,
      onSuccess: () => setDeleteTarget(null),
    });
  }

  function handleRowContextMenu(e, draft) {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row: draft });
  }

  const activeFilterEntries = [
    { key: 'search', label: 'Search', value: initialFilters?.search },
    { key: 'date_from', label: 'From', value: initialFilters?.date_from },
    { key: 'date_to', label: 'To', value: initialFilters?.date_to },
  ].filter((f) => f.value);

  return (
    <AppLayout title="My Drafts">
      <Head title="My Drafts" />

      <div className="pb-6">
        <header data-tour="drafts-header" className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">My Drafts</h1>
            <p className="text-sm text-slate-500 mt-0.5">
              Draft cases saved but not yet submitted. Only visible to you.
            </p>
          </div>
          <div data-tour="drafts-new-case" className="flex items-center gap-3">
            <button
              onClick={() => router.visit(route('cases.create'))}
              className="h-[40px] px-5 bg-blue-900 text-white text-[14px] font-bold rounded-[3px] flex items-center gap-2 hover:bg-blue-800 transition-colors shadow-sm"
            >
              + New Case
            </button>
          </div>
        </header>

        <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
          {/* Filter bar */}
          <div data-tour="drafts-filters" className="p-4 bg-slate-50 border-b border-slate-300 space-y-3">
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
              <div className="flex items-center gap-2">
                <div className="flex items-center gap-1.5">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-slate-400">From</label>
                    <input
                      type="date"
                      value={dateFrom}
                      onChange={(e) => handleDateChange('date_from', e.target.value)}
                      className="w-[140px] h-[40px] px-2.5 text-[13px] text-slate-600 border border-slate-300 rounded-[2px] outline-none focus:ring-1 focus:ring-blue-900 transition-all"
                    />
                  </div>
                  <div className="flex items-center gap-1.5">
                    <label className="text-[10px] font-bold uppercase tracking-widest text-slate-400">To</label>
                    <input
                      type="date"
                      value={dateTo}
                      onChange={(e) => handleDateChange('date_to', e.target.value)}
                      className="w-[140px] h-[40px] px-2.5 text-[13px] text-slate-600 border border-slate-300 rounded-[2px] outline-none focus:ring-1 focus:ring-blue-900 transition-all"
                  />
                </div>
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
                      onClick={() => removeFilter(key)}
                      className="flex items-center justify-center hover:opacity-75 transition-opacity"
                    >
                      <span className="material-symbols-outlined text-[15px]">close</span>
                    </button>
                  </span>
                ))}
              </div>
            )}
          </div>

          <div data-tour="drafts-table">
          {drafts.data.length === 0 ? (
            <div className="flex flex-col items-center justify-center p-12 text-center">
              <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
              <p className="text-[14px] font-bold text-slate-700">No Drafts Yet</p>
              <p className="mt-1 max-w-sm text-xs text-slate-500">
                When you start creating a case and save it as a draft, it will appear here. Only you can see your drafts.
              </p>
              <button
                onClick={() => router.visit(route('cases.create'))}
                className="mt-4 h-[40px] px-5 bg-blue-900 text-white text-[14px] font-bold rounded-[3px] hover:bg-blue-800 transition-colors shadow-sm"
              >
                Create a Case
              </button>
            </div>
          ) : (
            <>
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-300">
                    <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Case #</th>
                    <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Client</th>
                    <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Type</th>
                    <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Created</th>
                    <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Draft Age</th>
                    <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-300 bg-white">
                  {drafts.data.map((draft) => (
                    <tr key={draft.id} className="hover:bg-slate-100 transition-colors cursor-context-menu" onContextMenu={(e) => handleRowContextMenu(e, draft)}>
                      <td className="px-5 py-4">
                        <button
                          onClick={() => router.visit(route('cases.show', draft.id))}
                          className="text-xs font-bold text-blue-900 hover:underline"
                        >
                          {draft.case_number}
                        </button>
                      </td>
                      <td className="px-5 py-4 text-xs text-slate-700">
                        {getDraftClientName(draft)}
                      </td>
                      <td className="px-5 py-4">
                        <StatusBadge status={draft.client_type === 'OFW' ? 'OFW' : 'NOK'} />
                      </td>
                      <td className="px-5 py-4 text-xs text-slate-500">
                        {draft.created_at ? formatDisplayDate(draft.created_at) : '—'}
                      </td>
                      <td className="px-5 py-4">
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold leading-none ${ageColor(draft.updated_at)}`}
                        >
                          {timeAgo(draft.updated_at)}
                        </span>
                      </td>
                      <td className="px-5 py-4 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => router.visit(route('cases.edit-draft', draft.id))}
                            className="min-h-[28px] px-3 bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50 text-[11px] font-bold rounded-[3px] transition-colors"
                          >
                            <span className="material-symbols-outlined text-[16px] align-middle">edit</span>
                          </button>
                          <button
                            onClick={() => handlePublish(draft.id)}
                            disabled={publishing === draft.id}
                            className="min-h-[28px] px-3 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors disabled:opacity-60"
                          >
                            {publishing === draft.id ? '...' : 'Publish'}
                          </button>
                          <button
                            onClick={() => setDeleteTarget(draft.id)}
                            className="min-h-[28px] px-3 bg-white text-red-600 border border-red-200 hover:bg-red-50 text-[11px] font-bold rounded-[3px] transition-colors"
                          >
                            <Delete className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Pagination */}
              {drafts.last_page > 1 && (
                <div className="px-6 py-4 bg-slate-50 border-t border-slate-300 flex items-center justify-between">
                  <span className="text-xs text-slate-500">
                    Page {drafts.current_page} of {drafts.last_page}
                  </span>
                  <div className="flex gap-2">
                    <button
                      onClick={() => goToPage(drafts.current_page - 1)}
                      disabled={drafts.current_page === 1}
                      className="px-3 py-1.5 text-[11px] font-bold rounded-[2px] border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                      Prev
                    </button>
                    <button
                      onClick={() => goToPage(drafts.current_page + 1)}
                      disabled={drafts.current_page === drafts.last_page}
                      className="px-3 py-1.5 text-[11px] font-bold rounded-[2px] border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                      Next
                    </button>
                  </div>
                </div>
              )}
            </>
          )}
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete Draft?"
        message="This will permanently delete this draft case and all associated client data. This action cannot be undone."
        confirmLabel="Delete"
        cancelLabel="Cancel"
        tone="danger"
        onConfirm={() => handleDelete(deleteTarget)}
        onCancel={() => setDeleteTarget(null)}
      />

      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            setContextMenu(null);
            router.visit(route('cases.edit-draft', contextMenu.row.id));
          }} />
          <RowContextMenuItem icon="publish" label="Publish" onClick={() => {
            setContextMenu(null);
            handlePublish(contextMenu.row.id);
          }} />
          <RowContextMenuItem icon="delete" label="Delete" variant="danger" onClick={() => {
            setContextMenu(null);
            setDeleteTarget(contextMenu.row.id);
          }} />
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
