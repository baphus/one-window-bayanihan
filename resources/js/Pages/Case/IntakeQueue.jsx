import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { formatDisplayDate } from '@/lib/utils';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import { useToast } from '@/Hooks/useToast';
import { Inbox, Mail, CalendarClock, Tag } from 'lucide-react';

function getClientName(caseItem) {
  if (caseItem.client) {
    return `${caseItem.client.first_name} ${caseItem.client.last_name}`;
  }
  if (caseItem.draft_client_data?.first_name || caseItem.draft_client_data?.last_name) {
    return `${caseItem.draft_client_data.first_name ?? ''} ${caseItem.draft_client_data.last_name ?? ''}`.trim();
  }
  return '—';
}

function getClientEmail(caseItem) {
  if (caseItem.client?.email) return caseItem.client.email;
  if (caseItem.draft_client_data?.email) return caseItem.draft_client_data.email;
  return '—';
}

function getCategoryNames(caseItem) {
  if (caseItem.categories && caseItem.categories.length > 0) {
    return caseItem.categories.map((c) => c.name).join(', ');
  }
  if (caseItem.category) return caseItem.category.name;
  return '—';
}

function hasEmail(caseItem) {
  return getClientEmail(caseItem) !== '—';
}

export default function IntakeQueue({ cases, filters: initialFilters = {} }) {
  const [searchValue, setSearchValue] = useState(initialFilters?.search ?? '');
  const [emailFilter, setEmailFilter] = useState('');
  const [rejectTarget, setRejectTarget] = useState(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);
  const [perPage, setPerPage] = useState(cases.per_page ?? 15);
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();
  const toast = useToast();
  const searchTimeout = useRef(null);

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  /* ── Navigation helpers ─────────────────────────────────────── */

  function navigateWith(overrides) {
    const url = new URL(window.location);
    Object.entries(overrides).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
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

  function handlePerPageChange(newPerPage) {
    setPerPage(newPerPage);
    navigateWith({ per_page: newPerPage, page: undefined });
  }

  /* ── Email quick-filter (client-side) ──────────────────────── */
  // TODO: For proper cross-page filtering, add a server-side `email_filter`
  // param to CaseController::intakeQueue and CaseService::getIntakeQueue.
  const handleEmailFilter = useCallback((value) => {
    setEmailFilter((prev) => (prev === value ? '' : value));
  }, []);

  /* ── Stats (computed from current page — approximate) ───────── */
  // TODO: Add a dedicated `getIntakeQueueStats()` method to CaseService
  // that returns accurate cross-page counts, similar to getCaseStats().
  const pageStats = useMemo(() => {
    const data = cases.data || [];
    const withEmailCount = data.filter((c) => hasEmail(c)).length;
    const now = new Date();
    const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    const thisWeekCount = data.filter((c) => {
      if (!c.created_at) return false;
      return new Date(c.created_at) >= weekAgo;
    }).length;
    const categories = new Set();
    data.forEach((c) => {
      if (c.categories?.length) c.categories.forEach((cat) => categories.add(cat.name));
      else if (c.category?.name) categories.add(c.category.name);
    });
    return {
      total: cases.total,
      withEmail: withEmailCount,
      thisWeek: thisWeekCount,
      categoryCount: categories.size,
    };
  }, [cases]);

  /* ── Client-side filtered rows ─────────────────────────────── */
  const filteredData = useMemo(() => {
    if (!emailFilter) return cases.data;
    return cases.data.filter((c) => {
      return emailFilter === 'yes' ? hasEmail(c) : !hasEmail(c);
    });
  }, [cases.data, emailFilter]);

  /* ── Quick-filter pill counts ──────────────────────────────── */
  // "All" uses the accurate server total; email-based counts are page-level.
  const quickFilterCounts = useMemo(() => {
    const data = cases.data || [];
    const withEmail = data.filter((c) => hasEmail(c)).length;
    return { all: cases.total, withEmail, noEmail: data.length - withEmail };
  }, [cases]);

  /* ── Reject handler ────────────────────────────────────────── */

  function handleReject() {
    if (!rejectTarget || rejectReason.length < 10) return;
    setRejecting(true);
    router.post(route('cases.reject-intake', rejectTarget), {
      deletion_reason: rejectReason,
    }, {
      onSuccess: () => {
        setRejectTarget(null);
        setRejectReason('');
        toast.success('Intake submission rejected.');
      },
      onError: (errors) => {
        const msg = errors?.deletion_reason || Object.values(errors)[0] || 'Failed to reject intake.';
        toast.error(msg);
      },
      onFinish: () => setRejecting(false),
    });
  }

  /* ── Pagination helpers ────────────────────────────────────── */

  const totalPages = cases.last_page;
  const currentPage = cases.current_page;

  const pageNumbers = useMemo(() => {
    if (totalPages <= 7) {
      return Array.from({ length: totalPages }, (_, i) => i + 1);
    }
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(2, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages - 1, start + maxVisible - 1);
    if (end - start < maxVisible - 1) {
      start = Math.max(2, end - maxVisible + 1);
    }
    pages.push(1);
    if (start > 2) pages.push('…s');
    for (let i = start; i <= end; i++) pages.push(i);
    if (end < totalPages - 1) pages.push('…e');
    pages.push(totalPages);
    return pages;
  }, [currentPage, totalPages]);

  /* ── Render ────────────────────────────────────────────────── */

  return (
    <AppLayout title="Intake Queue">
      <Head title="Intake Queue" />

      <div className="pb-6">
        {/* ── Page header ──────────────────────────────────── */}
        <header className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
          <div>
            <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
              Intake Queue
            </h1>
            <p className="text-sm text-slate-400 font-body mt-0.5">
              Self-filed OFW submissions awaiting review and approval.
            </p>
          </div>
        </header>

        {/* ── Summary stat cards ───────────────────────────── */}
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending Intakes</p>
              <span className="p-1.5 bg-blue-50 rounded-lg"><Inbox className="w-4 h-4 text-blue-900" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{pageStats.total}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">Awaiting review</p>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">With Email</p>
              <span className="p-1.5 bg-emerald-50 rounded-lg"><Mail className="w-4 h-4 text-emerald-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{pageStats.withEmail}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">On this page</p>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">This Week</p>
              <span className="p-1.5 bg-violet-50 rounded-lg"><CalendarClock className="w-4 h-4 text-violet-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{pageStats.thisWeek}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">Submitted recently</p>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Categories</p>
              <span className="p-1.5 bg-amber-50 rounded-lg"><Tag className="w-4 h-4 text-amber-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{pageStats.categoryCount}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">Distinct categories</p>
          </div>
        </section>

        {/* ── Table card ───────────────────────────────────── */}
        <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
          {/* Toolbar: quick filters + search */}
          <div className="p-4 bg-slate-50 border-b border-slate-300">
            <div className="flex flex-wrap items-center gap-3">
              {/* Quick filter pills */}
              <div className="flex items-center gap-1.5" role="group" aria-label="Quick email filters">
                <span className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mr-1">Show:</span>
                {[
                  { label: 'All', value: '', count: quickFilterCounts.all },
                  { label: 'With Email', value: 'yes', count: quickFilterCounts.withEmail },
                  { label: 'No Email', value: 'no', count: quickFilterCounts.noEmail },
                ].map((f) => {
                  const isActive = emailFilter === f.value || (f.value === '' && emailFilter === '');
                  return (
                    <button
                      key={f.label}
                      onClick={() => handleEmailFilter(f.value)}
                      className={`px-3 py-1.5 text-[12px] font-bold rounded-md transition-colors border ${
                        isActive
                          ? 'bg-blue-900 text-white border-blue-900 shadow-sm'
                          : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50 hover:text-slate-800'
                      }`}
                    >
                      {f.label}
                      {f.count > 0 && ` (${f.count})`}
                    </button>
                  );
                })}
              </div>

              {/* Search */}
              <div className="relative flex-1 min-w-[200px]">
                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">
                  search
                </span>
                <input
                  type="text"
                  value={searchValue}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  placeholder="Search by OFW name..."
                  className="w-full h-[40px] pl-10 pr-10 bg-white border border-slate-300 rounded-[2px] text-[14px] text-slate-600 placeholder-slate-400 outline-none focus:ring-1 focus:ring-blue-900 transition-all"
                />
              </div>
            </div>
          </div>

          {/* Table body */}
          <div className="relative" aria-busy={tableLoading}>
            {tableLoading && <TableLoadingOverlay />}

            {filteredData.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
                <p className="text-[14px] font-bold text-slate-700">
                  {emailFilter ? 'No matching submissions' : 'No Pending Intake Submissions'}
                </p>
                <p className="mt-1 max-w-sm text-xs text-slate-500">
                  {emailFilter
                    ? 'Try adjusting your filters to see more results.'
                    : 'When OFWs submit a case through the self-filing portal, their submissions will appear here for review.'}
                </p>
              </div>
            ) : (
              <>
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-300">
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">OFW Name</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Email</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Submitted</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Categories</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-300 bg-white">
                    {filteredData.map((caseItem) => (
                      <tr key={caseItem.id} className="hover:bg-slate-100 transition-colors">
                        <td className="px-5 py-4 text-xs font-medium text-slate-800">
                          {getClientName(caseItem)}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-600">
                          {getClientEmail(caseItem)}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-500">
                          {caseItem.created_at ? formatDisplayDate(caseItem.created_at) : '—'}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-600">
                          <span className="truncate max-w-[200px] inline-block">
                            {getCategoryNames(caseItem)}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-right">
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => router.visit(route('cases.review-intake', caseItem.id))}
                              className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors border border-blue-900"
                            >
                              Review
                            </button>
                            <button
                              onClick={() => setRejectTarget(caseItem.id)}
                              className="min-h-[28px] px-2.5 bg-white text-red-600 border border-red-200 hover:bg-red-50 text-[11px] font-bold rounded-[3px] transition-colors"
                            >
                              Reject
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* ── Pagination footer ──────────────────────── */}
                <div className="px-6 py-4 bg-slate-50 border-t border-slate-300 flex items-center justify-between flex-wrap gap-4">
                  <div className="flex items-center gap-3">
                    <span className="text-xs text-slate-500">
                      Showing {cases.from}&ndash;{cases.to} of {cases.total} records
                    </span>
                    <div className="flex items-center gap-1.5">
                      <label htmlFor="intake-queue-per-page" className="text-xs text-slate-500">Rows:</label>
                      <select
                        id="intake-queue-per-page"
                        value={perPage}
                        onChange={(e) => handlePerPageChange(Number(e.target.value))}
                        className="h-8 border border-slate-300 rounded-[2px] px-2 text-xs font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900 bg-white"
                      >
                        <option value={15}>15</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                      </select>
                    </div>
                  </div>

                  {totalPages > 1 && (
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => goToPage(1)}
                        disabled={currentPage === 1}
                        className="px-2 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        title="First page"
                      >
                        <span className="material-symbols-outlined text-[16px]">first_page</span>
                      </button>
                      <button
                        onClick={() => goToPage(currentPage - 1)}
                        disabled={currentPage === 1}
                        className="px-2 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        title="Previous page"
                      >
                        <span className="material-symbols-outlined text-[16px]">chevron_left</span>
                      </button>

                      {pageNumbers.map((page) => {
                        if (typeof page === 'string') {
                          return (
                            <span key={page} className="px-1 text-xs text-slate-400 select-none">
                              &hellip;
                            </span>
                          );
                        }
                        return (
                          <button
                            key={page}
                            onClick={() => goToPage(page)}
                            className={`min-w-[32px] px-2 py-1.5 text-xs font-bold rounded transition-colors border ${
                              page === currentPage
                                ? 'bg-blue-900 text-white border-blue-900'
                                : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-100'
                            }`}
                          >
                            {page}
                          </button>
                        );
                      })}

                      <button
                        onClick={() => goToPage(currentPage + 1)}
                        disabled={currentPage === totalPages}
                        className="px-2 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        title="Next page"
                      >
                        <span className="material-symbols-outlined text-[16px]">chevron_right</span>
                      </button>
                      <button
                        onClick={() => goToPage(totalPages)}
                        disabled={currentPage === totalPages}
                        className="px-2 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        title="Last page"
                      >
                        <span className="material-symbols-outlined text-[16px]">last_page</span>
                      </button>
                    </div>
                  )}
                </div>
              </>
            )}
          </div>
        </div>
      </div>

      {/* ── Reject Confirmation Dialog ──────────────────────── */}
      <ConfirmDialog
        open={!!rejectTarget}
        onClose={() => { setRejectTarget(null); setRejectReason(''); }}
        onConfirm={handleReject}
        title="Reject Intake Submission"
        confirmLabel={rejecting ? 'Rejecting...' : 'Reject'}
        confirmVariant="danger"
        disabled={rejecting || rejectReason.length < 10}
      >
        <p className="text-sm text-slate-600 mb-3">
          This will reject the OFW's submission. Please provide a reason (minimum 10 characters):
        </p>
        <textarea
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          placeholder="Reason for rejection..."
          rows={3}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:ring-1 focus:ring-blue-900 outline-none"
        />
        {rejectReason.length > 0 && rejectReason.length < 10 && (
          <p className="text-xs text-red-500 mt-1">Reason must be at least 10 characters.</p>
        )}
      </ConfirmDialog>
    </AppLayout>
  );
}
