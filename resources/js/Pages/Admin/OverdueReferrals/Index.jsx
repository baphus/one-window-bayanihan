import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

const statusStyles = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    FOR_COMPLIANCE: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
};

export default function OverdueReferralsIndex({ overdueReferrals, overdueDays }) {
    const [selectedIds, setSelectedIds] = useState([]);
    const [sending, setSending] = useState(false);

    const overdueColumns = [
        { key: 'case_number', title: 'Case #', render: (row) => (
            <span className="text-sm font-mono text-slate-900">{row.case_file?.case_number ?? 'N/A'}</span>
        )},
        { key: 'client', title: 'Client', render: (row) => (
            <span className="text-sm text-slate-700">
                {row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}
            </span>
        )},
        { key: 'agency', title: 'Agency', render: (row) => (
            <span className="text-sm text-slate-700">{row.agency?.name ?? 'N/A'}</span>
        )},
        { key: 'service', title: 'Service', render: (row) => (
            <span className="text-sm text-slate-600 max-w-xs truncate block">{row.required_services}</span>
        )},
        { key: 'created', title: 'Created', render: (row) => (
            <span className="text-sm text-slate-600">{new Date(row.created_at).toLocaleDateString()}</span>
        )},
        { key: 'overdue', title: 'Overdue', render: (row) => (
            <span className="inline-flex rounded-full px-2 text-xs font-semibold leading-5 bg-red-100 text-red-800">
                {Math.floor((Date.now() - new Date(row.created_at).getTime()) / (1000 * 60 * 60 * 24))} days
            </span>
        )},
        { key: 'status', title: 'Status', render: (row) => (
            <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[row.status] || 'bg-slate-100 text-slate-800'}`}>
                {row.status}
            </span>
        )},
        { key: 'actions', title: 'Actions', render: (row) => (
            <div className="flex items-center gap-2">
                <a href={route('referrals.show', row.id)} className="text-indigo-600 hover:text-indigo-900 text-sm font-medium">View</a>
                <button onClick={() => sendReminders([row.id])} disabled={sending} className="text-amber-600 hover:text-amber-900 text-sm font-medium disabled:opacity-50">Remind</button>
            </div>
        )},
    ];

    function sendReminders(referralIds) {
        if (sending) return;
        setSending(true);
        router.post(route('admin.overdue-referrals.send-reminders'), {
            referral_ids: referralIds,
        }, {
            preserveScroll: true,
            onFinish: () => setSending(false),
        });
    }

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

    const p = overdueReferrals;

    return (
        <AppLayout title="Overdue Referrals">
            <Head title="Overdue Referrals" />
            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Overdue Referrals</h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Referrals not completed or rejected within {overdueDays} days of creation.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {selectedIds.length > 0 && (
                            <button
                                onClick={() => sendReminders(selectedIds)}
                                disabled={sending}
                                className="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-500 disabled:opacity-50 flex items-center gap-2"
                            >
                                <span className="material-symbols-outlined text-[16px]">notifications</span>
                                Send Reminder ({selectedIds.length})
                            </button>
                        )}
                        {p.total > 0 && (
                            <button
                                onClick={() => sendReminders([])}
                                disabled={sending}
                                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500 disabled:opacity-50 flex items-center gap-2"
                            >
                                <span className="material-symbols-outlined text-[16px]">notifications_active</span>
                                Send All Reminders
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <UnifiedTable
                columns={overdueColumns}
                data={overdueReferrals.data}
                keyExtractor={(row) => row.id}
                selectable={true}
                selectedKeys={selectedIds}
                onSelectionChange={setSelectedIds}
                {...paginatorProps(overdueReferrals)}
            />
        </AppLayout>
    );
}
