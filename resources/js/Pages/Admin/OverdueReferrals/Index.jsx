import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate } from '@/lib/utils';

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
            <span className="text-sm text-slate-600">{formatDisplayDate(row.created_at)}</span>
        )},
        { key: 'overdue', title: 'Overdue', render: (row) => (
            <span className="inline-flex rounded-full px-2 text-xs font-semibold leading-5 bg-red-100 text-red-800">
                {Math.floor((Date.now() - new Date(row.created_at).getTime()) / (1000 * 60 * 60 * 24))} days
            </span>
        )},
        { key: 'status', title: 'Status', render: (row) => (
            <StatusBadge status={row.status} />
        )},
        { key: 'actions', title: 'Actions', render: (row) => (
            <div className="flex items-center gap-2">
                <button onClick={() => router.visit(route('referrals.show', row.id))} className="min-h-[28px] px-2.5 bg-[#0b5384] text-white hover:bg-[#09416a] text-[11px] font-bold rounded-[3px] transition-colors border border-[#0b5384]">View</button>
                <button onClick={() => sendReminders([row.id])} disabled={sending} className="min-h-[28px] px-2.5 bg-amber-50 text-amber-600 hover:bg-amber-100 text-[11px] font-bold rounded-[3px] transition-colors border border-amber-200 disabled:opacity-50">Remind</button>
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
                router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['overdueReferrals'] });
            },
            onRowsPerPageChange: (n) => {
                const url = new URL(window.location);
                url.searchParams.set('per_page', n);
                url.searchParams.delete('page');
                router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['overdueReferrals'] });
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
                                className="px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 rounded-md hover:bg-amber-100 disabled:opacity-50 flex items-center gap-2 border border-amber-200"
                            >
                                <span className="material-symbols-outlined text-[16px]">notifications</span>
                                Send Reminder ({selectedIds.length})
                            </button>
                        )}
                        {p.total > 0 && (
                            <button
                                onClick={() => sendReminders([])}
                                disabled={sending}
                                className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] disabled:opacity-50 flex items-center gap-2"
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
