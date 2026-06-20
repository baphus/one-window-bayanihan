import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import { FolderCheck, Users, ArrowRightLeft, TrendingUp, Clock, FileDown } from 'lucide-react';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';

const vulnStyles = {
  'PWD': 'bg-purple-100 text-purple-800',
  'Senior Citizen': 'bg-orange-100 text-orange-800',
  'Solo Parent': 'bg-pink-100 text-pink-800',
  'Indigenous Person': 'bg-teal-100 text-teal-800',
  'None': 'bg-slate-100 text-slate-500',
};

function getClientAge(dob) {
  if (!dob) return '\u2014';
  const birth = new Date(dob);
  if (Number.isNaN(birth.getTime())) return '\u2014';
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--;
  }
  return `${age} yr${age !== 1 ? 's' : ''}`;
}

function getLatestUpdate(row) {
  const timestamps = [];
  if (row.created_at) timestamps.push(new Date(row.created_at));
  (row.referrals || []).forEach((ref) => {
    if (ref.created_at) timestamps.push(new Date(ref.created_at));
    (ref.milestones || []).forEach((ms) => {
      if (ms.created_at) timestamps.push(new Date(ms.created_at));
    });
  });
  if (timestamps.length === 0) return null;
  return timestamps.reduce((a, b) => (a > b ? a : b)).toISOString();
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
  { key: 'author', label: 'Author', default: true },
  { key: 'vulnerability_indicator', label: 'Vulnerability', default: true },
  { key: 'category', label: 'Category', default: true },
  { key: 'age', label: 'Age', default: true },
  { key: 'status', label: 'Case Status', default: true },
  { key: 'referred_to', label: 'Referred To', default: true },
  { key: 'latest_update', label: 'Latest Update', default: true },
  { key: 'created_at', label: 'Date Created', default: true },
  { key: 'actions', label: 'Actions', default: true },
];

export default function CaseIndex({ cases, filters, stats, users = [], agencies = [], categories = [] }) {
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
  const [authorFilter, setAuthorFilter] = useState(filters?.user_id ?? '');
  const [agencyFilter, setAgencyFilter] = useState(filters?.agcy_id ?? '');
  const [categoryFilter, setCategoryFilter] = useState(filters?.category_id ?? '');

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
    router.get(url.toString(), {}, { preserveState: true, replace: true });
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
    if (authorFilter) {
      const author = users.find(u => u.id === authorFilter);
      chips.push({ key: 'user_id', label: 'Author', value: author?.name || authorFilter });
    }
    if (agencyFilter) {
      const agency = agencies.find(a => a.id === agencyFilter);
      chips.push({ key: 'agcy_id', label: 'Referred To', value: agency?.name || agencyFilter });
    }
    if (categoryFilter) {
      const cat = categories?.find(c => c.id === categoryFilter);
      chips.push({ key: 'category_id', label: 'Category', value: cat?.name || categoryFilter });
    }
    return chips;
  }, [statusFilter, typeFilter, vulnFilter, authorFilter, agencyFilter, categoryFilter, users, agencies, categories]);

  const handleRemoveFilter = (filter) => {
    if (filter.key === 'status') { setStatusFilter(''); navigateWith({ status: undefined }); }
    if (filter.key === 'client_type') { setTypeFilter(''); navigateWith({ client_type: undefined }); }
    if (filter.key === 'vulnerability_indicator') { setVulnFilter(''); navigateWith({ vulnerability_indicator: undefined }); }
    if (filter.key === 'user_id') { setAuthorFilter(''); navigateWith({ user_id: undefined }); }
    if (filter.key === 'agcy_id') { setAgencyFilter(''); navigateWith({ agcy_id: undefined }); }
    if (filter.key === 'category_id') { setCategoryFilter(''); navigateWith({ category_id: undefined }); }
  };

  const handleClearFilters = () => {
    setStatusFilter('');
    setTypeFilter('');
    setVulnFilter('');
    setAuthorFilter('');
    setAgencyFilter('');
    setCategoryFilter('');
    navigateWith({ status: undefined, client_type: undefined, vulnerability_indicator: undefined, user_id: undefined, agcy_id: undefined, category_id: undefined });
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
        router.get(url.toString());
      },
      onRowsPerPageChange: (n) => {
        const url = new URL(window.location);
        url.searchParams.set('per_page', n);
        url.searchParams.delete('page');
        router.get(url.toString());
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
          case 'author':
            return {
              ...base,
              sortable: true,
              sortAccessor: (row) => row.user?.name ?? '',
              render: (row) => {
                const user = row.user;
                if (!user) return <span className="text-slate-400">—</span>;
                const initials = user.name
                  ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
                  : '??';
                return (
                  <div className="flex items-center gap-2">
                    {user.avatar_url ? (
                      <img
                        src={user.avatar_url}
                        alt={user.name}
                        className="w-7 h-7 rounded-full object-cover border border-slate-200 shrink-0"
                      />
                    ) : (
                      <span className="w-7 h-7 rounded-full bg-[#0b5384] text-white text-[10px] font-bold flex items-center justify-center shrink-0">
                        {initials}
                      </span>
                    )}
                    <span className="text-xs text-slate-700 truncate max-w-[120px]">{user.name}</span>
                  </div>
                );
              },
            };
          case 'vulnerability_indicator':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                // Show the appropriate vulnerability based on client type
                const isNok = row.client_type === 'NEXT_OF_KIN';
                const val = isNok ? row.nok_vulnerability_indicator : row.vulnerability_indicator;
                if (!val || val === 'None') return <span className="text-slate-400">&mdash;</span>;
                return (
                  <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold leading-none ${vulnStyles[val] || 'bg-slate-100 text-slate-700'}`}>
                    {val}
                  </span>
                );
              },
            };
          case 'category':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                if (!row.category) return <span className="text-slate-400">&mdash;</span>;
                return (
                  <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium"
                        style={{ backgroundColor: row.category.color ? `${row.category.color}20` : '#f1f5f9', color: row.category.color || '#64748b' }}>
                    {row.category.color && (
                      <span className="w-2 h-2 rounded-full" style={{ backgroundColor: row.category.color }} />
                    )}
                    {row.category.name}
                  </span>
                );
              },
            };
          case 'age':
            return {
              ...base,
              sortable: true,
              sortAccessor: (row) => (row.client?.date_of_birth ? new Date(row.client.date_of_birth).getTime() : 0),
              render: (row) => (
                <span className="text-xs text-slate-600">{getClientAge(row.client?.date_of_birth)}</span>
              ),
            };
          case 'status':
            return {
              ...base,
              render: (row) => (
                <StatusBadge status={row.status} />
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
          case 'latest_update':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const ts = getLatestUpdate(row);
                if (!ts) return <span className="text-slate-400">&mdash;</span>;
                return (
                  <div>
                    <div className="text-xs text-slate-700">{formatDisplayDate(ts)}</div>
                    <div className="text-[10px] text-slate-500">{formatDisplayTime(ts)}</div>
                  </div>
                );
              },
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
                  <button
                    onClick={() => router.visit(route('cases.show', row.id))}
                    className="min-h-[28px] px-2.5 bg-[#0b5384] text-white hover:bg-[#09416a] text-[11px] font-bold rounded-[3px] transition-colors border border-[#0b5384]"
                  >
                    View
                  </button>
                  <button
                    onClick={() => router.visit(route('cases.show', row.id))}
                    className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300"
                  >
                    Edit
                  </button>
                  {row.status === 'CLOSED' && (
                    <button
                      onClick={() => router.post(route('cases.archive', row.id))}
                      className="min-h-[28px] px-2.5 bg-gray-100 text-gray-600 hover:bg-gray-200 text-[11px] font-bold rounded-[3px] transition-colors border border-gray-300"
                    >
                      Archive
                    </button>
                  )}
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
          <option value="ARCHIVED">Archived</option>
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

      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Category</label>
        <select
          value={categoryFilter}
          onChange={(e) => {
            const val = e.target.value;
            setCategoryFilter(val);
            setFilterOpen(false);
            navigateWith({ category_id: val || undefined });
          }}
          className="w-full border border-[#cbd5e1] rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
        >
          <option value="">All Categories</option>
          {categories?.map((cat) => (
            <option key={cat.id} value={cat.id}>{cat.name}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Author</label>
        <select
          value={authorFilter}
          onChange={(e) => {
            const val = e.target.value;
            setAuthorFilter(val);
            setFilterOpen(false);
            navigateWith({ user_id: val || undefined });
          }}
          className="w-full border border-[#cbd5e1] rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
        >
          <option value="">All Authors</option>
          {users.map((u) => (
            <option key={u.id} value={u.id}>{u.name}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Referred To</label>
        <select
          value={agencyFilter}
          onChange={(e) => {
            const val = e.target.value;
            setAgencyFilter(val);
            setFilterOpen(false);
            navigateWith({ agcy_id: val || undefined });
          }}
          className="w-full border border-[#cbd5e1] rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
        >
          <option value="">All Agencies</option>
          {agencies.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
      </div>
    </div>
  ), [statusFilter, typeFilter, vulnFilter, authorFilter, agencyFilter, categoryFilter, users, agencies, categories]);

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
        <button
          type="button"
          onClick={() => window.open(route('cases.export-excel'))}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-slate-600 bg-white border border-slate-200 rounded-md hover:bg-slate-50 hover:text-slate-800 transition-colors"
        >
          <FileDown className="w-3.5 h-3.5" />
          Export Excel
        </button>
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
              {stats?.archived_cases > 0 && <> &middot; {stats.archived_cases} archived</>}
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
        searchPlaceholder="Search by tracking ID, client name, or client type..."
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
        extraActions={
          <>
            <button
              onClick={() => router.visit(route('cases.drafts'))}
              className="h-[40px] px-4 border border-amber-300 text-[14px] font-bold text-amber-700 rounded-[3px] bg-amber-50 flex items-center gap-2 hover:bg-amber-100 transition-colors whitespace-nowrap shrink-0"
            >
              <span className="material-symbols-outlined text-[18px]">edit_note</span>
              View Drafts
            </button>
            <button
              onClick={() => {
                const url = new URL(window.location);
                if (filters?.status === 'ARCHIVED') {
                  url.searchParams.delete('status');
                } else {
                  url.searchParams.set('status', 'ARCHIVED');
                }
                url.searchParams.delete('page');
                router.get(url.toString(), {}, { preserveState: true, replace: true });
              }}
              className="h-[40px] px-4 border border-gray-300 text-[14px] font-bold text-gray-700 rounded-[3px] bg-gray-50 flex items-center gap-2 hover:bg-gray-100 transition-colors whitespace-nowrap shrink-0"
            >
              <span className="material-symbols-outlined text-[18px]">archive</span>
              {filters?.status === 'ARCHIVED' ? 'Active Cases' : `Archived${stats?.archived_cases > 0 ? ` (${stats.archived_cases})` : ''}`}
            </button>
          </>
        }
      />
    </AppLayout>
  );
}
