import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function CaseShow({ case: caseFile }) {
    const client = caseFile.client;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Case {caseFile.case_number}
                    </h2>
                    <Link
                        href={route('cases.index')}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        &larr; Back to Cases
                    </Link>
                </div>
            }
        >
            <Head title={`Case ${caseFile.case_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="space-y-6">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 bg-white px-6 py-4">
                                <h3 className="text-lg font-medium text-gray-900">Case Details</h3>
                            </div>
                            <div className="px-6 py-4">
                                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Case Number</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{caseFile.case_number}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Tracker Number</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{caseFile.tracker_number}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Client Type</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {caseFile.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Status</dt>
                                        <dd className="mt-1">
                                            <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${caseFile.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                                {caseFile.status}
                                            </span>
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-sm font-medium text-gray-500">Summary</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {caseFile.summary || 'No summary provided.'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Created By</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{caseFile.user?.name ?? 'N/A'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Created At</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {new Date(caseFile.created_at).toLocaleString()}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        {client && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 bg-white px-6 py-4">
                                    <h3 className="text-lg font-medium text-gray-900">Client Profile</h3>
                                </div>
                                <div className="px-6 py-4">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Full Name</dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {[client.first_name, client.middle_name, client.last_name, client.suffix]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Date of Birth</dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {client.date_of_birth ? new Date(client.date_of_birth).toLocaleDateString() : 'N/A'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Sex</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{client.sex || 'N/A'}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        )}

                        {client?.addresses?.length > 0 && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 bg-white px-6 py-4">
                                    <h3 className="text-lg font-medium text-gray-900">Address</h3>
                                </div>
                                <div className="px-6 py-4">
                                    {client.addresses.map((addr) => (
                                        <dl key={addr.id} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <dt className="text-sm font-medium text-gray-500">Street</dt>
                                                <dd className="mt-1 text-sm text-gray-900">
                                                    {[addr.line1, addr.line2].filter(Boolean).join(', ') || 'N/A'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">City</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{addr.city || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Province</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{addr.province || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Postal Code</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{addr.postal_code || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Country</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{addr.country || 'N/A'}</dd>
                                            </div>
                                        </dl>
                                    ))}
                                </div>
                            </div>
                        )}

                        {client?.employments?.length > 0 && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 bg-white px-6 py-4">
                                    <h3 className="text-lg font-medium text-gray-900">Employment History</h3>
                                </div>
                                <div className="px-6 py-4">
                                    {client.employments.map((emp) => (
                                        <dl key={emp.id} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Employer</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{emp.employer_name || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Position</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{emp.position || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Country</dt>
                                                <dd className="mt-1 text-sm text-gray-900">{emp.country || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Period</dt>
                                                <dd className="mt-1 text-sm text-gray-900">
                                                    {emp.start_date ? new Date(emp.start_date).toLocaleDateString() : '?'}
                                                    {' — '}
                                                    {emp.end_date ? new Date(emp.end_date).toLocaleDateString() : 'Present'}
                                                </dd>
                                            </div>
                                        </dl>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 bg-white px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        Referrals ({caseFile.referrals?.length ?? 0})
                                    </h3>
                                    <Link
                                        href={route('referrals.create', { case_id: caseFile.id })}
                                        className="rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                                    >
                                        New Referral
                                    </Link>
                                </div>
                            </div>
                                <div className="px-6 py-4">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Agency</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Service</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {caseFile.referrals.map((ref) => (
                                                <tr key={ref.id}>
                                                    <td className="whitespace-nowrap px-4 py-2 text-sm text-gray-900">
                                                        {ref.agency?.name ?? 'N/A'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                                                        {ref.required_services}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2">
                                                        <span className="inline-flex rounded-full bg-blue-100 px-2 text-xs font-semibold leading-5 text-blue-800">
                                                            {ref.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
