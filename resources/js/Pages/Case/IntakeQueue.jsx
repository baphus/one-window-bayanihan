import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import usePersistedColumns from '@/Hooks/usePersistedColumns';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import StatusBadge from '@/Components/ui/StatusBadge';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import { useToast } from '@/Hooks/useToast';
import { Inbox, ShieldAlert, CalendarClock, Tag } from 'lucide-react';

/* ── Helpers ────────────────────────────────────────────────── */

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

function getClientPhone(caseItem) {
  if (caseItem.client?.contact_number) return caseItem.client.contact_number;
  if (caseItem.draft_client_data?.contact_number) return caseItem.draft_client_data.contact_number;
  return '—';
}

function getClientAge(caseItem) {
  const dob = caseItem.client?.date_of_birth || caseItem.draft_client_data?.date_of_birth;
  if (!dob) return '—';
  const birth = new Date(dob);
  if (Number.isNaN(birth.getTime())) return '—';
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--;
  }
  return `${age} yrs`;
}

/* ── Vulnerability styles ───────────────────────────────────── */

const vulnStyles = {
  'PWD': 'bg-purple-100 text-purple-800',
  'Senior Citizen': 'bg-orange-100 text-orange-800',
  'Solo Parent': 'bg-pink-100 text-pink-800',
  'Indigenous Person': 'bg-teal-100 text-teal-800',
  'None': 'bg-slate-100 text-slate-500',
};

/* ── Column definitions ─────────────────────────────────────── */

const COLUMN_DEFS = [
  { key: 'case_number', label: 'Case Number', default: true },
  { key: 'client_name', label: 'OFW Name', default: true },
  { key: 'vulnerability', label: 'Vulnerability', default: true },
  { key: 'email', label: 'Email', default: true },
  { key: 'phone', label: 'Phone', default: true },
  { key: 'age', label: 'Age', default: true },
  { key: 'submitted', label: 'Submitted', default: true },
  { key: 'actions', label: 'Actions', default: true },
];

/* ── Component ──────────────────────────────────────────────── */

export default function IntakeQueue({ cases, filters: initialFilters = {}, stats, sort: initialSort, direction: initialDirection }) {
  const filters = initialFilters && !Array.isArray(initialFilters) ? initialFilters : {};

  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [vulnFilter, setVulnFilter] = useState('');
  const [rejectTarget, setRejectTarget] = useState(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);
  const [contextMenu, setContextMenu] = useState(null);
  const [columnsOpen, setColumnsOpen] = useState(false);
  const [tableLoading, setTableLoading] = useState(false);
  const searchTimeout = useRef(null);
  const toast = useToast();

  const [visibleColumns, setVisibleColumns] = usePersistedColumns(
    'intake-queue',
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  /* ── Lifecycle ──────────────────────────────────────────────── */

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  useEffect(() => {
    const onStart = () => setTableLoading(true);
    const onFinish = () => setTableLoading(false);
    const removeStart = router.on('start', onStart);
    const removeFinish = router.on('finish', onFinish);
    return () => {
      if (typeof removeStart === 'function') removeStart();
      if (typeof removeFinish === 'function') removeFinish();
    };
  }, []);

  /* ── Navigation helpers ─────────────────────────────────────── */

  function updateTable(params) {
    const clean = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    router.get(route('cases.intake-queue'), clean, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['cases', 'filters', 'stats', 'sort', 'direction'],
      showProgress: false,
    });
  }

  const handleSearchChange = useCallback((value) => {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      updateTable({ ...filters, search: value || undefined, page: undefined });
    }, 400);
  }, [filters]);

  const handleSearchClear = useCallback(() => {
    setSearchValue('');
    clearTimeout(searchTimeout.current);
    updateTable({ ...filters, search: undefined, page: undefined });
  }, [filters]);

  const handleSortChange = useCallback((sortKey, sortDirection) => {
    updateTable({ ...filters, sort: sortKey, direction: sortDirection, page: undefined });
  }, [filters]);

  /* ── Context menu ───────────────────────────────────────────── */

  const handleRowContextMenu = useCallback((e, row) => {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  }, []);

  /* ── Vulnerability quick-filter (client-side) ────────────── */

  function isVulnerable(caseItem) {
    const val = caseItem.vulnerability_indicator;
    return val && val !== 'None' && val.trim() !== '';
  }

  const handleVulnFilter = useCallback((value) => {
    setVulnFilter((prev) => (prev === value ? '' : value));
  }, []);

  const filteredData = useMemo(() => {
    if (!vulnFilter) return cases.data;
    return cases.data.filter((c) => {
      return vulnFilter === 'vulnerable' ? isVulnerable(c) : !isVulnerable(c);
    });
  }, [cases.data, vulnFilter]);

  const quickFilterCounts = useMemo(() => {
    const data = cases.data || [];
    const vulnerable = data.filter((c) => isVulnerable(c)).length;
    return { all: cases.total, vulnerable, noVuln: data.length - vulnerable };
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

  /* ── Column renderers ───────────────────────────────────────── */

  const columns = useMemo(() =>
    COLUMN_DEFS
      .filter((col) => visibleColumns.includes(col.key))
      .map((col) => {
        const base = { key: col.key, title: col.label, sortable: true };
        switch (col.key) {
          case 'case_number':
            return {
              ...base,
              render: (row) => (
                <span className="font-mono text-xs font-bold text-slate-700">{row.case_number}</span>
              ),
            };
          case 'client_name':
            return {
              ...base,
              sortAccessor: (row) => {
                const first = row.client?.first_name || row.draft_client_data?.first_name || '';
                const last = row.client?.last_name || row.draft_client_data?.last_name || '';
                return `${last}, ${first}`;
              },
              render: (row) => (
                <span className="text-xs font-medium text-slate-800">{getClientName(row)}</span>
              ),
            };
          case 'vulnerability':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const val = row.vulnerability_indicator;
                if (!val || val === 'None') return <span className="text-slate-400">&mdash;</span>;
                const parts = val.split(',').map(s => s.trim()).filter(Boolean);
                return (
                  <div className="flex flex-wrap gap-1">
                    {parts.map((v) => (
                      <span key={v} className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold leading-none ${vulnStyles[v] || 'bg-slate-100 text-slate-700'}`}>
                        {v}
                      </span>
                    ))}
                  </div>
                );
              },
            };
          case 'email':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{getClientEmail(row)}</span>
              ),
            };
          case 'phone':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{getClientPhone(row)}</span>
              ),
            };
          case 'age':
            return {
              ...base,
              sortAccessor: (row) => {
                const dob = row.client?.date_of_birth || row.draft_client_data?.date_of_birth;
                return dob ? new Date(dob).getTime() : 0;
              },
              render: (row) => (
                <span className="text-xs text-slate-600">{getClientAge(row)}</span>
              ),
            };
          case 'submitted':
            return {
              ...base,
              render: (row) => (
                <div>
                  <div className="text-xs text-slate-700">{formatDisplayDate(row.created_at)}</div>
                  <div className="text-[10px] text-slate-500">{formatDisplayTime(row.created_at)}</div>
                </div>
              ),
            };
          case 'actions':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <div className="flex items-center gap-1.5">
                  <button
                    onClick={() => router.visit(route('cases.review-intake', row.id))}
                    className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors border border-blue-900"
                  >
                    Review
                  </button>
                  <button
                    onClick={() => setRejectTarget(row.id)}
                    className="min-h-[28px] px-2.5 bg-white text-red-600 border border-red-200 hover:bg-red-50 text-[11px] font-bold rounded-[3px] transition-colors"
                  >
                    Reject
                  </button>
                </div>
              ),
            };
          default:
            return { ...base, render: (row) => row[col.key] };
        }
      }),
  [visibleColumns]);

  /* ── Quick-filter pills ─────────────────────────────────────── */

  const quickFilterPills = useMemo(() => {
    const currentFilter = vulnFilter;
    return (
      <div className="flex items-center gap-1.5" role="group" aria-label="Quick vulnerability filters">
        <span className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mr-1">Show:</span>
        {[
          { label: 'All', value: '', count: quickFilterCounts.all },
          { label: 'Vulnerable', value: 'vulnerable', count: quickFilterCounts.vulnerable },
          { label: 'No Vulnerability', value: 'none', count: quickFilterCounts.noVuln },
        ].map((f) => {
          const isActive = currentFilter === f.value || (f.value === '' && currentFilter === '');
          return (
            <button
              key={f.label}
              onClick={() => handleVulnFilter(f.value)}
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
    );
  }, [vulnFilter, quickFilterCounts, handleVulnFilter]);

  /* ── Column visibility control ──────────────────────────────── */

  const columnControlContent = useMemo(() => (
    <div className="space-y-2">
      {COLUMN_DEFS.map((col) => (
        <label
          key={col.key}
          className="flex items-center gap-2.5 text-[13px] text-slate-700 cursor-pointer select-none hover:text-slate-900 transition-colors"
        >
          <input
            type="checkbox"
            checked={visibleColumns.includes(col.key)}
            onChange={() => {
              setVisibleColumns((prev) =>
                prev.includes(col.key)
                  ? prev.filter((k) => k !== col.key)
                  : [...prev, col.key],
              );
            }}
            className="rounded border-slate-300 text-blue-900 focus:ring-blue-900 focus:ring-offset-0"
          />
          {col.label}
        </label>
      ))}
    </div>
  ), [visibleColumns, setVisibleColumns]);

  /* ── Stats from server prop ─────────────────────────────────── */

  const pageStats = useMemo(() => {
    if (stats) return stats;
    // Fallback: compute from page data if server stats not available
    const data = cases.data || [];
    const vulnerableCount = data.filter((c) => isVulnerable(c)).length;
    const now = new Date();
    const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    const thisWeekCount = data.filter((c) => {
      if (!c.created_at) return false;
      return new Date(c.created_at) >= weekAgo;
    }).length;
    return {
      total: cases.total,
      vulnerable: vulnerableCount,
      thisWeek: thisWeekCount,
      categoryCount: 0,
    };
  }, [stats, cases]);

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
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Vulnerable</p>
              <span className="p-1.5 bg-emerald-50 rounded-lg"><ShieldAlert className="w-4 h-4 text-emerald-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{pageStats.vulnerable}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">Need priority</p>
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

        {/* ── UnifiedTable ─────────────────────────────────── */}
        <UnifiedTable
          columns={columns}
          data={cases.data}
          keyExtractor={(row) => row.id}
          // Pagination
          totalRecords={cases.total}
          startIndex={cases.from}
          endIndex={cases.to}
          currentPage={cases.current_page}
          totalPages={cases.last_page}
          rowsPerPage={cases.per_page}
          onPageChange={(page) => updateTable({ ...filters, page })}
          onRowsPerPageChange={(n) => updateTable({ ...filters, per_page: n, page: undefined })}
          // Sort
          sortKey={initialSort ?? 'created_at'}
          sortDirection={initialDirection ?? 'asc'}
          onSortChange={handleSortChange}
          defaultSortKey="created_at"
          defaultSortDirection="asc"
          // Search
          searchValue={searchValue}
          searchPlaceholder="Search by OFW name..."
          onSearchChange={handleSearchChange}
          onSearchClear={handleSearchClear}
          // Quick filters
          quickFilters={quickFilterPills}
          // Context menu
          onRowContextMenu={handleRowContextMenu}
          // Columns control
          onColumnsControl={() => setColumnsOpen((v) => !v)}
          isColumnsControlOpen={columnsOpen}
          columnsControlContent={columnControlContent}
          // Loading & empty state
          isLoading={tableLoading}
          emptyStateMessage="No Pending Intake Submissions"
          hideSearch={false}
        />
      </div>

      {/* ── Context Menu ───────────────────────────────────── */}
      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="visibility" label="Review" onClick={() => {
            router.visit(route('cases.review-intake', contextMenu.row.id));
            setContextMenu(null);
          }} />
          <RowContextMenuItem icon="block" label="Reject" variant="danger" onClick={() => {
            setRejectTarget(contextMenu.row.id);
            setContextMenu(null);
          }} />
        </RowContextMenu>
      )}

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
