import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import TableTabs from '@/Components/ui/TableTabs';
import FilterPanel from '@/Components/ui/FilterPanel';
import { toFilterList, toggleFilterValue, useArrayFilters } from '@/Hooks/useArrayFilters';
import StatusBadge from '@/Components/ui/StatusBadge';
import { FolderCheck, Users, ArrowRightLeft, TrendingUp, Clock } from 'lucide-react';
import { formatDisplayDate, formatDisplayTime, getCategoryFilterIds } from '@/lib/utils';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import ExportDialog from '@/Components/ExportDialog';
import ExportExcelButton from '@/Components/ExportExcelButton';
import usePersistedColumns from '@/Hooks/usePersistedColumns';
import { usePersistedViewMode, usePersistedFilters } from '@/Hooks/usePersistedViewState';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

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

function hasActiveReferrals(referrals) {
  return (referrals || []).some((ref) => !['COMPLETED', 'REJECTED'].includes(ref.status));
}

function canArchiveCase(caseFile) {
  return caseFile.status === 'CLOSED' && !hasActiveReferrals(caseFile.referrals);
}

function getCaseCategories(caseFile, availableCategories = []) {
  if (Array.isArray(caseFile?.categories) && caseFile.categories.length) return caseFile.categories;
  if (caseFile?.category) return [caseFile.category];
  // Responses containing only IDs are supported as a final compatibility fallback.
  return (caseFile?.category_ids || []).map((id) => availableCategories.find((category) => String(category.id) === String(id))).filter(Boolean);
}

// Merged cells keep identifiers and qualifying details together; source fields remain optional.
const COLUMN_DEFS = [
  { key: 'case_reference', label: 'Case / Tracking ID', sortKey: 'case_number', default: true },
  { key: 'client', label: 'Client', sortKey: 'client_name', default: true },
  { key: 'issue_category', label: 'Issue / Category', sortable: false, default: true },
  { key: 'vulnerability_indicator', label: 'Vulnerability', sortable: false, default: true },
  { key: 'author', label: 'Author', default: true },
  { key: 'status', label: 'Case Status', default: true },
  { key: 'approval', label: 'Approval', sortable: false, default: true },
  { key: 'referred_to', label: 'Referred To', sortable: false, default: true },
  { key: 'latest_update', label: 'Latest Update', sortable: false, default: true },
  { key: 'created_at', label: 'Date Created', default: true },
  { key: 'actions', label: 'Actions', sortable: false, default: true },
  { key: 'case_number', label: 'Case Number', default: false },
  { key: 'tracker_number', label: 'Tracking ID', default: false },
  { key: 'client_type', label: 'Client Type', default: false },
  { key: 'client_name', label: 'Client Name', default: false },
  { key: 'category', label: 'Category', sortable: false, default: false },
  { key: 'issue_concern', label: 'Issue/Concern', sortable: false, default: false },
  { key: 'age', label: 'Age', sortable: false, default: false },
];

export default function CaseIndex({ cases, filters: rawFilters, stats, users = [], agencies = [], categories = [], caseIssues = [] }) {
  const { auth, roles } = usePage().props;
  const canCreate = auth.user.role === roles.CASE_MANAGER || auth.user.role === roles.ADMIN;
  const filters = rawFilters && !Array.isArray(rawFilters) ? rawFilters : {};

  const [searchValue, setSearchValue] = useState(filters?.search ?? '');
  const [viewMode, setViewMode] = usePersistedViewMode('owb-view_cases');
  const [filterOpen, setFilterOpen] = useState(false);
  const [columnsOpen, setColumnsOpen] = useState(false);

  const searchTimeout = useRef(null);

  const [visibleColumns, setVisibleColumns] = usePersistedColumns(
    'cases-v2',
    COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
  );

  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();
  const [exportDialogOpen, setExportDialogOpen] = useState(false);
  const [contextMenu, setContextMenu] = useState(null);
  const actionTriggerRef = useRef(null);
  const [confirmArchiveId, setConfirmArchiveId] = useState(null);

  const handleExport = useCallback(() => {
    setExportDialogOpen(true);
  }, []);

  // Single query builder shared by the export download and the live row-count
  // preview, so the count shown always matches the export that will run.
  const buildExportQuery = useCallback(({ dateFrom, dateTo }) => {
    const params = new URLSearchParams();
    // List filters may arrive as arrays (checkbox panel) or scalars (old URLs),
    // so every one is appended in the `key[]=` form the export endpoint reads.
    const appendList = (key, value) => {
      toFilterList(value).forEach((item) => params.append(`${key}[]`, item));
    };

    appendList('status', filters.status);
    if (filters.search) params.set('search', filters.search);
    appendList('client_type', filters.client_type);
    appendList('vulnerability_indicator', filters.vulnerability_indicator);
    appendList('user_id', filters.user_id);
    appendList('agcy_id', filters.agcy_id);
    getCategoryFilterIds(filters).forEach((id) => params.append('category_ids[]', id));
    appendList('case_issue_id', filters.case_issue_id);
    if (filters.age_min_days) params.set('age_min_days', filters.age_min_days);
    if (filters.referral_state) params.set('referral_state', filters.referral_state);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    return params.toString();
  }, [filters]);

  const handleExportConfirm = useCallback(({ dateFrom, dateTo }) => {
    const qs = buildExportQuery({ dateFrom, dateTo });
    const url = route('cases.export-excel') + (qs ? '?' + qs : '');

    setExportDialogOpen(false);
    window.location.href = url;
  }, [buildExportQuery]);

  const countUrlBuilder = useCallback((dateFrom, dateTo) => {
    const qs = buildExportQuery({ dateFrom, dateTo });
    return route('cases.export-count') + (qs ? '?' + qs : '');
  }, [buildExportQuery]);

  const activeFilterChips = useMemo(() => {
    const chips = [];
    const labelsFor = (options) => Object.fromEntries(options.map((o) => [String(o.value), o.label]));
    const labelList = (value, options) => {
      const labels = labelsFor(options);
      return toFilterList(value).map((v) => labels[v] ?? v).join(', ');
    };

    if (toFilterList(filters?.status).length) chips.push({ label: 'Status', value: labelList(filters.status, [
      { value: 'OPEN', label: 'Open' }, { value: 'CLOSED', label: 'Closed' }, { value: 'ARCHIVED', label: 'Archived' },
    ]) });
    if (toFilterList(filters?.client_type).length) chips.push({ label: 'Client Type', value: labelList(filters.client_type, [
      { value: 'OFW', label: 'OFW' }, { value: 'NEXT_OF_KIN', label: 'Next of Kin' },
    ]) });
    if (toFilterList(filters?.vulnerability_indicator).length) chips.push({ label: 'Vulnerability', value: toFilterList(filters.vulnerability_indicator).join(', ') });
    if (getCategoryFilterIds(filters).length) {
      const names = getCategoryFilterIds(filters).map((id) => categories?.find((c) => String(c.id) === id)?.name || id);
      chips.push({ label: 'Category', value: names.join(', ') });
    }
    if (toFilterList(filters?.case_issue_id).length) chips.push({ label: 'Issue', value: labelList(filters.case_issue_id, (caseIssues ?? []).map((c) => ({ value: c.id, label: c.name }))) });
    if (toFilterList(filters?.age_min_days).length) chips.push({ label: 'Case Age', value: toFilterList(filters.age_min_days).map((d) => `${d}+ days`).join(', ') });
    if (toFilterList(filters?.user_id).length) chips.push({ label: 'Author', value: labelList(filters.user_id, users.map((u) => ({ value: u.id, label: u.name }))) });
    if (toFilterList(filters?.agcy_id).length) chips.push({ label: 'Referred To', value: labelList(filters.agcy_id, agencies.map((a) => ({ value: a.id, label: a.name }))) });
    if (filters?.referral_state) chips.push({ label: 'Referral State', value: filters.referral_state });
    if (filters?.date_from) chips.push({ label: 'From', value: filters.date_from });
    if (filters?.date_to) chips.push({ label: 'To', value: filters.date_to });
    return chips;
  }, [filters]);

  const handleRowContextMenu = (e, row) => {
    e.preventDefault();
    actionTriggerRef.current = null;
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  };

  const handleActionMenu = (e, row) => {
    e.stopPropagation();
    actionTriggerRef.current = e.currentTarget;
    const rect = e.currentTarget.getBoundingClientRect();
    setContextMenu({ x: rect.right, y: rect.bottom, anchorRect: rect, row });
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

  // Latest filter state for the debounced search. Server-driven filters only
  // change when a response arrives, but a filter clicked while a search is still
  // pending has not round-tripped yet, so params sent while a search timer is
  // live are remembered and the timer merges into them. When no search is
  // pending the props are authoritative and nothing needs tracking — tracking
  // them unconditionally would let a mount-time filter restore shadow the props.
  const filtersRef = useRef(filters);
  filtersRef.current = filters;
  const pendingParamsRef = useRef(null);

  // Params of a navigation sent while a search timer is live. A filter clicked
  // during the 400ms window issues its own request before the server has sent
  // the new props back, so the timer cannot read them from `filters`. This must
  // be captured by whoever issues the request while the timer is armed — the
  // timer clears its own id before calling updateTable, so updateTable itself
  // can no longer tell that a search is pending.
  const trackPending = (clean) => {
    if (searchTimeout.current) {
      pendingParamsRef.current = clean;
    }
  };

  const updateTable = (params) => {
    const clean = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    trackPending(clean);
    router.get(route('cases.index'), clean, withLoading({
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['cases', 'filters', 'stats', 'users', 'agencies', 'categories', 'caseIssues'],
    }));
  };

  const restoreFilters = (saved) => {
    setSearchValue(saved.search ?? '');
    updateTable(saved);
  };
  usePersistedFilters('owb-filters_cases', filters, restoreFilters);

  const handleSearchChange = (value) => {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      const base = pendingParamsRef.current ?? filtersRef.current;
      pendingParamsRef.current = null;
      updateTable({ ...base, search: value || undefined, page: undefined });
      // Cleared AFTER updateTable: updateTable -> trackPending must not record
      // this very request as "pending". Anything issued later (a filter click
      // while the response is in flight) is authoritative on its own.
      searchTimeout.current = null;
    }, 400);
  };

  const activeFilters = useMemo(() => {
    const chips = [];
    const join = (list, labels) => list.map((value) => labels?.[value] ?? value).join(', ');

    const statusList = toFilterList(filters?.status);
    if (statusList.length) chips.push({ key: 'status', label: 'Status', value: join(statusList, { OPEN: 'Open', CLOSED: 'Closed', ARCHIVED: 'Archived' }) });

    const clientTypeList = toFilterList(filters?.client_type);
    if (clientTypeList.length) chips.push({ key: 'client_type', label: 'Client Type', value: join(clientTypeList, { OFW: 'OFW', NEXT_OF_KIN: 'Next of Kin' }) });

    const vulnerabilityList = toFilterList(filters?.vulnerability_indicator);
    if (vulnerabilityList.length) chips.push({ key: 'vulnerability_indicator', label: 'Vulnerability', value: vulnerabilityList.join(', ') });

    const authorList = toFilterList(filters?.user_id);
    if (authorList.length) {
      chips.push({ key: 'user_id', label: 'Author', value: join(authorList, Object.fromEntries(users.map((u) => [u.id, u.name]))) });
    }

    const agencyList = toFilterList(filters?.agcy_id);
    if (agencyList.length) {
      chips.push({ key: 'agcy_id', label: 'Referred To', value: join(agencyList, Object.fromEntries(agencies.map((a) => [a.id, a.name]))) });
    }

    if (getCategoryFilterIds(filters).length) {
      const ids = getCategoryFilterIds(filters);
      const names = ids.map((id) => categories?.find(c => String(c.id) === id)?.name || id);
      chips.push({ key: 'category_ids', label: 'Category', value: names.join(', ') });
    }

    const issueList = toFilterList(filters?.case_issue_id);
    if (issueList.length) {
      chips.push({ key: 'case_issue_id', label: 'Issue/Concern', value: join(issueList, Object.fromEntries((caseIssues ?? []).map((c) => [c.id, c.name]))) });
    }

    const ageList = toFilterList(filters?.age_min_days);
    if (ageList.length) chips.push({ key: 'age_min_days', label: 'Age', value: ageList.map((d) => `${d}+ days`).join(', ') });

    if (filters?.referral_state === 'none') chips.push({ key: 'referral_state', label: 'Referrals', value: 'None' });
    return chips;
  }, [filters, users, agencies, categories, caseIssues]);

  const handleClearFilters = () => {
    updateTable({
      status: undefined,
      client_type: undefined,
      vulnerability_indicator: undefined,
      user_id: undefined,
      agcy_id: undefined,
      category_ids: undefined,
      case_issue_id: undefined,
      age_min_days: undefined,
      referral_state: undefined,
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
        const base = { key: col.key, title: col.label, sortable: col.sortable ?? true, ...(col.sortKey ? { sortKey: col.sortKey } : {}) };
        switch (col.key) {
          case 'case_reference':
            return {
              ...base,
              render: (row) => (
                <div className="space-y-0.5">
                  <button
                    type="button"
                    onClick={() => router.visit(route('cases.show', row.id))}
                    className="block font-mono text-xs font-bold text-blue-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    aria-label={row.case_number}
                  >
                    {row.case_number}
                  </button>
                  <span className="block font-mono text-[10px] text-slate-500">{row.tracker_number}</span>
                </div>
              ),
            };
          case 'client':
            return {
              ...base,
              render: (row) => {
                const clientName = row.client ? `${row.client.first_name} ${row.client.last_name}` : 'N/A';
                const clientType = row.client_type === 'OFW' ? 'OFW' : 'Next of Kin';
                return (
                  <div>
                    <div className="text-xs font-medium text-slate-800">{clientName}</div>
                    <div className="text-[10px] text-slate-500">{clientType} · {getClientAge(row.client?.date_of_birth)}</div>
                  </div>
                );
              },
              sortAccessor: (row) => row.client ? `${row.client.last_name}, ${row.client.first_name}` : '',
            };
          case 'issue_category':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const rowCategories = getCaseCategories(row, categories);
                return (
                  <div className="space-y-1">
                    <div className="text-xs text-slate-700">{row.case_issue?.name || <span className="text-slate-400">&mdash;</span>}</div>
                    <div className="flex max-w-[220px] flex-wrap gap-1">
                      {rowCategories.length > 0 ? rowCategories.map((category) => (
                        <span key={category.id || category.name} className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                          style={{ backgroundColor: category.color ? `${category.color}20` : '#f1f5f9', color: category.color || '#64748b' }}>
                          {category.color && <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: category.color }} />}
                          {category.name || category.title}
                        </span>
                      )) : <span className="text-[10px] text-slate-400">&mdash;</span>}
                    </div>
                  </div>
                );
              },
            };
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
                    <span className="inline-flex shrink-0 overflow-hidden rounded-full">
                      {user.avatar_url ? (
                        <img
                          src={user.avatar_url}
                          alt={user.name}
                          className="w-7 h-7 rounded-full object-cover border border-slate-200"
                          onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.querySelector('.avatar-fallback').classList.remove('hidden'); }}
                        />
                      ) : null}
                      <span className={`${user.avatar_url ? 'avatar-fallback hidden' : ''} w-7 h-7 rounded-full bg-blue-900 flex items-center justify-center relative overflow-hidden`}>
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
          case 'category':
            return {
              ...base,
              sortable: false,
              render: (row) => {
                const rowCategories = getCaseCategories(row, categories);
                if (!rowCategories.length) return <span className="text-slate-400">&mdash;</span>;
                return (
                  <div className="flex max-w-[220px] flex-wrap gap-1">
                    {rowCategories.map((category) => (
                      <span key={category.id || category.name} className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        style={{ backgroundColor: category.color ? `${category.color}20` : '#f1f5f9', color: category.color || '#64748b' }}>
                        {category.color && <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: category.color }} />}
                        {category.name || category.title}
                      </span>
                    ))}
                  </div>
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
              // Not sortable: clients.date_of_birth is an encrypted column, so
              // age cannot be ordered in SQL. The table sorts server-side, so
              // offering it here would only ever produce a rejected request.
              sortable: false,
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
          case 'approval':
            return {
              ...base,
              sortable: false,
              render: (row) => (
                <span className="text-xs text-slate-600">{row.reviewer?.name || <span className="text-slate-400">&mdash;</span>}</span>
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
              render: (row) => (
                <button
                  type="button"
                  aria-label={`Actions for case ${row.case_number}`}
                  aria-haspopup="menu"
                  aria-expanded={contextMenu?.row?.id === row.id}
                  onClick={(e) => handleActionMenu(e, row)}
                  className="inline-flex h-8 w-8 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                  <span aria-hidden="true" className="material-symbols-outlined text-[20px]">more_vert</span>
                </button>
              ),
            };
          default:
            return { ...base, render: (row) => row[col.key] };
        }
      }),
  [visibleColumns, categories]);

  const handleSearchClear = () => {
    setSearchValue('');
    clearTimeout(searchTimeout.current);
    updateTable({ ...filters, search: undefined, page: undefined });
  };

  const handleStatusQuickFilter = (status) => {
    const base = pendingParamsRef.current ?? filtersRef.current;
    pendingParamsRef.current = null;
    updateTable({ ...base, status: status || undefined, page: undefined });
  };

  const filterGroups = useMemo(
    () => [
      {
        key: 'status',
        label: 'Status',
        options: [
          { value: 'OPEN', label: 'Open' },
          { value: 'CLOSED', label: 'Closed' },
          { value: 'ARCHIVED', label: 'Archived' },
        ],
      },
      {
        key: 'client_type',
        label: 'Client Type',
        options: [
          { value: 'OFW', label: 'OFW' },
          { value: 'NEXT_OF_KIN', label: 'Next of Kin' },
        ],
      },
      {
        key: 'vulnerability_indicator',
        label: 'Vulnerability',
        options: [
          { value: 'PWD', label: 'PWD' },
          { value: 'Senior Citizen', label: 'Senior Citizen' },
          { value: 'Solo Parent', label: 'Solo Parent' },
          { value: 'Indigenous Person', label: 'Indigenous Person' },
          { value: 'None', label: 'None' },
        ],
      },
      {
        key: 'category_ids',
        label: 'Category',
        options: (categories ?? []).map((category) => ({ value: String(category.id), label: category.name })),
      },
      {
        key: 'case_issue_id',
        label: 'Issue / Concern',
        options: (caseIssues ?? []).map((issue) => ({ value: issue.id, label: issue.name })),
      },
      {
        key: 'user_id',
        label: 'Author',
        options: (users ?? []).map((user) => ({ value: user.id, label: user.name })),
      },
      {
        key: 'agcy_id',
        label: 'Referred To',
        options: (agencies ?? []).map((agency) => ({ value: agency.id, label: agency.name })),
      },
    ],
    [users, agencies, categories, caseIssues],
  );

  const selectedFilters = useMemo(
    () => ({
      status: toFilterList(filters?.status),
      client_type: toFilterList(filters?.client_type),
      vulnerability_indicator: toFilterList(filters?.vulnerability_indicator),
      category_ids: getCategoryFilterIds(filters),
      case_issue_id: toFilterList(filters?.case_issue_id),
      user_id: toFilterList(filters?.user_id),
      agcy_id: toFilterList(filters?.agcy_id),
    }),
    [filters],
  );

  const { toggleFilter } = useArrayFilters();

  const handleFilterToggle = (key, value) => {
    if (key === 'category_ids') {
      const next = toggleFilterValue(getCategoryFilterIds(filters), value);
      updateTable({ ...filters, category_ids: next.length ? next : undefined, category_id: undefined, page: undefined });
      return;
    }

    updateTable(toggleFilter(filters, key, value));
  };

  // Every list filter is a checkbox group (Jira-style). Case Age stays a single
  // select: `age_min_days` is validated as a scalar integer server-side, so an
  // array there would be rejected.
  const advancedFilterContent = useMemo(() => (
    <div className="flex flex-col gap-3">
      <FilterPanel
        searchable
        groups={filterGroups}
        selected={selectedFilters}
        onToggle={handleFilterToggle}
        onClearAll={handleClearFilters}
        activeCount={activeFilters.length}
      />

      <div className="flex flex-col gap-1 border-t border-slate-200 pt-2.5">
        <label className="flex flex-col gap-1">
          <span className="text-[11px] text-slate-500">Case age</span>
          <select
            value={filters?.age_min_days ?? ''}
            onChange={(e) => {
              const val = e.target.value;
              updateTable({ ...filters, age_min_days: val || undefined, page: undefined });
            }}
            className="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-[12.5px] text-slate-800 outline-none transition-colors focus:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
          >
            <option value="">Any age</option>
            <option value="7">7+ days</option>
            <option value="14">14+ days</option>
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
          </select>
        </label>
      </div>

      <div className="border-t border-slate-200 pt-2.5">
        <span className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[0.12em] text-slate-500">Date range</span>
        <div className="flex flex-col gap-2">
          <label className="flex flex-col gap-1">
            <span className="text-[11px] text-slate-500">From</span>
            <input
              type="date"
              value={filters?.date_from ?? ''}
              onChange={(e) => {
                const val = e.target.value;
                updateTable({ ...filters, date_from: val || undefined, page: undefined });
              }}
              className="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-[12.5px] text-slate-800 outline-none transition-colors focus:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-[11px] text-slate-500">To</span>
            <input
              type="date"
              value={filters?.date_to ?? ''}
              onChange={(e) => {
                const val = e.target.value;
                updateTable({ ...filters, date_to: val || undefined, page: undefined });
              }}
              className="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-[12.5px] text-slate-800 outline-none transition-colors focus:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
            />
          </label>
        </div>
      </div>

      <div className="border-t border-slate-200 pt-2.5">
        <button
          type="button"
          onClick={() => setFilterOpen(false)}
          className="h-9 w-full rounded-md bg-primary text-[12.5px] font-semibold text-white transition-colors hover:bg-primary-container focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
          Done
        </button>
      </div>
    </div>
  ), [filters, filterGroups, selectedFilters, activeFilters.length, updateTable]);

  const quickFilterPills = useMemo(() => {
    const statuses = [
      { key: 'all', label: 'All', value: '', count: stats?.total_cases },
      { key: 'open', label: 'Open', value: 'OPEN', count: stats?.open_cases },
      { key: 'closed', label: 'Closed', value: 'CLOSED', count: stats?.closed_cases },
      { key: 'archived', label: 'Archived', value: 'ARCHIVED', count: stats?.archived_cases },
    ];

    return (
      <TableTabs
        label="Filter cases by status"
        items={statuses}
        value={filters?.status ?? ''}
        allValue=""
        onSelect={(value) => handleStatusQuickFilter(value || undefined)}
      />
    );
  }, [filters, stats]);

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
        <div data-tour="cases-export">
          <ExportExcelButton onClick={handleExport} />
        </div>
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
        emptyStateMessage="No cases found"
        sortKey={filters?.sort ?? 'created_at'}
        sortDirection={filters?.direction ?? 'desc'}
        defaultSortKey="created_at"
        defaultSortDirection="desc"
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
        onClearFilters={handleClearFilters}
        quickFilters={quickFilterPills}
      />
      </div>

      {contextMenu && (
        <RowContextMenu
          x={contextMenu.x}
          y={contextMenu.y}
          anchorRect={contextMenu.anchorRect}
          triggerRef={actionTriggerRef}
          onClose={() => setContextMenu(null)}
        >
          <RowContextMenuItem icon="visibility" label="View" onClick={() => {
            router.visit(route('cases.show', contextMenu.row.id));
            setContextMenu(null);
          }} />
          <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
            router.visit(`${route('cases.show', contextMenu.row.id)}?edit=1`);
            setContextMenu(null);
          }} />
          {canArchiveCase(contextMenu.row) && (
            <RowContextMenuItem icon="archive" label="Archive" onClick={() => {
              setConfirmArchiveId(contextMenu.row.id);
              setContextMenu(null);
            }} />
          )}
        </RowContextMenu>
      )}

      <ExportDialog
        open={exportDialogOpen}
        onClose={() => setExportDialogOpen(false)}
        title="Export Cases"
        activeFilters={activeFilterChips}
        countUrlBuilder={countUrlBuilder}
        onExport={handleExportConfirm}
      />
      <ConfirmDialog
        open={!!confirmArchiveId}
        title="Archive Case"
        message="Are you sure you want to archive this case? It will be hidden from the main list but can be unarchived later."
        confirmLabel="Archive"
        cancelLabel="Cancel"
        tone="danger"
        onConfirm={() => {
          router.post(route('cases.archive', confirmArchiveId), {}, { preserveScroll: true });
          setConfirmArchiveId(null);
        }}
        onCancel={() => setConfirmArchiveId(null)}
      />
    </AppLayout>
  );
}
