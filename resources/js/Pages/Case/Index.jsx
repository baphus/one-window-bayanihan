import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { useToast } from '@/Hooks/useToast';
import StatusBadge from '@/Components/ui/StatusBadge';
import { FolderCheck, Users, ArrowRightLeft, TrendingUp, Clock } from 'lucide-react';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';

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
  const events = [];
  if (row.created_at) {
    const d = new Date(row.created_at);
    if (!Number.isNaN(d.getTime())) events.push({ timestamp: d.toISOString(), title: 'Case Created', description: null });
  }
  (row.referrals || []).forEach((ref) => {
    if (ref.created_at) {
      const d = new Date(ref.created_at);
      if (!Number.isNaN(d.getTime())) events.push({ timestamp: d.toISOString(), title: `Referred to ${ref.agency?.name || 'Agency'}`, description: ref.required_services || null });
    }
    (ref.milestones || []).forEach((ms) => {
      if (ms.created_at) {
        const d = new Date(ms.created_at);
        if (!Number.isNaN(d.getTime())) events.push({ timestamp: d.toISOString(), title: ms.title, description: ms.description || null });
      }
    });
  });
  if (events.length === 0) return null;
  return events.reduce((a, b) => (a.timestamp > b.timestamp ? a : b));
}

function referredToAgencies(referrals) {
  if (!referrals || referrals.length === 0) return '\u2014';
  const names = [...new Set(referrals.map((r) => r.agency?.name).filter(Boolean))];
  return names.length > 0 ? names.join(', ') : '\u2014';
}

const COLUMN_DEFS = [
  { key: 'case_number', label: 'Case Number', default: true },
  { key: 'tracker_number', label: 'Tracking ID', default: true },
  { key: 'client_type', label: 'Client Type', default: true },
  { key: 'client_name', label: 'Client Name', default: true },
  { key: 'author', label: 'Author', default: true },
  { key: 'vulnerability_indicator', label: 'Vulnerability', default: true },
  { key: 'category', label: 'Category', default: true },
  { key: 'issue_concern', label: 'Issue/Concern', default: true },
  { key: 'age', label: 'Age', default: true },
  { key: 'status', label: 'Case Status', default: true },
  { key: 'referred_to', label: 'Referred To', default: true },
  { key: 'latest_update', label: 'Latest Update', default: true },
  { key: 'created_at', label: 'Date Created', default: true },
  { key: 'actions', label: 'Actions', default: true },
];

export default function CaseIndex({ cases, filters: rawFilters, stats, users = [], agencies = [], categories = [], caseIssues = [] }) {
  const { auth } = usePage().props;
  const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';
  const filters = rawFilters && !Array.isArray(rawFilters) ? rawFilters : {};

  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [viewMode, setViewMode] = useState('list');
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);

  const searchTimeout = useRef(null);

  const [visibleColumns, setVisibleColumns] = useState(
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  const [tableLoading, setTableLoading] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [contextMenu, setContextMenu] = useState(null);
  const toast = useToast();

  const handleExport = useCallback(() => {
    const params = new URLSearchParams();
    if (filters.status) params.set('status', filters.status);
    if (filters.search) params.set('search', filters.search);
    if (filters.client_type) params.set('client_type', filters.client_type);
    if (filters.vulnerability_indicator) params.set('vulnerability_indicator', filters.vulnerability_indicator);
    if (filters.user_id) params.set('user_id', filters.user_id);
    if (filters.agcy_id) params.set('agcy_id', filters.agcy_id);
    if (filters.category_id) params.set('category_id', filters.category_id);
    if (filters.case_issue_id) params.set('case_issue_id', filters.case_issue_id);

    const qs = params.toString();
    const url = route('cases.export-excel') + (qs ? '?' + qs : '');

    setIsExporting(true);
    toast.info('Preparing your export…');

    // Trigger download without opening a new tab
    const link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Reset after a reasonable timeout
    setTimeout(() => setIsExporting(false), 5000);
  }, [filters, toast]);

  const handleRowContextMenu = (e, row) => {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  };

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  useEffect(() => {
    if (!filterOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setFilterOpen(false);
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [filterOpen]);

  const updateTable = (params) => {
    const clean = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    router.get(route('cases.index'), clean, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['cases', 'filters', 'stats', 'users', 'agencies', 'categories', 'caseIssues'],
      showProgress: false,
    });
  };

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

  const handleSearchChange = (value) => {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      updateTable({ ...filters, search: value || undefined, page: undefined });
    }, 400);
  };

  const activeFilters = useMemo(() => {
    const chips = [];
    if (filters?.status) chips.push({ key: 'status', label: 'Status', value: filters.status });
    if (filters?.client_type) chips.push({ key: 'client_type', label: 'Client Type', value: filters.client_type === 'OFW' ? 'OFW' : 'NOK' });
    if (filters?.vulnerability_indicator) chips.push({ key: 'vulnerability_indicator', label: 'Vulnerability', value: filters.vulnerability_indicator });
    if (filters?.user_id) {
      const author = users.find(u => u.id === filters.user_id);
      chips.push({ key: 'user_id', label: 'Author', value: author?.name || filters.user_id });
    }
    if (filters?.agcy_id) {
      const agency = agencies.find(a => a.id === filters.agcy_id);
      chips.push({ key: 'agcy_id', label: 'Referred To', value: agency?.name || filters.agcy_id });
    }
    if (filters?.category_id) {
      const cat = categories?.find(c => c.id === filters.category_id);
      chips.push({ key: 'category_id', label: 'Category', value: cat?.name || filters.category_id });
    }
    if (filters?.case_issue_id) {
      const issue = caseIssues?.find(c => c.id === filters.case_issue_id);
      chips.push({ key: 'case_issue_id', label: 'Issue/Concern', value: issue?.name || filters.case_issue_id });
    }
    return chips;
  }, [filters, users, agencies, categories, caseIssues]);

  const handleRemoveFilter = (filter) => {
    updateTable({ ...filters, [filter.key]: undefined, page: undefined });
  };

  const handleClearFilters = () => {
    updateTable({
      status: undefined,
      client_type: undefined,
      vulnerability_indicator: undefined,
      user_id: undefined,
      agcy_id: undefined,
      category_id: undefined,
      case_issue_id: undefined,
      search: undefined,
      page: undefined,
    });
  };

  const handleSortChange = (sortKey, sortDirection) => {
    updateTable({ ...filters, sort: sortKey, direction: sortDirection, page: undefined });
  };

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator.total,
      startIndex: paginator.from,
      endIndex: paginator.to,
      currentPage: paginator.current_page,
      totalPages: paginator.last_page,
      rowsPerPage: paginator.per_page,
      onPageChange: (page) => updateTable({ ...filters, page }),
      onRowsPerPageChange: (n) => updateTable({ ...filters, per_page: n, page: undefined }),
    };
  }

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
          case 'tracker_number':
            return {
              ...base,
              render: (row) => (
                <span className="font-mono text-xs text-slate-500">{row.tracker_number}</span>
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
                return (
                  <div className="flex items-center gap-2">
                    <span className="inline-flex shrink-0 overflow-hidden rounded-circle">
                      {user.avatar_url ? (
                        <img
                          src={user.avatar_url}
                          alt={user.name}
                          className="w-7 h-7 rounded-circle object-cover border border-slate-200"
                          onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.querySelector('.avatar-fallback').classList.remove('hidden'); }}
                        />
                      ) : null}
                      <span className={`${user.avatar_url ? 'avatar-fallback hidden' : ''} w-7 h-7 rounded-circle bg-blue-900 flex items-center justify-center relative overflow-hidden`}>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-3/5 h-3/5 text-white/30">
                          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                      </span>
                    </span>
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
          case 'issue_concern':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                if (!row.case_issue) return <span className="text-slate-400">&mdash;</span>;
                return <span className="text-xs text-slate-700">{row.case_issue.name}</span>;
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
                const ev = getLatestUpdate(row);
                if (!ev) return <span className="text-slate-400">&mdash;</span>;
                return (
                  <div className="max-w-[200px]">
                    <div className="text-xs font-medium text-slate-800 truncate" title={ev.title}>{ev.title}</div>
                    {ev.description && (
                      <div className="text-[10px] text-slate-500 truncate" title={ev.description}>{ev.description}</div>
                    )}
                    <div className="text-[10px] text-slate-400 mt-0.5">{formatDisplayDate(ev.timestamp)} {formatDisplayTime(ev.timestamp)}</div>
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
                    className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors border border-blue-900"
                  >
                    View
                  </button>
                  <button
                    onClick={() => router.visit(`${route('cases.show', row.id)}?edit=1`)}
                    className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300"
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

  const handleSearchClear = () => {
    setSearchValue('');
    clearTimeout(searchTimeout.current);
    updateTable({ ...filters, search: undefined, page: undefined });
  };

  const handleStatusQuickFilter = (status) => {
    updateTable({ ...filters, status: status || undefined, page: undefined });
  };

  const advancedFilterContent = useMemo(() => (
    <div className="space-y-4">
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Status</label>
        <select
          value={filters?.status ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, status: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
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
          value={filters?.client_type ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, client_type: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Types</option>
          <option value="OFW">OFW</option>
          <option value="NOK">Next of Kin</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Vulnerability</label>
        <select
          value={filters?.vulnerability_indicator ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, vulnerability_indicator: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
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
          value={filters?.category_id ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, category_id: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Categories</option>
          {categories?.map((cat) => (
            <option key={cat.id} value={cat.id}>{cat.name}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Issue/Concern</label>
        <select
          value={filters?.case_issue_id ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, case_issue_id: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Issues</option>
          {caseIssues?.map((issue) => (
            <option key={issue.id} value={issue.id}>{issue.name}</option>
          ))}
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Author</label>
        <select
          value={filters?.user_id ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, user_id: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
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
          value={filters?.agcy_id ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, agcy_id: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All Agencies</option>
          {agencies.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
      </div>
      <div className="border-t border-slate-200 pt-4 mt-4">
        <button
          type="button"
          onClick={() => setFilterOpen(false)}
          className="w-full h-[36px] bg-blue-900 text-white text-[13px] font-bold rounded-[2px] hover:bg-blue-800 transition-colors"
        >
          Done
        </button>
      </div>
    </div>
  ), [filters, users, agencies, categories, caseIssues]);

  const quickFilterPills = useMemo(() => {
    const statuses = [
      { label: 'All', value: '' },
      { label: 'Open', value: 'OPEN' },
      { label: 'Closed', value: 'CLOSED' },
      { label: 'Archived', value: 'ARCHIVED' },
    ];
    const currentStatus = filters?.status ?? '';

    return (
      <div className="flex items-center gap-1.5" role="group" aria-label="Quick status filters">
        <span className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mr-1">Show:</span>
        {statuses.map((s) => {
          const isActive = currentStatus === s.value || (!currentStatus && s.value === '');
          return (
            <button
              key={s.label}
              onClick={() => handleStatusQuickFilter(s.value || undefined)}
              className={`px-3 py-1.5 text-[12px] font-bold rounded-md transition-colors border ${
                isActive
                  ? 'bg-blue-900 text-white border-blue-900 shadow-sm'
                  : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50 hover:text-slate-800'
              }`}
            >
              {s.label}
              {s.label === 'Archived' && stats?.archived_cases > 0 && ` (${stats.archived_cases})`}
            </button>
          );
        })}
      </div>
    );
  }, [filters?.status, stats?.archived_cases]);

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
  ), [visibleColumns]);

  return (
    <AppLayout title="Case Management">
      <Head title="Case Management" />

      <header data-tour="cases-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Cases
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Manage all client cases and track their progress.</p>
        </div>
        <button
          type="button"
          onClick={handleExport}
          disabled={isExporting}
          className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0b5384] text-white hover:bg-[#09416a] text-[12px] font-bold rounded-md transition-colors border border-[#0b5384] disabled:opacity-60 disabled:cursor-not-allowed"
        >
          <span className="material-symbols-outlined text-[18px]">{isExporting ? 'sync' : 'download'}</span>
          {isExporting ? 'Exporting…' : 'Export Excel'}
        </button>
      </header>

      <div data-tour="cases-filter">
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
      </div>

      <div data-tour="cases-table">
      <UnifiedTable
        columns={columns}
        data={cases.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(cases)}
        isLoading={tableLoading}
        sortKey={filters?.sort ?? 'created_at'}
        sortDirection={filters?.direction ?? 'desc'}
        onSortChange={handleSortChange}
        searchValue={searchValue}
        searchPlaceholder="Search by tracking ID, client name, or client type..."
        onSearchChange={handleSearchChange}
        onSearchClear={handleSearchClear}
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
        onRowContextMenu={handleRowContextMenu}
        activeFilters={activeFilters}
        activeFilterCount={activeFilters.length}
        onRemoveFilter={handleRemoveFilter}
        onClearFilters={handleClearFilters}
        quickFilters={quickFilterPills}
        extraActions={
          <button
            onClick={() => router.visit(route('cases.drafts'))}
            className="h-[40px] px-4 border border-amber-300 text-[14px] font-bold text-amber-700 rounded-[3px] bg-amber-50 flex items-center gap-2 hover:bg-amber-100 transition-colors whitespace-nowrap shrink-0"
          >
            <span className="material-symbols-outlined text-[18px]">edit_note</span>
            View Drafts
          </button>
        }
      />
      </div>

      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="visibility" label="View" onClick={() => {
            router.visit(route('cases.show', contextMenu.row.id));
            setContextMenu(null);
          }} />
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            router.visit(`${route('cases.show', contextMenu.row.id)}?edit=1`);
            setContextMenu(null);
          }} />
          {contextMenu.row.status === 'CLOSED' && (
            <RowContextMenuItem icon="archive" label="Archive" onClick={() => {
              router.post(route('cases.archive', contextMenu.row.id), {}, { preserveScroll: true });
              setContextMenu(null);
            }} />
          )}
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
