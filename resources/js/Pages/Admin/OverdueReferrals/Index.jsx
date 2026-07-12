import { useCallback, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import OverdueCard from './OverdueCard';

const ROLE_SUBTITLES = {
  ADMIN: 'Overdue referrals across all agencies — sorted by most stale first',
  CASE_MANAGER: 'Overdue referrals from your cases — sorted by most stale first',
  AGENCY: 'Referrals sent to your agency that need attention — sorted by most stale first',
};

const BOTTLENECK_LABELS = {
  pending: 'Pending review — waiting for agency to begin processing',
  processing: 'In progress — agencies are working on these',
  for_compliance: 'Awaiting compliance documents — follow up with agencies',
};

const SORT_OPTIONS = [
  { value: 'most_stale', label: 'Most Stale' },
  { value: 'status', label: 'Status' },
  { value: 'client_name', label: 'Client Name' },
];

const STATUS_FILTER_OPTIONS = [
  { value: 'all', label: 'All Statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'processing', label: 'Processing' },
  { value: 'for_compliance', label: 'For Compliance' },
];

export default function OverdueReferralsIndex({ stats = {}, referrals, userRole, overdueDays }) {
  const canRemind = userRole === 'ADMIN' || userRole === 'CASE_MANAGER';
  const total = stats.total ?? 0;

  const [selectedIds, setSelectedIds] = useState([]);
  const [sending, setSending] = useState(null); // referral ID being reminded, or '__all__'
  const [confirmDialog, setConfirmDialog] = useState(null); // { type, referralId? }
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  // Derive current sort/filter from URL
  const currentSort = new URL(window.location).searchParams.get('sort_by') || 'most_stale';
  const currentFilter = new URL(window.location).searchParams.get('status_filter') || 'all';

  const handleSelect = useCallback((id) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    );
  }, []);

  const handleSelectAll = useCallback(() => {
    const allIds = referrals?.data?.map((r) => r.id) ?? [];
    if (selectedIds.length === allIds.length && allIds.length > 0) {
      setSelectedIds([]);
    } else {
      setSelectedIds(allIds);
    }
  }, [referrals, selectedIds]);

  const handleSendReminder = useCallback(
    (referralId) => {
      setConfirmDialog({ type: 'single', referralId });
    },
    [],
  );

  const confirmSend = useCallback(() => {
    if (!confirmDialog) return;
    const ids =
      confirmDialog.type === 'batch'
        ? selectedIds
        : confirmDialog.referralId
          ? [confirmDialog.referralId]
          : [];
    if (ids.length === 0 && confirmDialog.type === 'all') {
      // Send all: empty array means "all overdue"
    }

    setSending(confirmDialog.referralId ?? '__all__');
    setConfirmDialog(null);

    router.post(
      route('overdue-referrals.send-reminders'),
      { referral_ids: confirmDialog.type === 'all' ? [] : ids },
      {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
          setSending(null);
          setSelectedIds([]);
        },
      },
    );
  }, [confirmDialog, selectedIds]);

  const handleSortChange = useCallback((e) => {
    const url = new URL(window.location);
    url.searchParams.set('sort_by', e.target.value);
    url.searchParams.delete('page');
    router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['referrals'] }));
  }, [withLoading]);

  const handleFilterChange = useCallback((e) => {
    const url = new URL(window.location);
    url.searchParams.set('status_filter', e.target.value);
    url.searchParams.delete('page');
    router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['referrals'] }));
  }, [withLoading]);

  const handlePageChange = useCallback((page) => {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['referrals'] }));
  }, [withLoading]);

  const paginator = referrals;
  const totalPages = paginator?.last_page ?? 1;
  const currentPage = paginator?.current_page ?? 1;

  // Build status breakdown
  const statusBreakdown = useMemo(() => {
    const items = [
      { key: 'PENDING', label: 'Pending', count: stats.pending_count ?? 0 },
      { key: 'PROCESSING', label: 'Processing', count: stats.processing_count ?? 0 },
      { key: 'FOR_COMPLIANCE', label: 'For Compliance', count: stats.for_compliance_count ?? 0 },
    ];
    const maxCount = Math.max(...items.map((i) => i.count), 1);
    return items.map((item) => ({
      ...item,
      pct: total > 0 ? Math.round((item.count / total) * 100) : 0,
      widthPct: Math.round((item.count / maxCount) * 100),
    }));
  }, [stats, total]);

  return (
    <AppLayout title="Overdue Referrals">
      <Head title="Overdue Referrals" />

      <div className="space-y-6">
        {/* Header */}
        <div data-tour="overdue-header" className="flex items-start justify-between flex-wrap gap-4">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Overdue Referrals</h1>
            <p className="text-sm text-slate-500 mt-1">
              {ROLE_SUBTITLES[userRole] ?? ROLE_SUBTITLES.ADMIN}
            </p>
          </div>

          {/* Batch actions (admin/cm only) */}
          {canRemind && total > 0 && (
            <div className="flex items-center gap-3">
              <button
                onClick={() => setConfirmDialog({ type: 'all' })}
                disabled={sending === '__all__'}
                className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 disabled:opacity-50 flex items-center gap-2"
              >
                <span className="material-symbols-outlined text-[16px]">notifications_active</span>
                Send All Reminders
              </button>
            </div>
          )}
        </div>

        {/* Section 1: KPI Cards */}
        <div data-tour="overdue-kpis" className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard
            title="Total Overdue"
            value={total}
            icon="warning"
            iconBg="bg-rose-50"
            iconColor="text-rose-700"
          />
          <KpiCard
            title={`Mild (${overdueDays}–14d)`}
            value={stats.mild_count ?? 0}
            icon="schedule"
            iconBg="bg-amber-50"
            iconColor="text-amber-700"
          />
          <KpiCard
            title="Moderate (15–29d)"
            value={stats.moderate_count ?? 0}
            icon="hourglass_bottom"
            iconBg="bg-orange-50"
            iconColor="text-orange-700"
          />
          <KpiCard
            title="Severe (30d+)"
            value={stats.severe_count ?? 0}
            icon="emergency"
            iconBg="bg-rose-50"
            iconColor="text-rose-700"
          />
        </div>

        {/* Section 2: Status Breakdown */}
        {total > 0 && (
          <section data-tour="overdue-breakdown" className="bg-white rounded-md border border-slate-200 p-5">
            <h2 className="text-sm font-bold text-slate-700 mb-3">Status Breakdown</h2>
            <div className="space-y-2.5">
              {statusBreakdown.map((item) => (
                <div key={item.key} className="flex items-center gap-3">
                  <StatusBadge status={item.key} size="sm" />
                  <div className="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full transition-all duration-500 ${
                        item.key === 'PENDING'
                          ? 'bg-amber-400'
                          : item.key === 'PROCESSING'
                            ? 'bg-blue-400'
                            : 'bg-orange-400'
                      }`}
                      style={{ width: `${item.widthPct}%` }}
                    />
                  </div>
                  <span className="text-xs font-semibold text-slate-600 min-w-[3ch] text-right">
                    {item.pct}%
                  </span>
                  <span className="text-xs text-slate-400 min-w-[3ch] text-right">
                    {item.count}
                  </span>
                </div>
              ))}
            </div>
            <p className="mt-3 text-[11px] text-slate-400 italic">
              Bottleneck:{' '}
              {BOTTLENECK_LABELS[stats.bottleneck] ??
                'Distributed evenly across statuses'}
            </p>
          </section>
        )}

        {/* Section 3: Sort/Filter + Card List */}
        {total > 0 && (
          <div className="relative" aria-busy={tableLoading}>
            {/* Sort/Filter bar */}
            <div data-tour="overdue-filters" className="flex items-center justify-between flex-wrap gap-3">
              <div className="flex items-center gap-3">
                <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">
                  Sort by
                </label>
                <select
                  value={currentSort}
                  onChange={handleSortChange}
                  className="text-sm border border-slate-200 rounded-md px-3 py-1.5 bg-white text-slate-700 focus:ring-blue-900 focus:border-blue-900"
                >
                  {SORT_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex items-center gap-3">
                <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">
                  Status
                </label>
                <select
                  value={currentFilter}
                  onChange={handleFilterChange}
                  className="text-sm border border-slate-200 rounded-md px-3 py-1.5 bg-white text-slate-700 focus:ring-blue-900 focus:border-blue-900"
                >
                  {STATUS_FILTER_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Batch selection bar (admin/cm only) */}
            {canRemind && selectedIds.length > 0 && (
              <div className="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-md px-4 py-2.5">
                <span className="text-sm text-blue-800 font-medium">
                  {selectedIds.length} selected
                </span>
                <button
                  onClick={() => setConfirmDialog({ type: 'batch' })}
                  disabled={sending === '__all__'}
                  className="px-3 py-1.5 text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-md hover:bg-amber-100 disabled:opacity-50 flex items-center gap-1"
                >
                  <span className="material-symbols-outlined text-[14px]">notifications</span>
                  Send Reminder
                </button>
                <button
                  onClick={() => setSelectedIds([])}
                  className="px-3 py-1.5 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors"
                >
                  Clear selection
                </button>
              </div>
            )}

            {/* Card list */}
            <div data-tour="overdue-list" className="space-y-2.5">
              {/* Select all checkbox (admin/cm only) */}
              {canRemind && referrals?.data?.length > 0 && (
                <label className="flex items-center gap-2 px-1 text-xs text-slate-400 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === referrals.data.length}
                    onChange={handleSelectAll}
                    className="h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900 cursor-pointer"
                  />
                  Select all on this page
                </label>
              )}

              {referrals?.data?.map((referral) => (
                <OverdueCard
                  key={referral.id}
                  referral={referral}
                  userRole={userRole}
                  selected={selectedIds.includes(referral.id)}
                  onSelect={handleSelect}
                  onSendReminder={handleSendReminder}
                  sending={sending}
                />
              ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <nav className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-lg shadow-sm mt-2">
                <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-slate-700">
                      Showing{' '}
                      <span className="font-medium">{paginator?.from ?? 0}</span>{' '}
                      to{' '}
                      <span className="font-medium">{paginator?.to ?? 0}</span>{' '}
                      of{' '}
                      <span className="font-medium">{paginator?.total ?? 0}</span>{' '}
                      results
                    </p>
                  </div>
                  <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                      <button
                        onClick={() => handlePageChange(currentPage - 1)}
                        disabled={currentPage <= 1}
                        className="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 focus:z-20 focus:outline-offset-0"
                      >
                        <span className="sr-only">Previous</span>
                        <span className="material-symbols-outlined text-[20px]">chevron_left</span>
                      </button>
                      {Array.from({ length: totalPages }, (_, i) => i + 1).map((p) => {
                        // Show first, last, current, and neighbors; collapse others
                        if (p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
                          return (
                            <button
                              key={p}
                              onClick={() => handlePageChange(p)}
                              className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 focus:outline-offset-0 ${
                                p === currentPage
                                  ? 'z-10 bg-blue-900 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-900'
                                  : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                              }`}
                            >
                              {p}
                            </button>
                          );
                        }
                        // Show ellipsis
                        if (
                          (p === currentPage - 2 && currentPage > 3) ||
                          (p === currentPage + 2 && currentPage < totalPages - 2)
                        ) {
                          return (
                            <span
                              key={p}
                              className="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300"
                            >
                              ...
                            </span>
                          );
                        }
                        return null;
                      })}
                      <button
                        onClick={() => handlePageChange(currentPage + 1)}
                        disabled={currentPage >= totalPages}
                        className="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 focus:z-20 focus:outline-offset-0"
                      >
                        <span className="sr-only">Next</span>
                        <span className="material-symbols-outlined text-[20px]">chevron_right</span>
                      </button>
                    </nav>
                  </div>
                </div>
              </nav>
            )}
            {tableLoading && <TableLoadingOverlay variant="card" />}
          </div>
        )}

        {/* Empty state */}
        {total === 0 && (
          <div className="bg-white rounded-md border border-slate-200 p-12 text-center">
            <span className="material-symbols-outlined text-5xl text-slate-300 mb-3">check_circle</span>
            <h3 className="text-lg font-bold text-slate-700 mb-1">All caught up!</h3>
            <p className="text-sm text-slate-400">
              {userRole === 'AGENCY'
                ? 'No overdue referrals sent to your agency.'
                : userRole === 'CASE_MANAGER'
                  ? 'No overdue referrals from your cases.'
                  : 'No overdue referrals across all agencies.'}
            </p>
          </div>
        )}
      </div>

      {/* Confirmation Dialog */}
      {confirmDialog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30" onClick={() => setConfirmDialog(null)}>
          <div className="bg-white rounded-lg shadow-xl p-6 max-w-sm mx-4" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center gap-3 mb-3">
              <span className="material-symbols-outlined text-2xl text-amber-600">notifications</span>
              <h3 className="text-base font-bold text-slate-900">Send Reminder</h3>
            </div>
            <p className="text-sm text-slate-600 mb-5">
              {confirmDialog.type === 'all'
                ? 'This will send email reminders to all agencies about every overdue referral. Continue?'
                : confirmDialog.type === 'batch'
                  ? `Send reminders for the ${selectedIds.length} selected overdue referral(s)?`
                  : 'Send an email reminder to the assigned agency about this referral?'}
            </p>
            <div className="flex items-center justify-end gap-3">
              <button
                onClick={() => setConfirmDialog(null)}
                className="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={confirmSend}
                disabled={sending === '__all__'}
                className="px-4 py-2 text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 rounded-md transition-colors disabled:opacity-50 flex items-center gap-2"
              >
                <span className="material-symbols-outlined text-[16px]">send</span>
                Send
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
