import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

const statusStyles = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    FOR_COMPLIANCE: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
};

export default function ReferralIndex({ referrals, filters }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';

    function paginatorProps(paginator) {
        return {
            totalRecords: paginator.total,
            startIndex: paginator.from,
            endIndex: paginator.to,
            currentPage: paginator.current_page,
            totalPages: paginator.last_page,
            rowsPerPage: paginator.per_page,
            hideControlBar: true,
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

    const columns = useMemo(() => [
        {
            key: 'case_number',
            title: 'Case #',
            sortable: true,
            render: (row) => row.case_file?.case_number ?? 'N/A',
        },
        {
            key: 'client',
            title: 'Client',
            sortable: true,
            render: (row) => row.case_file?.client
                ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}`
                : 'N/A',
        },
        {
            key: 'agency',
            title: 'Agency',
            sortable: true,
            render: (row) => row.agency?.name ?? 'N/A',
        },
        {
            key: 'required_services',
            title: 'Service',
            sortable: false,
            render: (row) => <span className="max-w-xs truncate block">{row.required_services}</span>,
        },
        {
            key: 'status',
            title: 'Status',
            sortable: true,
            render: (row) => (
                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[row.status] || 'bg-slate-100 text-slate-800'}`}>
                    {row.status}
                </span>
            ),
        },
        {
            key: 'id',
            title: 'Actions',
            sortable: false,
            render: (row) => (
                <Link href={route('referrals.show', row.id)} className="text-indigo-600 hover:text-indigo-900">
                    View
                </Link>
            ),
        },
    ], []);

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

            <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                <div className="px-6 py-4">
                    <div className="mb-4 flex flex-wrap gap-2">
                        {['', 'PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'].map((s) => (
                            <Link
                                key={s}
                                href={s ? `${window.location.pathname}?status=${s}` : window.location.pathname}
                                className={`rounded-md px-3 py-1 text-sm ${(filters?.status ?? '') === s ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}`}
                            >
                                {s || 'All'}
                            </Link>
                        ))}
                    </div>

                    <UnifiedTable
                        columns={columns}
                        data={referrals.data}
                        keyExtractor={(row) => row.id}
                        {...paginatorProps(referrals)}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
