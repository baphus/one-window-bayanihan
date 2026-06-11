import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate } from '@/lib/utils';
import { NotepadText, Delete } from 'lucide-react';

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

  const activeFilterEntries = [
    { key: 'search', label: 'Search', value: initialFilters?.search },
    { key: 'date_from', label: 'From', value: initialFilters?.date_from },
    { key: 'date_to', label: 'To', value: initialFilters?.date_to },
  ].filter((f) => f.value);

  return (
    <AppLayout title="My Drafts">
      <Head title="My Drafts" />

      <div className="max-w-5xl mx-auto pb-6">
        <Link
          href={route('cases.index')}
          className="text-xs text-slate-500 hover:text-gray-900 transition-colors flex items-center gap-1 mb-2"
        >
          <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clipRule="evenodd" />
          </svg>
          Back to Cases
        </Link>
        <header className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">My Drafts</h1>
            <p className="text-sm text-slate-500 mt-0.5">
              Draft cases saved but not yet submitted. Only visible to you.
            </p>
          </div>
          <button
            onClick={() => router.visit(route('cases.create'))}
            className="px-4 py-2 bg-blue-900 text-white rounded-lg text-[13px] font-bold hover:bg-blue-800 transition-colors"
          >
            + New Case
          </button>
        </header>

        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          {/* Filter bar */}
          <div className="px-4 pt-4 pb-3 border-b border-slate-100 space-y-3">
            <div className="flex flex-wrap items-end gap-3">
              <div className="relative flex-1 min-w-[200px]">
                <svg
                  className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <circle cx="11" cy="11" r="8" />
                  <path d="m21 21-4.35-4.35" />
                </svg>
                <input
                  type="text"
                  value={searchValue}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  placeholder="Search by client name or case number..."
                  className="w-full pl-9 pr-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 border border-slate-200 rounded-lg outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900/20 transition-all"
                />
              </div>
              <div className="flex items-center gap-2">
                <div className="flex items-center gap-1.5">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-slate-400">From</label>
                  <input
                    type="date"
                    value={dateFrom}
                    onChange={(e) => handleDateChange('date_from', e.target.value)}
                    className="w-[140px] px-2.5 py-2 text-[12px] text-slate-700 border border-slate-200 rounded-lg outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900/20 transition-all"
                  />
                </div>
                <div className="flex items-center gap-1.5">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-slate-400">To</label>
                  <input
                    type="date"
                    value={dateTo}
                    onChange={(e) => handleDateChange('date_to', e.target.value)}
                    className="w-[140px] px-2.5 py-2 text-[12px] text-slate-700 border border-slate-200 rounded-lg outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900/20 transition-all"
                  />
                </div>
              </div>
            </div>

            {/* Active filter chips */}
            {activeFilterEntries.length > 0 && (
              <div className="flex flex-wrap items-center gap-1.5">
                {activeFilterEntries.map(({ key, label, value }) => (
                  <span
                    key={key}
                    className="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-[11px] font-medium"
                  >
                    {label}: {key === 'search' ? `"${value}"` : value}
                    <button
                      onClick={() => removeFilter(key)}
                      className="hover:text-indigo-900 leading-none"
                    >
                      &times;
                    </button>
                  </span>
                ))}
              </div>
            )}
          </div>

          {drafts.data.length === 0 ? (
            <div className="p-12 text-center">
              <div className="mx-auto w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mb-4">
                <NotepadText className="w-8 h-8 text-amber-400" />
              </div>
              <h3 className="text-base font-bold text-slate-700 mb-1">No Drafts Yet</h3>
              <p className="text-sm text-slate-500 max-w-sm mx-auto">
                When you start creating a case and save it as a draft, it will appear here. Only you can see your drafts.
              </p>
              <button
                onClick={() => router.visit(route('cases.create'))}
                className="mt-4 px-4 py-2 bg-blue-900 text-white rounded-lg text-[13px] font-bold hover:bg-blue-800 transition-colors"
              >
                Create a Case
              </button>
            </div>
          ) : (
            <>
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50/50">
                    <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Case #</th>
                    <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Client</th>
                    <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Type</th>
                    <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Created</th>
                    <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Draft Age</th>
                    <th className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {drafts.data.map((draft) => (
                    <tr key={draft.id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-4 py-3">
                        <button
                          onClick={() => router.visit(route('cases.show', draft.id))}
                          className="text-xs font-bold text-blue-900 hover:underline"
                        >
                          {draft.case_number}
                        </button>
                      </td>
                      <td className="px-4 py-3 text-xs text-slate-700">
                        {getDraftClientName(draft)}
                      </td>
                      <td className="px-4 py-3">
                        <StatusBadge status={draft.client_type === 'OFW' ? 'OFW' : 'NOK'} />
                      </td>
                      <td className="px-4 py-3 text-xs text-slate-500">
                        {draft.created_at ? formatDisplayDate(draft.created_at) : '—'}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold leading-none ${ageColor(draft.updated_at)}`}
                        >
                          {timeAgo(draft.updated_at)}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
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
                <div className="flex items-center justify-between px-4 py-3 border-t border-slate-200 bg-slate-50/50">
                  <span className="text-xs text-slate-500">
                    Page {drafts.current_page} of {drafts.last_page}
                  </span>
                  <div className="flex gap-2">
                    <button
                      onClick={() => goToPage(drafts.current_page - 1)}
                      disabled={drafts.current_page === 1}
                      className="px-3 py-1.5 text-[11px] font-bold rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                      Prev
                    </button>
                    <button
                      onClick={() => goToPage(drafts.current_page + 1)}
                      disabled={drafts.current_page === drafts.last_page}
                      className="px-3 py-1.5 text-[11px] font-bold rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
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
    </AppLayout>
  );
}
