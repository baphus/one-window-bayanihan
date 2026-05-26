import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

const statusStyles = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    FOR_COMPLIANCE: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
};

const COLUMN_DEFS = [
    { key: 'case_number', label: 'Case #', default: true },
    { key: 'client', label: 'Client', default: true },
    { key: 'agency', label: 'Agency', default: true },
    { key: 'required_services', label: 'Service', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'id', label: 'Actions', default: true },
];

export default function ReferralIndex({ referrals, filters }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
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
                    case 'case_number':
                        return {
                            ...base,
                            render: (row) => row.case_file?.case_number ?? 'N/A',
                        };
                    case 'client':
                        return {
                            ...base,
                            render: (row) => row.case_file?.client
                                ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}`
                                : 'N/A',
                            sortAccessor: (row) => row.case_file?.client
                                ? `${row.case_file.client.last_name}, ${row.case_file.client.first_name}`
                                : '',
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
                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[row.status] || 'bg-slate-100 text-slate-800'}`}>
                                    {row.status}
                                </span>
                            ),
                        };
                    case 'id':
                        return {
                            ...base,
                            sortable: false,
                            title: 'Actions',
                            render: (row) => (
                                <Link href={route('referrals.show', row.id)} className="text-indigo-600 hover:text-indigo-900">
                                    View
                                </Link>
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
                        className="rounded border-[#cbd5e1] text-[#0b5384] focus:ring-[#0b5384] focus:ring-offset-0"
                    />
                    {col.label}
                </label>
            ))}
        </div>
    ), [visibleColumns]);

    return (
        <AppLayout title={isAgency ? 'My Agency Referrals' : 'Referral Management'}>
            <Head title="Referrals" />

            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            {isAgency ? 'My Agency Referrals' : 'Referral Management'}
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">Track and manage all referrals across agencies.</p>
                    </div>
                </div>
            </div>

            <UnifiedTable
                columns={columns}
                data={referrals.data}
                keyExtractor={(row) => row.id}
                {...paginatorProps(referrals)}
                searchValue={searchValue}
                searchPlaceholder="Search by case number, client..."
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
        </AppLayout>
    );
}
