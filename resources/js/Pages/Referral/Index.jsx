import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import StatusBadge from '@/Components/ui/StatusBadge';
import { useToast } from '@/Hooks/useToast';
import { formatResolvedAddress } from '@/lib/addressResolver';
import { ArrowRightLeft, Clock, Loader, ClipboardCheck, CheckCircle2, XCircle } from 'lucide-react';
import ExportDialog from '@/Components/ExportDialog';

const COLUMN_DEFS = [
    { key: 'case_number', label: 'Case #', default: true },
    { key: 'client', label: 'Client', default: true },
    { key: 'client_contact', label: 'Client Contact', default: false },
    { key: 'case_summary', label: 'Case Summary', default: true },
    { key: 'case_issue', label: 'Issue / Concern', default: false },
    { key: 'agency', label: 'Agency', default: true },
    { key: 'required_services', label: 'Service', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'latest_update', label: 'Latest Update', default: true },
    { key: 'id', label: 'Actions', default: true },
];

function formatClientName(client) {
    if (!client) return 'N/A';
    return [client.first_name, client.middle_initial, client.last_name, client.suffix].filter(Boolean).join(' ') || 'N/A';
}

function formatAddress(address) {
    return formatResolvedAddress(address, null);
}

export default function ReferralIndex({ referrals, filters: rawFilters, stats, agencies = [], categories = [], caseIssues = [], exportRowCount = null }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';
    const filters = rawFilters && !Array.isArray(rawFilters) ? rawFilters : {};

    const [searchValue, setSearchValue] = useState(filters?.search ?? '');
    const [viewMode, setViewMode] = useState('list');
    const [filterOpen, setFilterOpen] = useState(false);
    const [columnsOpen, setColumnsOpen] = useState(false);
    const [contextMenu, setContextMenu] = useState(null);

    const searchTimeout = useRef(null);

    const [visibleColumns, setVisibleColumns] = useState(
        COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
    );

    const [pendingDecision, setPendingDecision] = useState(null);
    const [decisionRemark, setDecisionRemark] = useState('');

    const toast = useToast();
    const [isExporting, setIsExporting] = useState(false);
    const [exportDialogOpen, setExportDialogOpen] = useState(false);
    const [tableLoading, setTableLoading] = useState(false);

    const handleExport = useCallback(() => {
        setExportDialogOpen(true);
    }, []);

    const handleExportConfirm = useCallback(({ dateFrom, dateTo }) => {
        const params = new URLSearchParams();
        if (filters.status) params.set('status', filters.status);
        if (filters.search) params.set('search', filters.search);
        if (filters.agcy_id) params.set('agcy_id', filters.agcy_id);
        if (filters.category_id) params.set('category_id', filters.category_id);
        if (filters.case_issue_id) params.set('case_issue_id', filters.case_issue_id);
        if (filters.age_min_days) params.set('age_min_days', filters.age_min_days);
        if (filters.age_max_days) params.set('age_max_days', filters.age_max_days);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);

        const qs = params.toString();
        const url = route('referrals.export-excel') + (qs ? '?' + qs : '');

        setIsExporting(true);
        setExportDialogOpen(false);
        toast.info('Preparing your export…');

        const link = document.createElement('a');
        link.href = url;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        setTimeout(() => setIsExporting(false), 5000);
    }, [filters, toast]);

    const activeFilterChips = useMemo(() => {
        const chips = [];
        if (filters?.status) chips.push({ label: 'Status', value: filters.status });
        if (filters?.agcy_id) chips.push({ label: 'Agency', value: filters.agcy_id });
        if (filters?.category_id) chips.push({ label: 'Category', value: filters.category_id });
        if (filters?.case_issue_id) chips.push({ label: 'Issue', value: filters.case_issue_id });
        if (filters?.age_min_days) chips.push({ label: 'Older Than', value: `${filters.age_min_days}+ days` });
        if (filters?.age_max_days) chips.push({ label: 'Received Within', value: `Last ${filters.age_max_days} days` });
        if (filters?.date_from) chips.push({ label: 'From', value: filters.date_from });
        if (filters?.date_to) chips.push({ label: 'To', value: filters.date_to });
        return chips;
    }, [filters]);

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

    const updateTable = (params) => {
        const clean = Object.fromEntries(
            Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
        );
        router.get(route('referrals.index'), clean, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['referrals', 'filters', 'stats', 'agencies', 'categories', 'caseIssues'],
            showProgress: false,
        });
    };

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
        if (filters?.status) chips.push({ key: 'status', label: 'Status', value: filters.status });
        if (filters?.agcy_id) {
            const agency = agencies.find(a => a.id === filters.agcy_id);
            chips.push({ key: 'agcy_id', label: 'Agency', value: agency?.name || filters.agcy_id });
        }
        if (filters?.category_id) {
            const cat = categories.find(c => c.id === filters.category_id);
            chips.push({ key: 'category_id', label: 'Category', value: cat?.name || filters.category_id });
        }
        if (filters?.case_issue_id) {
            const issue = caseIssues.find(c => c.id === filters.case_issue_id);
            chips.push({ key: 'case_issue_id', label: 'Issue/Concern', value: issue?.name || filters.case_issue_id });
        }
        if (filters?.age_min_days) chips.push({ key: 'age_min_days', label: 'Age', value: `${filters.age_min_days}+ days` });
        if (filters?.age_max_days) chips.push({ key: 'age_max_days', label: 'Received', value: `within ${filters.age_max_days} days` });
        return chips;
    }, [filters, agencies, categories, caseIssues]);

    const handleRemoveFilter = (filter) => {
        updateTable({ ...filters, [filter.key]: undefined, page: undefined });
    };

    const handleClearFilters = () => {
        setSearchValue('');
        clearTimeout(searchTimeout.current);
        updateTable({ status: undefined, search: undefined, agcy_id: undefined, category_id: undefined, case_issue_id: undefined, age_min_days: undefined, age_max_days: undefined, page: undefined });
    };

    const handleStatusQuickFilter = (status) => {
        updateTable({ ...filters, status: status || undefined, page: undefined });
    };

    const quickFilterPills = useMemo(() => {
        const statuses = [
            { label: 'All', value: '' },
            { label: 'Pending', value: 'PENDING', count: stats?.pending },
            { label: 'Processing', value: 'PROCESSING', count: stats?.processing },
            { label: 'For Compliance', value: 'FOR_COMPLIANCE', count: stats?.for_compliance },
            { label: 'Completed', value: 'COMPLETED', count: stats?.completed },
            { label: 'Rejected', value: 'REJECTED', count: stats?.rejected },
        ];
        const currentStatus = filters?.status ?? '';

        return (
            <div className="flex items-center gap-1.5 flex-wrap" role="group" aria-label="Quick status filters">
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
                            {s.count > 0 && ` (${s.count})`}
                        </button>
                    );
                })}
            </div>
        );
    }, [filters?.status, stats]);

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
                            render: (row) => formatClientName(row.case_file?.client),
                            sortAccessor: (row) => row.case_file?.client
                                ? `${row.case_file.client.last_name}, ${row.case_file.client.first_name}`
                                : '',
                        };
                    case 'client_contact':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => {
                                const client = row.case_file?.client;
                                const address = client?.addresses?.[0] ?? null;
                                return (
                                    <div className="max-w-xs space-y-0.5 text-[11px] text-slate-600">
                                        <div>{client?.contact_number ?? 'No contact number'}</div>
                                        {client?.email && <div className="truncate">{client.email}</div>}
                                        {formatAddress(address) && <div className="truncate text-slate-400">{formatAddress(address)}</div>}
                                    </div>
                                );
                            },
                        };
                    case 'case_summary':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => (
                                <span className="line-clamp-2 block max-w-sm text-[11px] leading-5 text-slate-600">
                                    {row.case_file?.summary ?? 'N/A'}
                                </span>
                            ),
                        };
                    case 'case_issue':
                        return {
                            ...base,
                            render: (row) => row.case_file?.case_issue?.name ?? row.case_file?.case_issue?.title ?? 'N/A',
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
                    <option value="PENDING">Pending</option>
                    <option value="PROCESSING">Processing</option>
                    <option value="FOR_COMPLIANCE">For Compliance</option>
                    <option value="COMPLETED">Completed</option>
                    <option value="REJECTED">Rejected</option>
                </select>
            </div>
            <div>
                <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Agency</label>
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
                    {categories.map((cat) => (
                        <option key={cat.id} value={cat.id}>{cat.name}</option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Issue / Concern</label>
                <select
                    value={filters?.case_issue_id ?? ''}
                    onChange={(e) => {
                        const val = e.target.value;
                        updateTable({ ...filters, case_issue_id: val || undefined, page: undefined });
                    }}
                    className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                    <option value="">All Issues</option>
                    {caseIssues.map((issue) => (
                        <option key={issue.id} value={issue.id}>{issue.name}</option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Received Within</label>
                <select
                    value={filters?.age_max_days ?? ''}
                    onChange={(e) => {
                        const val = e.target.value;
                        updateTable({ ...filters, age_max_days: val || undefined, page: undefined });
                    }}
                    className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                    <option value="">Any time</option>
                    <option value="1">Last 24 hours</option>
                    <option value="2">Last 2 days</option>
                    <option value="3">Last 3 days</option>
                    <option value="7">Last 7 days</option>
                    <option value="14">Last 14 days</option>
                    <option value="30">Last 30 days</option>
                </select>
            </div>
            <div>
                <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Older Than</label>
                <select
                    value={filters?.age_min_days ?? ''}
                    onChange={(e) => {
                        const val = e.target.value;
                        updateTable({ ...filters, age_min_days: val || undefined, page: undefined });
                    }}
                    className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                    <option value="">Any age</option>
                    <option value="3">3+ days</option>
                    <option value="7">7+ days</option>
                    <option value="14">14+ days</option>
                    <option value="30">30+ days</option>
                    <option value="60">60+ days</option>
                </select>
            </div>
            <div className="border-t border-slate-200 pt-3 mt-3">
                <label className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">Date Range</label>
                <div className="flex gap-2">
                    <div className="flex-1">
                        <label className="block text-[10px] text-slate-400 mb-1">From</label>
                        <input
                            type="date"
                            value={filters?.date_from ?? ''}
                            onChange={(e) => {
                                const val = e.target.value;
                                updateTable({ ...filters, date_from: val || undefined, page: undefined });
                            }}
                            className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                        />
                    </div>
                    <div className="flex-1">
                        <label className="block text-[10px] text-slate-400 mb-1">To</label>
                        <input
                            type="date"
                            value={filters?.date_to ?? ''}
                            onChange={(e) => {
                                const val = e.target.value;
                                updateTable({ ...filters, date_to: val || undefined, page: undefined });
                            }}
                            className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                        />
                    </div>
                </div>
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
    ), [filters, agencies, categories, caseIssues, updateTable]);

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
        if (!pendingDecision) return;
        const trimmed = decisionRemark.trim();
        if (!trimmed) return;
        const nextStatus = pendingDecision.action === 'ACCEPT' ? 'PROCESSING' : 'REJECTED';
        router.patch(route('referrals.update-status', pendingDecision.id), {
            status: nextStatus,
            decision: pendingDecision.action,
            decision_comment: trimmed,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDecision(null);
                setDecisionRemark('');
            },
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
                    <button
                        data-tour="referrals-export"
                        type="button"
                        onClick={handleExport}
                        disabled={isExporting}
                        className="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-emerald-700 bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span className="material-symbols-outlined text-[18px]">{isExporting ? 'sync' : 'download'}</span>
                        {isExporting ? 'Exporting…' : 'Export Excel'}
                    </button>
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
                isLoading={tableLoading}
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
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h2 className="text-base font-bold text-slate-900">
                                {pendingDecision.action === 'ACCEPT' ? 'Accept' : 'Reject'} Referral
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                A remark is required before you can continue.
                            </p>
                        </div>
                        <div className="px-5 py-4">
                            <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">Remark</label>
                            <textarea
                                value={decisionRemark}
                                onChange={(e) => setDecisionRemark(e.target.value)}
                                rows={4}
                                placeholder="Enter your decision remark..."
                                className="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                            />
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                            <button onClick={() => { setPendingDecision(null); setDecisionRemark(''); }}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button onClick={submitDecision} disabled={!decisionRemark.trim()}
                                className="h-9 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                Confirm {pendingDecision.action === 'ACCEPT' ? 'Accept' : 'Reject'}
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
                rowCount={exportRowCount}
                onExport={handleExportConfirm}
            />
        </AppLayout>
    );
}
