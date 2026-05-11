import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

export default function CaseIndex({ cases, filters }) {
    const { auth } = usePage().props;
    const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';

    return (
        <AppLayout title="Case Management">
            <Head title="Case Management" />

            <div className="mb-8">
              <div className="flex items-center justify-between">
                <div>
                  <h1 className="text-2xl font-bold text-slate-900">Cases</h1>
                  <p className="text-sm text-slate-500 mt-1">Manage all client cases.</p>
                </div>
                {canCreate && (
                    <Link href={route('cases.create')}>
                        <PrimaryButton>Create New Case</PrimaryButton>
                    </Link>
                )}
              </div>
            </div>

            <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                <div className="px-6 py-4">
                    <div className="mb-4">
                        <TextInput
                            type="text"
                            placeholder="Search by case number, tracker number..."
                            className="w-full"
                            defaultValue={filters?.search ?? ''}
                            onInput={(e) => {
                                const url = new URL(window.location);
                                if (e.target.value) {
                                    url.searchParams.set('search', e.target.value);
                                } else {
                                    url.searchParams.delete('search');
                                }
                                window.location = url.toString();
                            }}
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Tracker #</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Client Type</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Created</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {cases.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-4 text-center text-slate-500">
                                            No cases found.
                                        </td>
                                    </tr>
                                ) : (
                                    cases.data.map((caseFile) => (
                                        <tr key={caseFile.id} className="hover:bg-slate-50">
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                                {caseFile.case_number}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                                {caseFile.tracker_number}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                                {caseFile.client_type === 'OFW' ? 'OFW' : 'Next of Kin'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${caseFile.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>
                                                    {caseFile.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                                {new Date(caseFile.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                                                <Link href={route('cases.show', caseFile.id)} className="text-indigo-600 hover:text-indigo-900">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {cases.last_page > 1 && (
                        <div className="border-t border-slate-200 pt-4 mt-4 flex items-center justify-between">
                            <div className="text-sm text-slate-700">
                                Showing {cases.from} to {cases.to} of {cases.total} results
                            </div>
                            <div className="flex gap-2">
                                {cases.links.map((link, i) => (
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
