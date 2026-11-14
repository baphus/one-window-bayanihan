import Select from '@/Components/Select';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import TableTabs from '@/Components/ui/TableTabs';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatResolvedAddress } from '@/lib/addressResolver';
import { ArrowRightLeft, Clock, Loader, ClipboardCheck, CheckCircle2, XCircle } from 'lucide-react';
import KpiCard from '@/Components/ui/KpiCard';
import ExportDialog from '@/Components/ExportDialog';
import { downloadExport } from '@/lib/downloadExport';
import { useToast } from '@/Hooks/useToast';
import ExportExcelButton from '@/Components/ExportExcelButton';
import InputError from '@/Components/InputError';
import usePersistedColumns from '@/Hooks/usePersistedColumns';
import { usePersistedViewMode, usePersistedFilters } from '@/Hooks/usePersistedViewState';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import FilterPanel from '@/Components/ui/FilterPanel';
import { toFilterList, useArrayFilters } from '@/Hooks/useArrayFilters';
import { humanizeStatus } from '@/lib/statusLabels';

const COLUMN_DEFS = [
    { key: 'case_number', label: 'Case #', default: true },
    { key: 'client', label: 'Client', default: true },
    { key: 'client_contact', label: 'Client Contact', default: false },
    { key: 'case_summary', label: 'Case Summary', default: true },
    { key: 'category', label: 'Category', default: false },
    { key: 'agency', label: 'Agency', default: true },
    { key: 'required_services', label: 'Service', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'latest_update', label: 'Latest Update', default: true },
    { key: 'id', label: 'Actions', default: true },
];

function caseIdentityOf(row) {
    return row?.case_identity ?? row?.caseIdentity ?? null;
}

function formatClientName(identity) {
    if (!identity) return 'N/A';
    const full = [identity.first_name, identity.middle_name, identity.last_name, identity.suffix].filter(Boolean).join(' ');
    return full || identity.name || 'N/A';
}

function formatAddress(address) {
    return formatResolvedAddress(address, null);
}

const REJECTION_REASONS = [
    { value: 'INCOMPLETE_REQUIREMENTS', label: 'Incomplete requirements' },
    { value: 'OUTSIDE_MANDATE', label: 'Outside agency mandate' },
    { value: 'DUPLICATE_REFERRAL', label: 'Duplicate referral' },
    { value: 'CLIENT_WITHDREW', label: 'Client withdrew' },
    { value: 'NO_SERVICE_CAPACITY', label: 'No service capacity' },
    { value: 'OTHER', label: 'Other' },
];

export default function ReferralIndex({ referrals, filters: rawFilters, stats, agencies = [], categories = [], statuses = [] }) {
    const { auth, roles, errors } = usePage().props;
    const toast = useToast();
    const isAgency = auth.user.role === roles.AGENCY;
    const canCreate = auth.user.role === roles.CASE_MANAGER || auth.user.role === roles.ADMIN;
    const filters = rawFilters && !Array.isArray(rawFilters) ? rawFilters : {};

    const [searchValue, setSearchValue] = useState(filters?.search ?? '');
    const [viewMode, setViewMode] = usePersistedViewMode('owb-view_referrals');
    const [filterOpen, setFilterOpen] = useState(false);
    const [columnsOpen, setColumnsOpen] = useState(false);
    const [contextMenu, setContextMenu] = useState(null);

    const searchTimeout = useRef(null);

    const [visibleColumns, setVisibleColumns] = usePersistedColumns(
      'referrals',
      COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
    );

    const [pendingDecision, setPendingDecision] = useState(null);
    const [decisionRemark, setDecisionRemark] = useState('');
    const [decisionReason, setDecisionReason] = useState('');
    const [decisionSubmitting, setDecisionSubmitting] = useState(false);

    const [exportDialogOpen, setExportDialogOpen] = useState(false);
    const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

    const handleExport = useCallback(() => {
        setExportDialogOpen(true);
    }, []);

    // Single query builder shared by the export download and the live row-count
    // preview, so the count shown always matches the export that will run.
    const buildExportQuery = useCallback(({ dateFrom, dateTo }) => {
        const params = new URLSearchParams();
        const appendList = (key, value) => {
            toFilterList(value).forEach((item) => params.append(`${key}[]`, item));
        };
        appendList('status', filters.status);
        if (filters.search) params.set('search', filters.search);
        appendList('agcy_id', filters.agcy_id);
        if (toFilterList(filters.category_ids).length) appendList('category_ids', filters.category_ids);
        else appendList('category_id', filters.category_id);
        if (filters.case_id) params.set('case_id', filters.case_id);
        if (filters.age_min_days) params.set('age_min_days', filters.age_min_days);
        if (filters.age_max_days) params.set('age_max_days', filters.age_max_days);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        return params.toString();
    }, [filters]);

    const handleExportConfirm = useCallback(({ dateFrom, dateTo }) => {
        const qs = buildExportQuery({ dateFrom, dateTo });
        const url = route('referrals.export-excel') + (qs ? '?' + qs : '');

        setExportDialogOpen(false);
        downloadExport(url).catch((error) => toast.error(error.message));
    }, [buildExportQuery, toast]);

    const countUrlBuilder = useCallback((dateFrom, dateTo) => {
        const qs = buildExportQuery({ dateFrom, dateTo });
        return route('referrals.export-count') + (qs ? '?' + qs : '');
    }, [buildExportQuery]);

    const activeFilterChips = useMemo(() => {
        const chips = [];
        if (filters?.status) chips.push({ label: 'Status', value: filters.status });
        if (filters?.agcy_id) chips.push({ label: 'Agency', value: filters.agcy_id });
        if (filters?.category_id) chips.push({ label: 'Category', value: filters.category_id });
        if (filters?.age_min_days) chips.push({ label: 'Older Than', value: `${filters.age_min_days}+ days` });
        if (filters?.age_max_days) chips.push({ label: 'Received Within', value: `Last ${filters.age_max_days} days` });
        if (filters?.date_from) chips.push({ label: 'From', value: filters.date_from });
        if (filters?.date_to) chips.push({ label: 'To', value: filters.date_to });
        return chips;
    }, [filters]);

    useEffect(() => {
        return () => clearTimeout(searchTimeout.current);
    }, []);

    // Latest filter state for the debounced search. Server-driven filters only
    // change when a response arrives, but a filter clicked while a search is
    // still pending has not round-tripped yet, so params sent while a search
    // timer is live are remembered and the timer merges into them. When no
    // search is pending the props are authoritative and nothing needs tracking —
    // tracking them unconditionally would let a mount-time filter restore
    // shadow the props.
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
        router.get(route('referrals.index'), clean, withLoading({
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['referrals', 'filters', 'stats', 'agencies', 'categories'],
        }));
    };

    const restoreFilters = (saved) => {
        setSearchValue(saved.search ?? '');
        updateTable(saved);
    };
    usePersistedFilters('owb-filters_referrals', filters, restoreFilters);

    const handleSearchChange = (value) => {
        setSearchValue(value);
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {
            const base = pendingParamsRef.current ?? filtersRef.current;
            pendingParamsRef.current = null;
            updateTable({ ...base, search: value || undefined, page: undefined });
            // Cleared AFTER updateTable: updateTable -> trackPending must not
            // record this very request as "pending". Anything issued later (a
            // filter click while the response is in flight) wins on its own.
            searchTimeout.current = null;
        }, 400);
    };

    const handleSearchClear = () => {
        setSearchValue('');
        clearTimeout(searchTimeout.current);
        searchTimeout.current = null;
        pendingParamsRef.current = null;
        updateTable({ ...filtersRef.current, search: undefined, page: undefined });
    };

    const agencyLabel = useCallback((id) => agencies.find((a) => a.id === id)?.name ?? id, [agencies]);
    const categoryLabel = useCallback(
        (id) => categories?.find((c) => String(c.id) === String(id))?.name ?? id,
        [categories],
    );
    // One chip per selected value, matching the multi-select checkbox panel.
    const activeFilters = useMemo(() => {
        const chips = [];
        const push = (key, label, value, display) => chips.push({ key, label, value: display ?? value });

        toFilterList(filters?.status).forEach((value) => push('status', 'Status', value, humanizeStatus(value)));
        toFilterList(filters?.agcy_id).forEach((value) => push('agcy_id', 'Agency', value, agencyLabel(value)));
        toFilterList(filters?.category_id).forEach((value) => push('category_id', 'Category', value, categoryLabel(value)));
        if (filters?.age_min_days) push('age_min_days', 'Age', filters.age_min_days, `${filters.age_min_days}+ days`);
        if (filters?.age_max_days) push('age_max_days', 'Received', filters.age_max_days, `within ${filters.age_max_days} days`);

        return chips;
    }, [filters, agencyLabel, categoryLabel]);

    const handleRemoveFilter = (filter) => {
        // The day-window filters are single-valued selects.
        if (filter.key === 'age_min_days' || filter.key === 'age_max_days') {
            updateTable({ ...filters, [filter.key]: undefined, page: undefined });
            return;
        }

        const remaining = toFilterList(filters?.[filter.key]).filter((value) => value !== filter.value);
        const encoded = remaining.length === 0 ? undefined : remaining.length === 1 ? remaining[0] : remaining;

        updateTable({ ...filters, [filter.key]: encoded, page: undefined });
    };

    const handleClearFilters = () => {
        setSearchValue('');
        clearTimeout(searchTimeout.current);
        updateTable({ status: undefined, search: undefined, agcy_id: undefined, category_id: undefined, age_min_days: undefined, age_max_days: undefined, page: undefined });
    };

    const handleSortChange = (sortKey, sortDirection) => {
        updateTable({ ...filters, sort: sortKey, direction: sortDirection, page: undefined });
    };

    const handleStatusQuickFilter = (status) => {
        updateTable({ ...filters, status: status || undefined, page: undefined });
    };

    const quickFilterPills = useMemo(() => {
        const statuses = [
            { key: 'all', label: 'All', value: '' },
            { key: 'pending', label: 'Pending', value: 'PENDING', count: stats?.pending },
            { key: 'processing', label: 'Processing', value: 'PROCESSING', count: stats?.processing },
            { key: 'for_compliance', label: 'For Compliance', value: 'FOR_COMPLIANCE', count: stats?.for_compliance },
            { key: 'completed', label: 'Completed', value: 'COMPLETED', count: stats?.completed },
            { key: 'rejected', label: 'Rejected', value: 'REJECTED', count: stats?.rejected },
        ];

        return (
            <TableTabs
                label="Filter referrals by status"
                items={statuses}
                value={filters?.status ?? ''}
                allValue=""
                onSelect={(value) => handleStatusQuickFilter(value || undefined)}
            />
        );
    }, [filters?.status, stats, handleStatusQuickFilter]);

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

    function handleRowContextMenu(e, row) {
        e.preventDefault();
        setContextMenu({ x: e.clientX, y: e.clientY, row });
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
                            render: (row) => row.case_file?.case_number ?? 'N/A',
                        };
                    case 'client':
                        return {
                            ...base,
                            render: (row) => formatClientName(caseIdentityOf(row)),
                            sortAccessor: (row) => {
                                const identity = caseIdentityOf(row);
                                return identity?.last_name ? `${identity.last_name}, ${identity.first_name}` : '';
                            },
                        };
                    case 'client_contact':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => {
                                const identity = caseIdentityOf(row);
                                const address = identity?.address ?? null;
                                return (
                                    <div className="max-w-xs space-y-0.5 text-[11px] text-slate-600">
                                        <div>{identity?.contact_number ?? 'No contact number'}</div>
                                        {identity?.email && <div className="truncate">{identity.email}</div>}
                                        {formatAddress(address) && <div className="truncate text-slate-400">{formatAddress(address)}</div>}
                                    </div>
                                );
                            },
                        };
                    case 'case_summary':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => {
                                const summary = row.case_file?.summary ?? 'N/A';
                                const preview = Array.from(summary).length > 120
                                    ? `${Array.from(summary).slice(0, 119).join('')}…`
                                    : summary;
                                return (
                                    <span title={summary} className="line-clamp-2 block max-w-sm text-[11px] leading-5 text-slate-600">
                                        {preview}
                                    </span>
                                );
                            },
                        };
                    case 'category':
                        return {
                            ...base,
                            render: (row) => row.category?.name ?? row.category?.title ?? 'N/A',
                        };
                    case 'agency':
                        return {
                            ...base,
                            render: (row) => row.agency?.name ?? 'N/A',
                        };
                    case 'required_services':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => <span className="max-w-xs truncate block">{row.required_services}</span>,
                        };
                    case 'status':
                        return {
                            ...base,
                            render: (row) => (
                                <StatusBadge status={row.status} />
                            ),
                        };
                    case 'latest_update':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => {
                                const update = row.latest_update;
                                if (!update) return <span className="text-slate-400 text-[11px]">—</span>;
                                return (
                                    <div className="max-w-[200px]">
                                        <div className="text-[11px] font-medium text-slate-700 truncate">{update.description}</div>
                                        <div className="text-[10px] text-slate-400">{update.date}</div>
                                    </div>
                                );
                            },
                        };
                    case 'id':
                        return {
                            ...base,
                            sortable: false,
                            title: 'Actions',
                            render: (row) => (
                                <div className="flex items-center gap-1.5">
                                    <button onClick={() => router.visit(route('referrals.show', row.id))} className="min-h-[28px] px-2.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-md transition-colors border border-blue-900">
                                        View
                                    </button>
                                    {isAgency && row.status === 'PENDING' && (
                                        <>
                                            <button onClick={() => setPendingDecision({ id: row.id, action: 'ACCEPT' })} className="min-h-[28px] px-2.5 bg-emerald-600 text-white hover:bg-emerald-700 text-[11px] font-bold rounded-md transition-colors border border-emerald-600">
                                                Accept
                                            </button>
                                            <button onClick={() => setPendingDecision({ id: row.id, action: 'REJECT' })} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200">
                                                Reject
                                            </button>
                                        </>
                                    )}
                                </div>
                            ),
                        };
                    default:
                        return { ...base, render: (row) => row[col.key] };
                }
            }),
        [visibleColumns]);

    // Every list filter is a checkbox group; the day-window selects and the date
    // range stay as plain controls. Group definitions are data so the panel can
    // apply its own in-panel search across all of them.
    const filterGroups = useMemo(
        () => [
            { key: 'status', label: 'Status', options: statuses },
            {
                key: 'agcy_id',
                label: 'Agency',
                options: (agencies ?? []).map((agency) => ({ value: agency.id, label: agency.name })),
            },
            {
                key: 'category_id',
                label: 'Category',
                options: (categories ?? []).map((category) => ({ value: String(category.id), label: category.name })),
            },
        ],
        [statuses, agencies, categories],
    );

    const { toggleFilter } = useArrayFilters();

    const selectedFilters = useMemo(
        () => ({
            status: toFilterList(filters?.status),
            agcy_id: toFilterList(filters?.agcy_id),
            category_id: toFilterList(filters?.category_id),
        }),
        [filters],
    );

    const handleFilterToggle = (key, value) => {
        updateTable(toggleFilter(filters, key, value));
    };

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
            <div className="flex flex-col gap-2 border-t border-slate-200 pt-2.5">
                <label className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500">Received within</span>
                    <Select
                        value={filters?.age_max_days ?? ''}
                        onChange={(e) => {
                            const val = e.target.value;
                            updateTable({ ...filters, age_max_days: val || undefined, page: undefined });
                        }}
                        className="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-[12.5px] text-slate-800 outline-none transition-colors focus:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                    >
                        <option value="">Any time</option>
                        <option value="1">Last 24 hours</option>
                        <option value="2">Last 2 days</option>
                        <option value="3">Last 3 days</option>
                        <option value="7">Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option>
                    </Select>
                </label>
                <label className="flex flex-col gap-1">
                    <span className="text-[11px] text-slate-500">Older than</span>
                    <Select
                        value={filters?.age_min_days ?? ''}
                        onChange={(e) => {
                            const val = e.target.value;
                            updateTable({ ...filters, age_min_days: val || undefined, page: undefined });
                        }}
                        className="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-[12.5px] text-slate-800 outline-none transition-colors focus:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                    >
                        <option value="">Any age</option>
                        <option value="3">3+ days</option>
                        <option value="7">7+ days</option>
                        <option value="14">14+ days</option>
                        <option value="30">30+ days</option>
                        <option value="60">60+ days</option>
                    </Select>
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
    ), [filterGroups, selectedFilters, activeFilters.length, filters, handleClearFilters, handleFilterToggle, updateTable]);
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

    const submitDecision = () => {
        if (decisionSubmitting || !pendingDecision) return;
        const trimmed = decisionRemark.trim();
        if (!trimmed) return;
        const isReject = pendingDecision.action === 'REJECT';
        if (isReject && (!decisionReason || (decisionReason === 'OTHER' && trimmed.length < 10))) return;
        const nextStatus = pendingDecision.action === 'ACCEPT' ? 'PROCESSING' : 'REJECTED';
        setDecisionSubmitting(true);
        router.patch(route('referrals.update-status', pendingDecision.id), {
            status: nextStatus,
            decision: pendingDecision.action,
            decision_comment: trimmed,
            rejection_reason: isReject ? (decisionReason || undefined) : undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDecision(null);
                setDecisionRemark('');
                setDecisionReason('');
            },
            onFinish: () => setDecisionSubmitting(false),
        });
    };

    return (
        <AppLayout title={isAgency ? 'My Agency Referrals' : 'Referral Management'}>
            <Head title="Referrals" />

            <div data-tour="referrals-header" className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
                            {isAgency ? 'My Agency Referrals' : 'Referral Management'}
                        </h1>
                        <p className="text-sm text-slate-400 font-body mt-0.5">Track and manage all referrals across agencies.</p>
                    </div>
                    <ExportExcelButton onClick={handleExport} data-tour="referrals-export" />
                </div>
            </div>

            <section className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-start justify-between mb-2">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Referrals</p>
                        <span className="p-1.5 bg-blue-50 rounded-lg"><ArrowRightLeft className="w-4 h-4 text-blue-900" /></span>
                    </div>
                    <p className="text-2xl font-black text-slate-900">{stats?.total_referrals ?? 0}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">All referrals</p>
                </div>

                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-start justify-between mb-2">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</p>
                        <span className="p-1.5 bg-amber-50 rounded-lg"><Clock className="w-4 h-4 text-amber-600" /></span>
                    </div>
                    <p className="text-2xl font-black text-slate-900">{stats?.pending ?? 0}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">
                        {stats?.total_referrals > 0
                            ? `${((stats.pending / stats.total_referrals) * 100).toFixed(0)}% of total`
                            : 'Awaiting response'}
                    </p>
                </div>

                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-start justify-between mb-2">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Processing</p>
                        <span className="p-1.5 bg-sky-50 rounded-lg"><Loader className="w-4 h-4 text-sky-600" /></span>
                    </div>
                    <p className="text-2xl font-black text-slate-900">{stats?.processing ?? 0}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">
                        {stats?.total_referrals > 0
                            ? `${((stats.processing / stats.total_referrals) * 100).toFixed(0)}% of total`
                            : 'In progress'}
                    </p>
                </div>

                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-start justify-between mb-2">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">For Compliance</p>
                        <span className="p-1.5 bg-violet-50 rounded-lg"><ClipboardCheck className="w-4 h-4 text-violet-600" /></span>
                    </div>
                    <p className="text-2xl font-black text-slate-900">{stats?.for_compliance ?? 0}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">
                        {stats?.total_referrals > 0
                            ? `${((stats.for_compliance / stats.total_referrals) * 100).toFixed(0)}% of total`
                            : 'Awaiting compliance'}
                    </p>
                </div>

                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-start justify-between mb-2">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Completed</p>
                        <span className="p-1.5 bg-emerald-50 rounded-lg"><CheckCircle2 className="w-4 h-4 text-emerald-600" /></span>
                    </div>
                    <p className="text-2xl font-black text-slate-900">{stats?.completed ?? 0}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">
                        {stats?.total_referrals > 0
                            ? `${((stats.completed / stats.total_referrals) * 100).toFixed(0)}% of total`
                            : 'Successfully closed'}
                    </p>
                </div>

                <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div className="flex items-start justify-between mb-2">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Rejected</p>
                        <span className="p-1.5 bg-red-50 rounded-lg"><XCircle className="w-4 h-4 text-red-500" /></span>
                    </div>
                    <p className="text-2xl font-black text-slate-900">{stats?.rejected ?? 0}</p>
                    <p className="text-[10px] text-slate-400 mt-0.5">
                        {stats?.total_referrals > 0
                            ? `${((stats.rejected / stats.total_referrals) * 100).toFixed(0)}% of total`
                            : 'Declined by agency'}
                    </p>
                </div>
            </section>

            <div data-tour="referrals-table">
            <UnifiedTable
                columns={columns}
                data={referrals.data}
                keyExtractor={(row) => row.id}
                {...paginatorProps(referrals)}
                // The API defaults to created_at DESC. Keep that server-side
                // default controlled until the user chooses a visible column.
                sortKey={filters?.sort ?? 'created_at'}
                sortDirection={filters?.direction ?? 'desc'}
                defaultSortKey="created_at"
                defaultSortDirection="desc"
                onSortChange={handleSortChange}
                showSortReset={false}
                isLoading={tableLoading}
                emptyStateMessage="No referrals found"
                searchValue={searchValue}
                searchPlaceholder="Search by referral ID, client, agency, or service..."
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
                onNewRecord={canCreate ? () => router.visit(route('referrals.create')) : undefined}
                newRecordLabel="Create Referral"
                activeFilters={activeFilters}
                activeFilterCount={activeFilters.length}
                onRemoveFilter={handleRemoveFilter}
                onClearFilters={handleClearFilters}
                onRowContextMenu={handleRowContextMenu}
                quickFilters={quickFilterPills}
            />
            </div>

            {pendingDecision && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => { setPendingDecision(null); setDecisionRemark(''); setDecisionReason(''); }}>
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl owb-modal-animate" onClick={(e) => e.stopPropagation()}>
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h2 className="text-base font-bold text-slate-900">
                                {pendingDecision.action === 'ACCEPT' ? 'Accept' : 'Reject'} Referral
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                A remark is required before you can continue.
                            </p>
                        </div>
                        <div className="px-5 py-4 space-y-4">
                            {pendingDecision.action === 'REJECT' && (
                                <div>
                                    <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">Reason *</label>
                                    <Select
                                        value={decisionReason}
                                        onChange={(e) => setDecisionReason(e.target.value)}
                                        className="h-10 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                    >
                                        <option value="">Select a reason</option>
                                        {REJECTION_REASONS.map((reason) => (
                                            <option key={reason.value} value={reason.value}>{reason.label}</option>
                                        ))}
                                    </Select>
                                    <InputError message={errors?.rejection_reason} className="mt-1" />
                                </div>
                            )}
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">Remark</label>
                                <textarea
                                    value={decisionRemark}
                                    onChange={(e) => setDecisionRemark(e.target.value)}
                                    rows={4}
                                    placeholder="Enter your decision remark..."
                                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                                {pendingDecision.action === 'REJECT' && decisionReason === 'OTHER' && (
                                    <p className="mt-1 text-[11px] text-slate-500">Please explain in at least 10 characters.</p>
                                )}
                                <InputError message={errors?.decision_comment} className="mt-1" />
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                            <button onClick={() => { setPendingDecision(null); setDecisionRemark(''); setDecisionReason(''); }}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button onClick={submitDecision} disabled={!decisionRemark.trim() || decisionSubmitting || (pendingDecision.action === 'REJECT' && (!decisionReason || (decisionReason === 'OTHER' && decisionRemark.trim().length < 10)))}
                                className="h-9 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                {decisionSubmitting ? 'Saving…' : `Confirm ${pendingDecision.action === 'ACCEPT' ? 'Accept' : 'Reject'}`}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {contextMenu && (
                <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
                    <RowContextMenuItem icon="visibility" label="View" onClick={() => {
                        router.visit(route('referrals.show', contextMenu.row.id));
                        setContextMenu(null);
                    }} />
                </RowContextMenu>
            )}

            <ExportDialog
                open={exportDialogOpen}
                onClose={() => setExportDialogOpen(false)}
                title="Export Referrals"
                activeFilters={activeFilterChips}
                countUrlBuilder={countUrlBuilder}
                onExport={handleExportConfirm}
            />
        </AppLayout>
    );
}
