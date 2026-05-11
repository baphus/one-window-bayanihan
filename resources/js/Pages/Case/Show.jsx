import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import Timeline from '@/Components/Timeline';
import { CardSection, MetaTile, InfoCell, SubsectionCard } from '@/Components/ui/CardSection';

export default function CaseShow({ case: caseFile }) {
    const client = caseFile.client;

    const timelineItems = (caseFile.referrals || []).flatMap((ref) => [
        ...(ref.milestones || []).map((ms) => ({
            id: ms.id,
            title: `Referral to ${ref.agency?.name || 'Agency'}: ${ms.title}`,
            description: ms.description,
            date: ms.created_at,
            meta: ms.user?.name ? `by ${ms.user.name}` : '',
        })),
        {
            id: `${ref.id}-created`,
            title: `Referred to ${ref.agency?.name || 'Agency'}`,
            description: ref.required_services,
            date: ref.created_at,
            meta: `Status: ${ref.status}`,
        },
    ]).sort((a, b) => new Date(a.date) - new Date(b.date));

    return (
        <AppLayout title={`Case ${caseFile.case_number}`}>
            <Head title={`Case ${caseFile.case_number}`} />

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Case {caseFile.case_number}</h1>
                        <p className="text-sm text-slate-500 mt-1">View detailed case information and referral progress.</p>
                    </div>
                    <Link
                        href={route('cases.index')}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        &larr; Back to Cases
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <CardSection title="Case Details">
                        <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8] border-b border-[#d8dee8]">
                            <InfoCell label="Case Number" value={caseFile.case_number} />
                            <InfoCell label="Tracker Number" value={caseFile.tracker_number} />
                            <InfoCell label="Status" value={
                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${caseFile.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>
                                    {caseFile.status}
                                </span>
                            } />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8]">
                            <InfoCell label="Client Type" value={caseFile.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'} />
                            <InfoCell label="Created By" value={caseFile.user?.name ?? 'N/A'} />
                            <InfoCell label="Created At" value={new Date(caseFile.created_at).toLocaleString()} />
                        </div>
                        {caseFile.summary && (
                            <div className="px-3 py-2">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Summary</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{caseFile.summary}</p>
                            </div>
                        )}
                    </CardSection>

                    {client && (
                        <CardSection title="Client Profile">
                            <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8] border-b border-[#d8dee8]">
                                <InfoCell label="Full Name" value={[client.first_name, client.middle_name, client.last_name, client.suffix].filter(Boolean).join(' ')} />
                                <InfoCell label="Date of Birth" value={client.date_of_birth ? new Date(client.date_of_birth).toLocaleDateString() : 'N/A'} />
                                <InfoCell label="Sex" value={client.sex || 'N/A'} />
                            </div>

                            {client.addresses?.length > 0 && (
                                <div className="px-3 py-2 border-b border-[#d8dee8]">
                                    <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500 mb-2">Addresses</p>
                                    {client.addresses.map((addr) => (
                                        <div key={addr.id} className="text-[12px] text-slate-700 mb-1">
                                            {[addr.line1, addr.line2].filter(Boolean).join(', ')}, {addr.city}, {addr.province} {addr.postal_code}, {addr.country}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {client.employments?.length > 0 && (
                                <div className="px-3 py-2">
                                    <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500 mb-2">Employment History</p>
                                    {client.employments.map((emp) => (
                                        <div key={emp.id} className="text-[12px] text-slate-700 mb-1">
                                            <span className="font-semibold">{emp.position || 'N/A'}</span> at {emp.employer_name || 'N/A'}
                                            {emp.country ? ` (${emp.country})` : ''}
                                            <span className="text-slate-500"> &mdash; {emp.start_date ? new Date(emp.start_date).toLocaleDateString() : '?'} to {emp.end_date ? new Date(emp.end_date).toLocaleDateString() : 'Present'}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardSection>
                    )}

                    <CardSection title={`Referrals (${caseFile.referrals?.length ?? 0})`}>
                        <div className="flex justify-end mb-3">
                            <Link
                                href={route('referrals.create', { case_id: caseFile.id })}
                                className="rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-500"
                            >
                                + New Referral
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Agency</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Service</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {caseFile.referrals?.map((ref) => (
                                        <tr key={ref.id} className="hover:bg-slate-50">
                                            <td className="whitespace-nowrap px-4 py-2 text-sm text-slate-900">
                                                {ref.agency?.name ?? 'N/A'}
                                            </td>
                                            <td className="max-w-xs truncate px-4 py-2 text-sm text-slate-500">
                                                {ref.required_services}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-2">
                                                <span className="inline-flex rounded-full bg-blue-100 px-2 text-xs font-semibold leading-5 text-blue-800">
                                                    {ref.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-2 text-sm">
                                                <Link href={route('referrals.show', ref.id)} className="text-indigo-600 hover:text-indigo-900 font-medium">View</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardSection>
                </div>

                <div className="space-y-6">
                    <CardSection title="Overview">
                        <div className="space-y-2">
                            <MetaTile label="Case Number" value={caseFile.case_number} />
                            <MetaTile label="Tracker Number" value={caseFile.tracker_number} />
                            <MetaTile label="Status" value={
                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${caseFile.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>
                                    {caseFile.status}
                                </span>
                            } />
                            {client && <MetaTile label="Client" value={`${client.first_name} ${client.last_name}`} />}
                            {client && <MetaTile label="Client Type" value={caseFile.client_type === 'OFW' ? 'OFW' : 'Next of Kin'} />}
                            <MetaTile label="Referrals" value={caseFile.referrals?.length ?? 0} />
                            <MetaTile label="Created" value={new Date(caseFile.created_at).toLocaleDateString()} />
                        </div>
                    </CardSection>

                    <CardSection title={`Timeline (${timelineItems.length})`}>
                        <Timeline items={timelineItems} />
                    </CardSection>
                </div>
            </div>
        </AppLayout>
    );
}
