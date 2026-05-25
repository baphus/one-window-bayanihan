import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { FolderCheck, Users, ArrowRightLeft, TrendingUp, Clock } from 'lucide-react';

const statusStyles = {
  OPEN: 'bg-green-100 text-green-800',
  CLOSED: 'bg-slate-100 text-slate-800',
};

const vulnStyles = {
  'PWD': 'bg-purple-100 text-purple-800',
  'Senior Citizen': 'bg-orange-100 text-orange-800',
  'Solo Parent': 'bg-pink-100 text-pink-800',
  'Indigenous Person': 'bg-teal-100 text-teal-800',
  'None': 'bg-slate-100 text-slate-500',
};

function formatCaseAge(createdAt) {
  const parsed = new Date(createdAt);
  if (Number.isNaN(parsed.getTime())) return 'N/A';
  const ageInMs = Math.max(0, Date.now() - parsed.getTime());
  const oneDayInMs = 24 * 60 * 60 * 1000;
  const ageInDays = Math.floor(ageInMs / oneDayInMs);
  if (ageInDays > 0) return `${ageInDays} day${ageInDays === 1 ? '' : 's'}`;
  const ageInHours = Math.floor(ageInMs / (60 * 60 * 1000));
  if (ageInHours > 0) return `${ageInHours} hr${ageInHours === 1 ? '' : 's'}`;
  const ageInMinutes = Math.floor(ageInMs / (60 * 1000));
  return `${Math.max(1, ageInMinutes)} min`;
}

function referredToAgencies(referrals) {
  if (!referrals || referrals.length === 0) return '\u2014';
  const names = [...new Set(referrals.map((r) => r.agency?.name).filter(Boolean))];
  return names.length > 0 ? names.join(', ') : '\u2014';
}

const COLUMN_DEFS = [
  { key: 'tracker_number', label: 'Tracking ID', default: true },
  { key: 'client_type', label: 'Client Type', default: true },
  { key: 'client_name', label: 'Client Name', default: true },
  { key: 'vulnerability_indicator', label: 'Vulnerability', default: true },
  { key: 'age', label: 'Age', default: true },
  { key: 'status', label: 'Case Status', default: true },
  { key: 'referred_to', label: 'Referred To', default: true },
  { key: 'created_at', label: 'Date Created', default: true },
  { key: 'actions', label: 'Actions', default: true },
];

export default function CaseIndex({ cases, filters, stats }) {
  const { auth } = usePage().props;
  const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';

  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [viewMode, setViewMode] = useState('list');
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);

  const searchTimeout = useRef(null);

  const [visibleColumns, setVisibleColumns] = useState(
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  const [statusFilter, setStatusFilter] = useState(filters?.status ?? '');
  const [typeFilter, setTypeFilter] = useState(filters?.client_type ?? '');
  const [vulnFilter, setVulnFilter] = useState(filters?.vulnerability_indicator ?? '');

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
    window.location = url.toString();
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
    if (statusFilter) chips.push({ key: 'status', label: 'Status', value: statusFilter });
    if (typeFilter) chips.push({ key: 'client_type', label: 'Client Type', value: typeFilter === 'OFW' ? 'OFW' : 'NOK' });
    if (vulnFilter) chips.push({ key: 'vulnerability_indicator', label: 'Vulnerability', value: vulnFilter });
    return chips;
  }, [statusFilter, typeFilter, vulnFilter]);

  const handleRemoveFilter = (filter) => {
    if (filter.key === 'status') { setStatusFilter(''); navigateWith({ status: undefined }); }
    if (filter.key === 'client_type') { setTypeFilter(''); navigateWith({ client_type: undefined }); }
    if (filter.key === 'vulnerability_indicator') { setVulnFilter(''); navigateWith({ vulnerability_indicator: undefined }); }
  };

  const handleClearFilters = () => {
    setStatusFilter('');
    setTypeFilter('');
    setVulnFilter('');
    navigateWith({ status: undefined, client_type: undefined, vulnerability_indicator: undefined });
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
        window.location = url.toString();
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        window.location = url.toString();
      },
    };
  }

  const columns = useMemo(() =>
    COLUMN_DEFS
      .filter((col) => visibleColumns.includes(col.key))
      .map((col) => {
        const base = { key: col.key, title: col.label, sortable: true };
        switch (col.key) {
          case 'tracker_number':
            return {
              ...base,
              render: (row) => (
                <span className="font-mono text-xs font-bold text-slate-700">{row.tracker_number}</span>
              ),
            };
          case 'client_type':
            return {
              ...base,
              render: (row) => (row.client_type === 'OFW' ? 'OFW' : 'Next of Kin'),
            };
          case 'client_name':
            return {
              ...base,
              render: (row) =>
                row.client ? `${row.client.first_name} ${row.client.last_name}` : 'N/A',
              sortAccessor: (row) =>
                row.client ? `${row.client.last_name}, ${row.client.first_name}` : '',
            };
          case 'vulnerability_indicator':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const val = row.vulnerability_indicator;
                if (!val || val === 'None') return <span className="text-slate-400">&mdash;</span>;
                return (
                  <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold leading-none ${vulnStyles[val] || 'bg-slate-100 text-slate-700'}`}>
                    {val}
                  </span>
                );
              },
            };
          case 'age':
            return {
              ...base,
              sortable: true,
              sortAccessor: (row) => new Date(row.created_at).getTime(),
              render: (row) => (
                <span className="text-xs text-slate-600">{formatCaseAge(row.created_at)}</span>
              ),
            };
          case 'status':
            return {
              ...base,
              render: (row) => (
                <span
                  className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[row.status] || 'bg-slate-100 text-slate-800'}`}
                >
                  {row.status}
                </span>
              ),
            };
          case 'referred_to':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600 max-w-[200px] truncate block">{referredToAgencies(row.referrals)}</span>
              ),
            };
          case 'created_at':
            return {
              ...base,
              render: (row) => new Date(row.created_at).toLocaleDateString(),
            };
          case 'actions':
            return {
              ...base,
              sortable: false,
              title: 'Actions',
              render: (row) => (
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => router.visit(route('cases.show', row.id))}
                    className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                  >
                    View
                  </button>
                </div>
              ),
            };
          default:
            return { ...base, render: (row) => row[col.key] };
        }
      }),
  [visibleColumns]);

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
          className="w-full border border-[#cbd5e1] rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
        >
          <option value="">All Statuses</option>
          <option value="OPEN">Open</option>
          <option value="CLOSED">Closed</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Client Type</label>
        <select
          value={typeFilter}
          onChange={(e) => {
            const val = e.target.value;
            setTypeFilter(val);
            setFilterOpen(false);
            navigateWith({ client_type: val || undefined });
          }}
          className="w-full border border-[#cbd5e1] rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
        >
          <option value="">All Types</option>
          <option value="OFW">OFW</option>
          <option value="NOK">Next of Kin</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Vulnerability</label>
        <select
          value={vulnFilter}
          onChange={(e) => {
            const val = e.target.value;
            setVulnFilter(val);
            setFilterOpen(false);
            navigateWith({ vulnerability_indicator: val || undefined });
          }}
          className="w-full border border-[#cbd5e1] rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
        >
          <option value="">All</option>
          <option value="PWD">PWD</option>
          <option value="Senior Citizen">Senior Citizen</option>
          <option value="Solo Parent">Solo Parent</option>
          <option value="Indigenous Person">Indigenous Person</option>
          <option value="None">None</option>
        </select>
      </div>
    </div>
  ), [statusFilter, typeFilter, vulnFilter]);

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
            className="rounded border-[#cbd5e1] text-[#0b5384] focus:ring-[#0b5384] focus:ring-offset-0"
          />
          {col.label}
        </label>
      ))}
    </div>
  ), [visibleColumns]);

  return (
    <AppLayout title="Case Management">
      <Head title="Case Management" />

      <header className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Cases
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Manage all client cases and track their progress.</p>
        </div>
      </header>

      <section className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Cases</p>
            <span className="p-1.5 bg-blue-50 rounded-lg"><FolderCheck className="w-4 h-4 text-blue-900" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.total_cases ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <TrendingUp className="w-3 h-3 text-emerald-500" />
            <span className="text-[11px] font-bold text-emerald-600">
              {stats?.open_cases ?? 0} open &middot; {stats?.closed_cases ?? 0} closed
            </span>
          </div>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Active Cases</p>
            <span className="p-1.5 bg-emerald-50 rounded-lg"><Clock className="w-4 h-4 text-emerald-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.open_cases ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[11px] font-medium text-slate-500">
              {stats?.total_cases > 0
                ? `${((stats.open_cases / stats.total_cases) * 100).toFixed(0)}% of total`
                : 'No cases'}
            </span>
          </div>
          <p className="text-[10px] text-slate-400 mt-0.5">Currently in progress</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Client Types</p>
            <span className="p-1.5 bg-violet-50 rounded-lg"><Users className="w-4 h-4 text-violet-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.ofw_cases ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[11px] font-bold text-violet-600">OFW</span>
            <span className="text-[11px] text-slate-400">&middot;</span>
            <span className="text-[11px] text-slate-500">{stats?.nok_cases ?? 0} Next of Kin</span>
          </div>
          <p className="text-[10px] text-slate-400 mt-0.5">Client type distribution</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Referrals</p>
            <span className="p-1.5 bg-amber-50 rounded-lg"><ArrowRightLeft className="w-4 h-4 text-amber-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats?.total_referrals ?? 0}</p>
          <p className="text-[10px] text-slate-400 mt-0.5">Across all cases</p>
        </div>
      </section>

      <UnifiedTable
        columns={columns}
        data={cases.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(cases)}
        searchValue={searchValue}
        searchPlaceholder="Search by case number, tracker number..."
        onSearchChange={handleSearchChange}
        onAdvancedFilters={() => setFilterOpen((v) => { setColumnsOpen(false); return !v; })}
        isAdvancedFiltersOpen={filterOpen}
        advancedFiltersContent={advancedFilterContent}
        onColumnsControl={() => setColumnsOpen((v) => { setFilterOpen(false); return !v; })}
        isColumnsControlOpen={columnsOpen}
        columnsControlContent={columnControlContent}
        onViewModeChange={setViewMode}
        viewMode={viewMode}
        onNewRecord={canCreate ? () => router.visit(route('cases.create')) : undefined}
        newRecordLabel="Create Case"
        activeFilters={activeFilters}
        onRemoveFilter={handleRemoveFilter}
        onClearFilters={handleClearFilters}
      />
    </AppLayout>
  );
}
