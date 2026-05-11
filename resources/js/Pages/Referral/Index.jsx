import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';

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

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Client</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Agency</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Service</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {referrals.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-4 text-center text-slate-500">
                                            No referrals found.
                                        </td>
                                    </tr>
                                ) : (
                                    referrals.data.map((ref) => (
                                        <tr key={ref.id} className="hover:bg-slate-50">
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                                {ref.case_file?.case_number ?? 'N/A'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                                {ref.case_file?.client
                                                    ? `${ref.case_file.client.first_name} ${ref.case_file.client.last_name}`
                                                    : 'N/A'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                                {ref.agency?.name ?? 'N/A'}
                                            </td>
                                            <td className="max-w-xs truncate px-6 py-4 text-sm text-slate-500">
                                                {ref.required_services}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[ref.status] || 'bg-slate-100 text-slate-800'}`}>
                                                    {ref.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <Link href={route('referrals.show', ref.id)} className="text-indigo-600 hover:text-indigo-900">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {referrals.last_page > 1 && (
                        <div className="border-t border-slate-200 pt-4 mt-4 flex items-center justify-between">
                            <div className="text-sm text-slate-700">
                                Showing {referrals.from} to {referrals.to} of {referrals.total}
                            </div>
                            <div className="flex gap-2">
                                {referrals.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        className={`inline-flex items-center rounded-md px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
