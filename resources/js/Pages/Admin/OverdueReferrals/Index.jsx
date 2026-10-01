import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/ui/StatusBadge';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import { formatDisplayDate } from '@/lib/utils';

const ROLE_SUBTITLES = {
  ADMIN: 'Overdue referrals across all agencies — sorted by most stale first',
  CASE_MANAGER: 'Overdue referrals from your cases — sorted by most stale first',
  AGENCY: 'Referrals sent to your agency that need attention — sorted by most stale first',
};

const STATUS_FILTER_LABELS = {
  pending: 'Pending',
  processing: 'Processing',
  for_compliance: 'For Compliance',
};

// UnifiedTable column key -> backend sort_by param
const SORT_COLUMN_TO_BACKEND = {
  stale: 'most_stale',
  status: 'status',
  client: 'client_name',
};

const BACKEND_TO_SORT_COLUMN = {
  most_stale: 'stale',
  status: 'status',
  client_name: 'client',
};

function UrgencyBadge({ severity, days }) {
  const config = {
    mild: { label: 'Mild', classes: 'bg-amber-50 border-amber-200 text-amber-700' },
    moderate: { label: 'Moderate', classes: 'bg-orange-50 border-orange-200 text-orange-700' },
    severe: { label: 'Severe', classes: 'bg-rose-50 border-rose-200 text-rose-700' },
  };
  const { label, classes } = config[severity] ?? config.mild;

  return (
    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold whitespace-nowrap ${classes}`}>
      {label} &middot; {days}d
    </span>
  );
}

function currentParamsFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return {
    sort_by: params.get('sort_by') || 'most_stale',
    status_filter: params.get('status_filter') || 'all',
  };
}

export default function OverdueReferralsIndex({ stats = {}, referrals, userRole }) {
  const canRemind = userRole === 'ADMIN' || userRole === 'CASE_MANAGER';
  const total = stats.total ?? 0;

  const [selectedIds, setSelectedIds] = useState([]);
  const [sending, setSending] = useState(null); // referral ID being reminded, or '__all__'
  const [confirmDialog, setConfirmDialog] = useState(null); // { type, referralId? }
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  const { sort_by: currentSort, status_filter: currentFilter } = currentParamsFromUrl();

  const updateTable = (overrides) => {
    const params = new URLSearchParams(window.location.search);
    const merged = {
      sort_by: params.get('sort_by') ?? undefined,
      status_filter: params.get('status_filter') ?? undefined,
      per_page: params.get('per_page') ?? undefined,
      ...overrides,
    };
    const clean = Object.fromEntries(
      Object.entries(merged).filter(([_, v]) => v !== null && v !== undefined && v !== ''),
    );
    router.get(route('overdue-referrals.index'), clean, withLoading({
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['referrals'],
      showProgress: false,
    }));
  };

  const handleSortChange = (columnKey) => {
    updateTable({ sort_by: SORT_COLUMN_TO_BACKEND[columnKey] ?? 'most_stale', page: undefined });
  };

  const handleStatusFilter = (value) => {
    updateTable({ status_filter: value === 'all' ? undefined : value, page: undefined });
  };

  const handleRemoveFilter = () => {
    updateTable({ status_filter: undefined, page: undefined });
  };

  const handleClearFilters = () => {
    updateTable({ status_filter: undefined, page: undefined });
  };

  const confirmSend = () => {
    if (!confirmDialog) return;
    const { status_filter } = currentParamsFromUrl();
    const ids =
      confirmDialog.type === 'batch'
        ? selectedIds
        : confirmDialog.referralId
          ? [confirmDialog.referralId]
          : [];

    setSending(confirmDialog.referralId ?? '__all__');
    setConfirmDialog(null);

    router.post(
      route('overdue-referrals.send-reminders'),
      {
        referral_ids: confirmDialog.type === 'all' ? [] : ids,
        status_filter,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
          setSending(null);
          setSelectedIds([]);
        },
      },
    );
  };

  const activeFilters = useMemo(() => {
    if (!currentFilter || currentFilter === 'all') return [];
    return [{ key: 'status_filter', label: 'Status', value: STATUS_FILTER_LABELS[currentFilter] ?? currentFilter }];
  }, [currentFilter]);

  const quickFilters = useMemo(() => {
    const pills = [
      { label: 'All', value: 'all', count: stats.total ?? 0 },
      { label: 'Pending', value: 'pending', count: stats.pending_count ?? 0 },
      { label: 'Processing', value: 'processing', count: stats.processing_count ?? 0 },
      { label: 'For Compliance', value: 'for_compliance', count: stats.for_compliance_count ?? 0 },
    ];

    return (
      <div className="flex items-center gap-1.5" role="group" aria-label="Quick status filters">
        <span className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mr-1">Show:</span>
        {pills.map((pill) => {
          const isActive = currentFilter === pill.value;
          return (
            <button
              key={pill.value}
              onClick={() => handleStatusFilter(pill.value)}
              className={`px-3 py-1.5 text-[12px] font-bold rounded-md transition-colors border ${
                isActive
                  ? 'bg-blue-900 text-white border-blue-900 shadow-sm'
                  : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50 hover:text-slate-800'
              }`}
            >
              {pill.label}
              {pill.count > 0 && ` (${pill.count})`}
            </button>
          );
        })}
      </div>
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentFilter, stats]);

  const columns = useMemo(() => [
    {
      key: 'case',
      title: 'Case',
      sortable: false,
      render: (row) => (
        <span className="font-mono text-xs font-bold text-slate-700 whitespace-nowrap">{row.case_number ?? 'N/A'}</span>
      ),
    },
    {
      key: 'client',
      title: 'Client',
      sortable: true,
      render: (row) => (
        <div className="max-w-[220px]">
          <div className="text-xs font-semibold text-slate-800 truncate" title={row.client_name}>{row.client_name}</div>
          {row.required_services && (
            <div className="text-[11px] text-slate-500 truncate" title={row.required_services}>{row.required_services}</div>
          )}
        </div>
      ),
    },
    {
      key: 'agency',
      title: 'Agency',
      sortable: false,
      render: (row) => (
        <span className="text-xs text-slate-600 max-w-[180px] truncate block" title={row.agency_name}>{row.agency_name}</span>
      ),
    },
    {
      key: 'status',
      title: 'Status',
      sortable: true,
      render: (row) => (
        <StatusBadge status={row.status} variant="sharp" />
      ),
    },
    {
      key: 'urgency',
      title: 'Urgency',
      sortable: false,
      render: (row) => (
        <UrgencyBadge severity={row.severity} days={row.days_since_last_activity} />
      ),
    },
    {
      key: 'stale',
      title: 'Stale',
      sortable: true,
      render: (row) => (
        <span className="text-xs font-bold text-slate-700 whitespace-nowrap">{row.days_since_last_activity}d</span>
      ),
    },
    {
      key: 'last_activity',
      title: 'Last Activity',
      sortable: false,
      render: (row) => (
        <span
          className="text-xs text-slate-600 max-w-[240px] truncate block"
          title={row.last_activity_date ? formatDisplayDate(row.last_activity_date) : undefined}
        >
          {row.last_activity_description}
        </span>
      ),
    },
    {
      key: 'case_manager',
      title: 'Case Manager',
      sortable: false,
      render: (row) => (
        <span className="text-xs text-slate-600 whitespace-nowrap">{row.case_manager_name}</span>
      ),
    },
    {
      key: 'actions',
      title: 'Actions',
      sortable: false,
      render: (row) => {
        const isSending = sending === row.id;
        return (
          <div className="flex items-center gap-1.5">
            <Link
              href={route('referrals.show', row.id)}
              className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-md transition-colors whitespace-nowrap"
            >
              View Details
              <span className="material-symbols-outlined text-[14px]">chevron_right</span>
            </Link>
            {canRemind && (
              <button
                onClick={() => setConfirmDialog({ type: 'single', referralId: row.id })}
                disabled={sending !== null}
                className="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 text-[11px] font-bold rounded-md transition-colors disabled:opacity-50 whitespace-nowrap"
              >
                <span className={`material-symbols-outlined text-[14px] ${isSending ? 'animate-spin' : ''}`}>
                  {isSending ? 'progress_activity' : 'notifications'}
                </span>
                {isSending ? 'Sending…' : 'Remind'}
              </button>
            )}
          </div>
        );
      },
    },
  ], [canRemind, sending]);

  return (
    <AppLayout title="Overdue Referrals">
      <Head title="Overdue Referrals" />

      <header data-tour="overdue-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Overdue Referrals
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">
            {ROLE_SUBTITLES[userRole] ?? ROLE_SUBTITLES.ADMIN}
          </p>
        </div>
      </header>

      {canRemind && selectedIds.length > 0 && (
        <div className="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-md px-4 py-2.5 mb-4">
          <span className="text-sm text-blue-800 font-medium">
            {selectedIds.length} selected
          </span>
          <button
            onClick={() => setConfirmDialog({ type: 'batch' })}
            disabled={sending !== null}
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

      <div data-tour="overdue-table">
        <UnifiedTable
          columns={columns}
          data={referrals?.data ?? []}
          keyExtractor={(row) => row.id}
          selectable={canRemind}
          selectedKeys={selectedIds}
          onSelectionChange={setSelectedIds}
          totalRecords={referrals?.total ?? 0}
          startIndex={referrals?.from ?? 0}
          endIndex={referrals?.to ?? 0}
          currentPage={referrals?.current_page ?? 1}
          totalPages={referrals?.last_page ?? 1}
          rowsPerPage={referrals?.per_page ?? 15}
          onPageChange={(page) => updateTable({ page })}
          onRowsPerPageChange={(perPage) => updateTable({ per_page: perPage, page: undefined })}
          isLoading={tableLoading}
          emptyStateMessage="No overdue referrals — all caught up!"
          sortKey={BACKEND_TO_SORT_COLUMN[currentSort] ?? 'stale'}
          sortDirection={currentSort === 'status' ? 'asc' : 'desc'}
          onSortChange={handleSortChange}
          defaultSortKey="stale"
          defaultSortDirection="desc"
          hideSearch
          activeFilters={activeFilters}
          onRemoveFilter={handleRemoveFilter}
          onClearFilters={handleClearFilters}
          quickFilters={quickFilters}
          hideControlBar={!(canRemind && total > 0)}
          extraActions={
            canRemind && total > 0 ? (
              <button
                onClick={() => setConfirmDialog({ type: 'all' })}
                disabled={sending === '__all__'}
                className="h-[40px] px-4 bg-blue-900 text-white hover:bg-blue-800 disabled:opacity-50 text-[14px] font-bold rounded-[3px] flex items-center gap-2 transition-colors whitespace-nowrap shrink-0"
              >
                <span className="material-symbols-outlined text-[18px]">notifications_active</span>
                Send All Reminders
              </button>
            ) : undefined
          }
        />
      </div>

      {/* Confirmation Dialog */}
      {confirmDialog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30" onClick={() => setConfirmDialog(null)}>
          <div className="bg-white rounded-lg shadow-xl p-6 max-w-sm mx-4 owb-modal-animate" onClick={(e) => e.stopPropagation()}>
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
                disabled={sending !== null}
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
