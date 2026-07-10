import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import { useToast } from '@/Hooks/useToast';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import { Users, UserCheck, Shield, ArrowRightLeft } from 'lucide-react';

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
  const cf = row.case_file;
  if (!cf) return null;
  const events = [];
  if (cf.created_at) {
    const d = new Date(cf.created_at);
    if (!Number.isNaN(d.getTime())) events.push({ timestamp: d.toISOString(), title: 'Case Created', description: null });
  }
  (cf.referrals || []).forEach((ref) => {
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

function fullName(row) {
  return [row.first_name, row.middle_initial, row.last_name, row.suffix].filter(Boolean).join(' ');
}

const COLUMN_DEFS = [
  { key: 'name', label: 'Name', default: true },
  { key: 'age', label: 'Age', default: true },
  { key: 'sex', label: 'Sex', default: true },
  { key: 'contact_number', label: 'Contact #', default: true },
  { key: 'client_type', label: 'Client Type', default: true },
  { key: 'case_number', label: 'Case #', default: true },
  { key: 'case_status', label: 'Case Status', default: true },
  { key: 'vulnerability', label: 'Vulnerability', default: true },
  { key: 'category', label: 'Category', default: true },
  { key: 'issue_concern', label: 'Issue/Concern', default: false },
  { key: 'tracker_number', label: 'Tracker ID', default: false },
  { key: 'email', label: 'Email', default: false },
  { key: 'address', label: 'Address', default: false },
  { key: 'previous_country', label: 'Prev. Country', default: false },
  { key: 'date_of_arrival', label: 'Date of Arrival', default: false },
  { key: 'work_position', label: 'Work Position', default: false },
  { key: 'nok_name', label: 'NOK Name', default: false },
  { key: 'nok_contact', label: 'NOK Contact', default: false },
  { key: 'referred_to', label: 'Referred To', default: false },
  { key: 'latest_update', label: 'Latest Update', default: true },
  { key: 'created_at', label: 'Date Created', default: true },
  { key: 'actions', label: 'Actions', default: true },
];

export default function ClientIndex({ clients, filters: rawFilters, stats, users = [], agencies = [], categories = [], caseIssues = [] }) {
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
    if (filters.search) params.set('search', filters.search);
    if (filters.sex) params.set('sex', filters.sex);
    if (filters.client_type) params.set('client_type', filters.client_type);
    if (filters.vulnerability_indicator) params.set('vulnerability_indicator', filters.vulnerability_indicator);
    if (filters.case_status) params.set('case_status', filters.case_status);
    if (filters.category_id) params.set('category_id', filters.category_id);
    if (filters.case_issue_id) params.set('case_issue_id', filters.case_issue_id);
    if (filters.agcy_id) params.set('agcy_id', filters.agcy_id);

    const qs = params.toString();
    const url = route('clients.export-excel') + (qs ? '?' + qs : '');

    setIsExporting(true);
    toast.info('Preparing your export…');

    const link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

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
    router.get(route('clients.index'), clean, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['clients', 'filters', 'stats', 'users', 'agencies', 'categories', 'caseIssues'],
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

  const handleSearchClear = () => {
    setSearchValue('');
    clearTimeout(searchTimeout.current);
    updateTable({ ...filters, search: undefined, page: undefined });
  };

  const activeFilters = useMemo(() => {
    const chips = [];
    if (filters?.client_type) chips.push({ key: 'client_type', label: 'Client Type', value: filters.client_type === 'OFW' ? 'OFW' : 'NOK' });
    if (filters?.sex) chips.push({ key: 'sex', label: 'Sex', value: filters.sex });
    if (filters?.case_status) chips.push({ key: 'case_status', label: 'Case Status', value: filters.case_status });
    if (filters?.vulnerability_indicator) chips.push({ key: 'vulnerability_indicator', label: 'Vulnerability', value: filters.vulnerability_indicator });
    if (filters?.category_id) {
      const cat = categories?.find(c => c.id === filters.category_id);
      chips.push({ key: 'category_id', label: 'Category', value: cat?.name || filters.category_id });
    }
    if (filters?.case_issue_id) {
      const issue = caseIssues?.find(c => c.id === filters.case_issue_id);
      chips.push({ key: 'case_issue_id', label: 'Issue/Concern', value: issue?.name || filters.case_issue_id });
    }
    if (filters?.agcy_id) {
      const agency = agencies.find(a => a.id === filters.agcy_id);
      chips.push({ key: 'agcy_id', label: 'Referred To', value: agency?.name || filters.agcy_id });
    }
    return chips;
  }, [filters, categories, caseIssues, agencies]);

  const handleRemoveFilter = (filter) => {
    updateTable({ ...filters, [filter.key]: undefined, page: undefined });
  };

  const handleClearFilters = () => {
    updateTable({
      client_type: undefined,
      sex: undefined,
      case_status: undefined,
      vulnerability_indicator: undefined,
      category_id: undefined,
      case_issue_id: undefined,
      agcy_id: undefined,
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

  const handleStatusQuickFilter = (status) => {
    updateTable({ ...filters, case_status: status || undefined, page: undefined });
  };

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
                <div className="flex items-center gap-2">
                  <span className="inline-flex shrink-0 overflow-hidden rounded-circle">
                    {row.avatar_url ? (
                      <img
                        src={row.avatar_url}
                        alt={fullName(row)}
                        className="w-7 h-7 rounded-circle object-cover border border-slate-200"
                        onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.querySelector('.avatar-fallback').classList.remove('hidden'); }}
                      />
                    ) : null}
                    <span className={`${row.avatar_url ? 'avatar-fallback hidden' : ''} w-7 h-7 rounded-circle bg-blue-900 text-white text-[10px] font-bold flex items-center justify-center relative overflow-hidden`}>
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="absolute w-3/5 h-3/5 text-white/30">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                      </svg>
                      <span className="relative z-10">{(row.first_name?.[0] || '').toUpperCase()}{(row.last_name?.[0] || '').toUpperCase()}</span>
                    </span>
                  </span>
                  <span className="text-xs font-semibold text-slate-700 truncate max-w-[180px]">{fullName(row)}</span>
                </div>
              ),
              sortAccessor: (row) => `${row.last_name}, ${row.first_name}`,
            };
          case 'age':
            return {
              ...base,
              sortable: true,
              sortAccessor: (row) => (row.date_of_birth ? new Date(row.date_of_birth).getTime() : 0),
              render: (row) => (
                <span className="text-xs text-slate-600">{getClientAge(row.date_of_birth)}</span>
              ),
            };
          case 'sex':
            return {
              ...base,
              render: (row) => {
                if (!row.sex) return <span className="text-slate-400">&mdash;</span>;
                return <span className="text-xs text-slate-700">{row.sex === 'MALE' ? 'Male' : 'Female'}</span>;
              },
            };
          case 'contact_number':
            return {
              ...base,
              sortable: false,
              render: (row) => row.contact_number
                ? <span className="text-xs text-slate-600">{row.contact_number}</span>
                : <span className="text-slate-400">&mdash;</span>,
            };
          case 'client_type':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const ct = row.case_file?.client_type;
                if (!ct) return <span className="text-slate-400">&mdash;</span>;
                return ct === 'OFW' ? 'OFW' : 'Next of Kin';
              },
            };
          case 'case_number':
            return {
              ...base,
              sortable: false,
              render: (row) =>
                row.case_file ? (
                  <Link href={route('cases.show', row.case_file.id)} className="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-900">
                    {row.case_file.case_number}
                  </Link>
                ) : <span className="text-slate-400">&mdash;</span>,
            };
          case 'case_status':
            return {
              ...base,
              sortable: false,
              render: (row) =>
                row.case_file ? <StatusBadge status={row.case_file.status} /> : <span className="text-slate-400">&mdash;</span>,
            };
          case 'vulnerability':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const cf = row.case_file;
                if (!cf) return <span className="text-slate-400">&mdash;</span>;
                const isNok = cf.client_type === 'NOK';
                const val = isNok ? cf.nok_vulnerability_indicator : cf.vulnerability_indicator;
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
                const cat = row.case_file?.category;
                if (!cat) return <span className="text-slate-400">&mdash;</span>;
                return (
                  <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium"
                        style={{ backgroundColor: cat.color ? `${cat.color}20` : '#f1f5f9', color: cat.color || '#64748b' }}>
                    {cat.color && <span className="w-2 h-2 rounded-full" style={{ backgroundColor: cat.color }} />}
                    {cat.name}
                  </span>
                );
              },
            };
          case 'issue_concern':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const issue = row.case_file?.case_issue;
                if (!issue) return <span className="text-slate-400">&mdash;</span>;
                return <span className="text-xs text-slate-700">{issue.name}</span>;
              },
            };
          case 'tracker_number':
            return {
              ...base,
              sortable: false,
              render: (row) =>
                row.case_file?.tracker_number
                  ? <span className="font-mono text-xs text-slate-500">{row.case_file.tracker_number}</span>
                  : <span className="text-slate-400">&mdash;</span>,
            };
          case 'email':
            return {
              ...base,
              sortable: false,
              render: (row) => row.email
                ? <span className="text-xs text-slate-600 truncate max-w-[160px] block" title={row.email}>{row.email}</span>
                : <span className="text-slate-400">&mdash;</span>,
            };
          case 'address':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const addr = row.addresses?.[0];
                if (!addr) return <span className="text-slate-400">&mdash;</span>;
                const parts = [addr.barangay, addr.city_municipality].filter(Boolean);
                return <span className="text-xs text-slate-600 truncate max-w-[160px] block" title={parts.join(', ')}>{parts.join(', ') || '\u2014'}</span>;
              },
            };
          case 'previous_country':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const emp = row.employments?.[0];
                return emp?.country
                  ? <span className="text-xs text-slate-600">{emp.country}</span>
                  : <span className="text-slate-400">&mdash;</span>;
              },
            };
          case 'date_of_arrival':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const emp = row.employments?.[0];
                return emp?.date_of_arrival
                  ? <span className="text-xs text-slate-600">{formatDisplayDate(emp.date_of_arrival)}</span>
                  : <span className="text-slate-400">&mdash;</span>;
              },
            };
          case 'work_position':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const emp = row.employments?.[0];
                return emp?.position
                  ? <span className="text-xs text-slate-600 truncate max-w-[140px] block">{emp.position}</span>
                  : <span className="text-slate-400">&mdash;</span>;
              },
            };
          case 'nok_name':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const nok = row.next_of_kin?.find(n => n.is_primary) || row.next_of_kin?.[0];
                if (!nok) return <span className="text-slate-400">&mdash;</span>;
                return <span className="text-xs text-slate-600">{fullName(nok)}</span>;
              },
            };
          case 'nok_contact':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const nok = row.next_of_kin?.find(n => n.is_primary) || row.next_of_kin?.[0];
                if (!nok) return <span className="text-slate-400">&mdash;</span>;
                const contact = nok.phone_number || nok.email || '';
                return contact
                  ? <span className="text-xs text-slate-600 truncate max-w-[140px] block" title={contact}>{contact}</span>
                  : <span className="text-slate-400">&mdash;</span>;
              },
            };
          case 'referred_to':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600 max-w-[180px] truncate block">
                  {referredToAgencies(row.case_file?.referrals)}
                </span>
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
                    onClick={() => router.visit(route('clients.show', row.id))}
                    className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors border border-blue-900"
                  >
                    View
                  </button>
                  {row.case_file && (
                    <button
                      onClick={() => router.visit(route('cases.show', row.case_file.id))}
                      className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300"
                    >
                      Case
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

  const quickFilterPills = useMemo(() => {
    const statuses = [
      { label: 'All', value: '' },
      { label: 'Open Cases', value: 'OPEN' },
      { label: 'Closed Cases', value: 'CLOSED' },
    ];
    const currentStatus = filters?.case_status ?? '';

    return (
      <div className="flex items-center gap-1.5" role="group" aria-label="Quick case status filters">
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
            </button>
          );
        })}
      </div>
    );
  }, [filters?.case_status]);

  const advancedFilterContent = useMemo(() => (
    <div className="space-y-4">
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
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Sex</label>
        <select
          value={filters?.sex ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, sex: val || undefined, page: undefined });
          }}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        >
          <option value="">All</option>
          <option value="MALE">Male</option>
          <option value="FEMALE">Female</option>
        </select>
      </div>
      <div>
        <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Case Status</label>
        <select
          value={filters?.case_status ?? ''}
          onChange={(e) => {
            const val = e.target.value;
            updateTable({ ...filters, case_status: val || undefined, page: undefined });
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
  ), [filters, categories, caseIssues, agencies]);

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
    <AppLayout title="Clients">
      <Head title="Clients" />

      <header data-tour="clients-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Clients
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">View all registered clients and their associated cases.</p>
        </div>
        <button
          type="button"
          onClick={handleExport}
          disabled={isExporting}
          className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0b5384] text-white hover:bg-[#09416a] text-[12px] font-bold rounded-md transition-colors border border-[#0b5384] disabled:opacity-60 disabled:cursor-not-allowed"
        >
          <span className="material-symbols-outlined text-[18px]">{isExporting ? 'sync' : 'download'}</span>
          {isExporting ? 'Exporting\u2026' : 'Export Excel'}
        </button>
      </header>

      <div data-tour="clients-stats">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Clients</p>
              <span className="p-1.5 bg-blue-50 rounded-lg"><Users className="w-4 h-4 text-blue-900" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{stats?.total_clients ?? 0}</p>
            <div className="flex items-center gap-1.5 mt-1.5">
              <span className="text-[11px] font-bold text-emerald-600">
                {stats?.clients_with_open_cases ?? 0} with open cases
              </span>
            </div>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">OFW Clients</p>
              <span className="p-1.5 bg-violet-50 rounded-lg"><UserCheck className="w-4 h-4 text-violet-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{stats?.ofw_clients ?? 0}</p>
            <div className="flex items-center gap-1.5 mt-1.5">
              <span className="text-[11px] font-bold text-violet-600">OFW</span>
              <span className="text-[11px] text-slate-400">&middot;</span>
              <span className="text-[11px] text-slate-500">{stats?.nok_clients ?? 0} Next of Kin</span>
            </div>
            <p className="text-[10px] text-slate-400 mt-0.5">Client type distribution</p>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Vulnerable Clients</p>
              <span className="p-1.5 bg-amber-50 rounded-lg"><Shield className="w-4 h-4 text-amber-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">
              {stats?.vulnerability_counts
                ? Object.values(stats.vulnerability_counts).reduce((a, b) => a + b, 0)
                : 0}
            </p>
            <div className="flex flex-wrap gap-x-2 gap-y-0.5 mt-1.5">
              {stats?.vulnerability_counts && Object.entries(stats.vulnerability_counts).map(([key, count]) => (
                count > 0 && (
                  <span key={key} className={`inline-flex rounded-full px-1.5 py-0.5 text-[9px] font-bold leading-none ${vulnStyles[key] || 'bg-slate-100 text-slate-700'}`}>
                    {key}: {count}
                  </span>
                )
              ))}
            </div>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div className="flex items-start justify-between mb-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Referrals</p>
              <span className="p-1.5 bg-amber-50 rounded-lg"><ArrowRightLeft className="w-4 h-4 text-amber-600" /></span>
            </div>
            <p className="text-2xl font-black text-slate-900">{stats?.total_referrals ?? 0}</p>
            <p className="text-[10px] text-slate-400 mt-0.5">Across all client cases</p>
          </div>
        </section>
      </div>

      <div data-tour="clients-table">
      <UnifiedTable
        columns={columns}
        data={clients.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(clients)}
        isLoading={tableLoading}
        sortKey={filters?.sort ?? 'created_at'}
        sortDirection={filters?.direction ?? 'desc'}
        onSortChange={handleSortChange}
        searchValue={searchValue}
        searchPlaceholder="Search by name, email, contact, case #, or tracker ID..."
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
        onRowContextMenu={handleRowContextMenu}
        activeFilters={activeFilters}
        activeFilterCount={activeFilters.length}
        onRemoveFilter={handleRemoveFilter}
        onClearFilters={handleClearFilters}
        quickFilters={quickFilterPills}
      />
      </div>

      {contextMenu && (
        <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
          <RowContextMenuItem icon="visibility" label="View Client" onClick={() => {
            router.visit(route('clients.show', contextMenu.row.id));
            setContextMenu(null);
          }} />
          {contextMenu.row.case_file && (
            <RowContextMenuItem icon="folder" label="View Case" onClick={() => {
              router.visit(route('cases.show', contextMenu.row.case_file.id));
              setContextMenu(null);
            }} />
          )}
        </RowContextMenu>
      )}
    </AppLayout>
  );
}
