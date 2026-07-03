import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import { useToast } from '@/Hooks/useToast';
import { formatResolvedAddress } from '@/lib/addressResolver';

const COLUMN_DEFS = [
    { key: 'referral_id', label: 'Referral #', default: true },
    { key: 'case_number', label: 'Case #', default: true },
    { key: 'client', label: 'Client', default: true },
    { key: 'client_contact', label: 'Client Contact', default: true },
    { key: 'case_summary', label: 'Case Summary', default: true },
    { key: 'case_issue', label: 'Issue / Concern', default: false },
    { key: 'agency', label: 'Agency', default: true },
    { key: 'required_services', label: 'Service', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'id', label: 'Actions', default: true },
];

function formatClientName(client) {
    if (!client) return 'N/A';
    return [client.first_name, client.middle_name, client.last_name, client.suffix].filter(Boolean).join(' ') || 'N/A';
}

function formatAddress(address) {
    return formatResolvedAddress(address, null);
}

export default function ReferralIndex({ referrals, filters }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';

    const [searchValue, setSearchValue] = useState(filters?.search ?? '');
    const [viewMode, setViewMode] = useState('list');
    const [filterOpen, setFilterOpen] = useState(false);
    const [columnsOpen, setColumnsOpen] = useState(false);

    const [pendingDecision, setPendingDecision] = useState(null);
    const [decisionRemark, setDecisionRemark] = useState('');

    const searchTimeout = useRef(null);

    const [visibleColumns, setVisibleColumns] = useState(
        COLUMN_DEFS.filter((c) => c.default).map((c) => c.key),
    );

    const [statusFilter, setStatusFilter] = useState(filters?.status ?? '');

    const toast = useToast();
    const [isExporting, setIsExporting] = useState(false);

    const handleExport = useCallback(() => {
        const params = new URLSearchParams();
        if (filters.status) params.set('status', filters.status);
        if (filters.search) params.set('search', filters.search);

        const qs = params.toString();
        const url = route('referrals.export-excel') + (qs ? '?' + qs : '');

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
        return chips;
    }, [statusFilter]);

    const handleRemoveFilter = (filter) => {
        if (filter.key === 'status') { setStatusFilter(''); navigateWith({ status: undefined }); }
    };

    const handleClearFilters = () => {
        setStatusFilter('');
        navigateWith({ status: undefined });
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
                router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['referrals'] });
            },
            onRowsPerPageChange: (n) => {
                const url = new URL(window.location);
                url.searchParams.set('per_page', n);
                url.searchParams.delete('page');
                router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['referrals'] });
            },
        };
    }

    const columns = useMemo(() =>
        COLUMN_DEFS
            .filter((col) => visibleColumns.includes(col.key))
            .map((col) => {
                const base = { key: col.key, title: col.label, sortable: true };
                switch (col.key) {
                    case 'referral_id':
                        return {
                            ...base,
                            sortable: false,
                            render: (row) => (
                                <span className="font-mono text-xs font-bold text-slate-700">{row.id.slice(0, 8)}</span>
                            ),
                        };
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
                    value={statusFilter}
                    onChange={(e) => {
                        const val = e.target.value;
                        setStatusFilter(val);
                        setFilterOpen(false);
                        navigateWith({ status: val || undefined });
                    }}
                    className="w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                    <option value="">All Statuses</option>
                    <option value="PENDING">Pending</option>
                    <option value="PROCESSING">Processing</option>
                    <option value="FOR_COMPLIANCE">For Compliance</option>
                    <option value="COMPLETED">Completed</option>
                    <option value="REJECTED">Rejected</option>
                </select>
            </div>

        </div>
    ), [statusFilter]);

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
                        <h1 className="text-2xl font-bold text-slate-900">
                            {isAgency ? 'My Agency Referrals' : 'Referral Management'}
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">Track and manage all referrals across agencies.</p>
                    </div>
                    <button
                        type="button"
                        onClick={handleExport}
                        disabled={isExporting}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span className="material-symbols-outlined text-[18px]">{isExporting ? 'sync' : 'download'}</span>
                        {isExporting ? 'Exporting…' : 'Export Excel'}
                    </button>
                </div>
            </div>

            <div data-tour="referrals-table">
            <UnifiedTable
                columns={columns}
                data={referrals.data}
                keyExtractor={(row) => row.id}
                {...paginatorProps(referrals)}
                searchValue={searchValue}
                searchPlaceholder="Search by referral ID, client, agency, or service..."
                onSearchChange={handleSearchChange}
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
                onRemoveFilter={handleRemoveFilter}
                onClearFilters={handleClearFilters}
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
        </AppLayout>
    );
}
