import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import { Building2, Users, CheckCircle, XCircle, Shield, MapPin, Phone } from 'lucide-react';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';

import AgencyFormModal from '@/Components/Admin/AgencyFormModal';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';

const COLUMN_DEFS = [
  { key: 'name', label: 'Name', default: true },
  { key: 'short', label: 'Short', default: true },
  { key: 'is_default', label: 'Type', default: true },
  { key: 'description', label: 'Description', default: false },
  { key: 'contact_info', label: 'Contact', default: false },
  { key: 'referrals_count', label: 'Referrals', default: true },
  { key: 'is_active', label: 'Status', default: true },
  { key: 'created_at', label: 'Date Created', default: false },
  { key: 'actions', label: 'Actions', default: true },
];

export default function AdminAgencyIndex({ agencies, filters, stats }) {
  const { auth } = usePage().props;
  const isAdmin = auth.user.role === 'ADMIN';

  const [showForm, setShowForm] = useState(false);
  const [editingAgency, setEditingAgency] = useState(null);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm);
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [contextMenu, setContextMenu] = useState(null);
  const [viewMode, setViewMode] = useState('list');

  const handleRowContextMenu = (e, row) => {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  };
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);

  const searchTimeout = useRef(null);

  const [visibleColumns, setVisibleColumns] = useState(
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  const [statusFilter, setStatusFilter] = useState(filters?.status ?? '');
  const [isDefaultFilter, setIsDefaultFilter] = useState(filters?.is_default ?? '');

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  const navigateWith = (params) => {
    const url = new URL(window.location);
    Object.entries(params).forEach(([k, v]) => {
      if (v) url.searchParams.set(k, v);
      else url.searchParams.delete(k);
    });
    url.searchParams.delete('page');
    router.get(url.toString(), {}, withLoading({ preserveState: true, replace: true }));
  };

  const handleSearchChange = (value) => {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      navigateWith({ search: value || undefined });
    }, 400);
  };

  const activeFilters = useMemo(() => {
    const chips = [];
    if (statusFilter) chips.push({ key: 'status', label: 'Status', value: statusFilter === '1' ? 'Active' : 'Inactive' });
    if (isDefaultFilter) chips.push({ key: 'is_default', label: 'Type', value: isDefaultFilter === '1' ? 'Default Only' : 'Standard' });
    return chips;
  }, [statusFilter, isDefaultFilter]);

  const handleRemoveFilter = (filter) => {
    if (filter.key === 'status') { setStatusFilter(''); navigateWith({ status: undefined }); }
    if (filter.key === 'is_default') { setIsDefaultFilter(''); navigateWith({ is_default: undefined }); }
  };

  const handleClearFilters = () => {
    setStatusFilter('');
    setIsDefaultFilter('');
    navigateWith({ status: undefined, is_default: undefined });
  };

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator.total,
      startIndex: paginator.from,
      endIndex: paginator.to,
      currentPage: paginator.current_page,
      totalPages: paginator.last_page,
      rowsPerPage: paginator.per_page,
      onPageChange: (page) => {
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['agencies'] }));
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['agencies'] }));
      },
    };
  }

  const columns = useMemo(() =>
    COLUMN_DEFS
      .filter((col) => visibleColumns.includes(col.key))
      .map((col) => {
        const base = { key: col.key, title: col.label, sortable: true };
        switch (col.key) {
          case 'name':
            return {
              ...base,
              render: (row) => (
                <div className="flex items-center gap-3">
                  {row.logo_url ? (
                    <img
                      src={row.logo_url}
                      alt={row.name}
                      className="w-5 h-5 rounded object-contain border border-slate-200 shrink-0"
                      onError={(e) => { e.target.style.display = 'none'; }}
                    />
                  ) : (
                    <span className="w-5 h-5 rounded bg-slate-100 flex items-center justify-center shrink-0">
                      <Building2 className="w-3 h-3 text-slate-400" />
                    </span>
                  )}
                  <div className="min-w-0">
                    <div className="text-sm font-semibold text-slate-800 truncate max-w-[180px]">{row.name}</div>
                  </div>
                </div>
              ),
              sortAccessor: (row) => row.name,
            };
          case 'short':
            return {
              ...base,
              render: (row) => (
                <span className="text-xs text-slate-600">{row.short}</span>
              ),
            };
          case 'is_default':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${row.is_default ? 'bg-amber-50 text-amber-700 border-amber-300' : 'bg-slate-100 text-slate-500 border-slate-200'}`}>
                  {row.is_default ? 'Default' : 'Standard'}
                </span>
              ),
            };
          case 'description':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600 truncate max-w-[200px] block">{row.description || <span className="text-slate-400">&mdash;</span>}</span>
              ),
            };
          case 'contact_info':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{row.contact_info || <span className="text-slate-400">&mdash;</span>}</span>
              ),
            };
          case 'referrals_count':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-sm font-semibold text-slate-700">{row.referrals_count ?? 0}</span>
              ),
            };
          case 'is_active':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <StatusBadge status={row.is_active ? 'ACTIVE' : 'INACTIVE'} />
              ),
            };
          case 'created_at':
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
              title: 'Actions',
              render: (row) => (
                <div className="flex items-center gap-1.5">
                  <a
                    href={route('admin.agencies.show', row.id)}
                    className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-md transition-colors border border-blue-900 inline-flex items-center"
                  >
                    View
                  </a>
                  <button
                    onClick={() => { setEditingAgency(row); setShowForm(true); }}
                    className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300"
                  >
                    Edit
                  </button>
                  {isAdmin && row.is_active && !row.is_default && (
                    <button
                      onClick={() => {
                        if (confirm('Deactivate this agency? It will no longer be available for referrals.')) {
                          router.delete(route('admin.agencies.destroy', row.id), { preserveScroll: true });
                        }
                      }}
                      className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200"
                    >
                      Deactivate
                    </button>
                  )}
                  {isAdmin && (!row.is_active || row.is_deleted) && (
                    <button
                      onClick={() => {
                        if (confirm(`Reactivate agency "${row.name}"?`)) {
                          router.patch(route('admin.agencies.reactivate', row.id), {}, { preserveScroll: true });
                        }
                      }}
                      className="min-h-[28px] px-2.5 bg-green-50 text-green-600 hover:bg-green-100 text-[11px] font-bold rounded-md transition-colors border border-green-200"
                    >
                      Reactivate
                    </button>
                  )}
                </div>
              ),
            };
          default:
            return { ...base, render: (row) => row[col.key] };
        }
      }),
  [visibleColumns, isAdmin]);

  const advancedFilterContent = useMemo(() => (
    <div className="space-y-4">
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Status</label>
        <select
          value={statusFilter}
          onChange={(e) => {
            const val = e.target.value;
            setStatusFilter(val);
            setFilterOpen(false);
            navigateWith({ status: val || undefined });
          }}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Default</label>
        <select
          value={isDefaultFilter}
          onChange={(e) => {
            const val = e.target.value;
            setIsDefaultFilter(val);
            setFilterOpen(false);
            navigateWith({ is_default: val || undefined });
          }}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All</option>
          <option value="1">Default Only</option>
          <option value="0">Standard</option>
        </select>
      </div>
    </div>
  ), [statusFilter, isDefaultFilter]);

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
            className="rounded border-slate-200 text-blue-900 focus:ring-blue-900 focus:ring-offset-0"
          />
          {col.label}
        </label>
      ))}
    </div>
  ), [visibleColumns]);

  return (
    <AppLayout title="Manage Agencies">
      <Head title="Manage Agencies" />

      {showForm && (
        <AgencyFormModal
          agency={editingAgency}
          onClose={() => { setShowForm(false); setEditingAgency(null); }}
          onBypass={bypassNext}
        />
      )}

      <header data-tour="agencies-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Agencies
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Manage all partner agencies in the system.</p>
        </div>
      </header>

      <section data-tour="agencies-stats" className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Agencies</p>
            <span className="p-1.5 bg-blue-50 rounded-lg"><Building2 className="w-4 h-4 text-blue-900" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.total ?? 0}</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Active</p>
            <span className="p-1.5 bg-emerald-50 rounded-lg"><CheckCircle className="w-4 h-4 text-emerald-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.active ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[11px] font-medium text-slate-500">
              {stats?.total > 0 ? `${((stats.active / stats.total) * 100).toFixed(0)}%` : '—'}
            </span>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Inactive</p>
            <span className="p-1.5 bg-red-50 rounded-lg"><XCircle className="w-4 h-4 text-red-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.inactive ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[11px] font-medium text-slate-500">
              {stats?.total > 0 ? `${((stats.inactive / stats.total) * 100).toFixed(0)}%` : '—'}
            </span>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Default Agencies</p>
            <span className="p-1.5 bg-purple-50 rounded-lg"><Shield className="w-4 h-4 text-purple-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.default ?? 0}</p>
        </div>
      </section>

      <div data-tour="agencies-table">
      <UnifiedTable
        columns={columns}
        data={agencies.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(agencies)}
        searchValue={searchValue}
        searchPlaceholder="Search by name, short code, or description..."
        onSearchChange={handleSearchChange}
        onAdvancedFilters={() => setFilterOpen((v) => { setColumnsOpen(false); return !v; })}
        isAdvancedFiltersOpen={filterOpen}
        advancedFiltersContent={advancedFilterContent}
        onColumnsControl={() => setColumnsOpen((v) => { setFilterOpen(false); return !v; })}
        isColumnsControlOpen={columnsOpen}
        columnsControlContent={columnControlContent}
        onViewModeChange={setViewMode}
        viewMode={viewMode}
        onNewRecord={() => { setEditingAgency(null); setShowForm(true); }}
        newRecordLabel="New Agency"
        activeFilters={activeFilters}
        onRemoveFilter={handleRemoveFilter}
        onClearFilters={handleClearFilters}
        onRowContextMenu={handleRowContextMenu}
        isLoading={tableLoading}
      />
      </div>

      {UnsavedModal}
      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="visibility" label="View" onClick={() => {
            router.visit(route('admin.agencies.show', contextMenu.row.id));
            setContextMenu(null);
          }} />
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            setEditingAgency(contextMenu.row);
            setContextMenu(null);
          }} />
          {isAdmin && (!contextMenu.row.is_active || contextMenu.row.is_deleted) && (
            <RowContextMenuItem icon="restart_alt" label="Reactivate" variant="success" onClick={() => {
              if (confirm(`Reactivate agency "${contextMenu.row.name}"?`)) {
                router.patch(route('admin.agencies.reactivate', contextMenu.row.id), {}, { preserveScroll: true });
              }
              setContextMenu(null);
            }} />
          )}
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
