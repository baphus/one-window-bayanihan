import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { CardSection, MetaTile, InfoCell, SubsectionCard } from '@/Components/ui/CardSection';
import { formatDisplayDate } from '@/lib/utils';

export default function ClientShow({ client }) {
    const fullName = [client.first_name, client.middle_name, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');

    return (
        <AppLayout title={fullName}>
            <Head title={fullName} />

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Client Details</h1>
                        <p className="text-sm text-slate-500 mt-1">Client profile, associated cases, and activity.</p>
                    </div>
                    <Link
                        href={route('clients.index')}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        &larr; Back to Clients
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <CardSection title="Client Information">
                        <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8] border-b border-[#d8dee8]">
                            <InfoCell label="Full Name" value={fullName} />
                            <InfoCell label="Sex" value={client.sex || 'N/A'} />
                            <InfoCell label="Date of Birth" value={client.date_of_birth ? formatDisplayDate(client.date_of_birth) : 'N/A'} />
                        </div>
                        {client.caseFile && (
                            <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8]">
                                <InfoCell label="Case Number" value={
                                    <Link href={route('cases.show', client.caseFile.id)} className="text-indigo-600 hover:text-indigo-900">
                                        {client.caseFile.case_number}
                                    </Link>
                                } />
                                <InfoCell label="Client Type" value={
                                    client.caseFile.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'
                                } />
                                <InfoCell label="Case Status" value={<StatusBadge status={client.caseFile.status} />} />
                            </div>
                        )}
                    </CardSection>

                    {client.caseFile?.summary && (
                        <CardSection title="Case Summary">
                            <p className="text-sm text-slate-700">{client.caseFile.summary}</p>
                        </CardSection>
                    )}

                    {client.addresses?.length > 0 && (
                        <CardSection title="Addresses">
                            {client.addresses.map((addr) => (
                                <SubsectionCard key={addr.id} title="Address">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <MetaTile label="Region" value={addr.region || 'N/A'} />
                                        <MetaTile label="Province" value={addr.province || 'N/A'} />
                                        <MetaTile label="City/Municipality" value={addr.city_municipality || 'N/A'} />
                                        <MetaTile label="Barangay" value={addr.barangay || 'N/A'} />
                                        <MetaTile label="Street" value={addr.street || 'N/A'} />
                                    </div>
                                </SubsectionCard>
                            ))}
                        </CardSection>
                    )}

                    {client.employments?.length > 0 && (
                        <CardSection title="Employment History">
                            {client.employments.map((emp) => (
                                <div key={emp.id} className="mb-3 last:mb-0">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                                        <MetaTile label="Last Country" value={emp.last_country || emp.country || 'N/A'} />
                                        <MetaTile label="Last Position" value={emp.last_position || emp.position || 'N/A'} />
                                        <MetaTile label="Date of Arrival" value={emp.date_of_arrival ? formatDisplayDate(emp.date_of_arrival) : 'N/A'} />
                                        {emp.employer_name && <MetaTile label="Employer" value={emp.employer_name} />}
                                    </div>
                                </div>
                            ))}
                        </CardSection>
                    )}

                    {client.caseFile?.referrals?.length > 0 && (
                        <CardSection title={`Associated Referrals (${client.caseFile.referrals.length})`}>
                            <UnifiedTable
                                columns={[
                                    { key: 'agency', title: 'Agency', render: (row) => row.agency?.name ?? 'N/A' },
                                    { key: 'service', title: 'Service', render: (row) => row.required_services },
                                    { key: 'status', title: 'Status', render: (row) => (
                                        <StatusBadge status={row.status} />
                                    )},
                                    { key: 'actions', title: 'Actions', render: (row) => (
                                        <Link href={route('referrals.show', row.id)} className="text-indigo-600 hover:text-indigo-900">View</Link>
                                    )},
                                ]}
                                data={client.caseFile.referrals}
                                keyExtractor={(row) => row.id}
                                variant="embedded"
                                hideControlBar
                                hidePagination
                            />
                        </CardSection>
                    )}
                </div>

                <div className="space-y-6">
                    <CardSection title="Overview">
                        <div className="space-y-2">
                            <MetaTile label="Full Name" value={fullName} />
                            <MetaTile label="Sex" value={client.sex || 'N/A'} />
                            <MetaTile label="Date of Birth" value={client.date_of_birth ? formatDisplayDate(client.date_of_birth) : 'N/A'} />
                            {client.caseFile && (
                                <>
                                    <MetaTile label="Case Number" value={
                                        <Link href={route('cases.show', client.caseFile.id)} className="text-indigo-600 hover:text-indigo-900">
                                            {client.caseFile.case_number}
                                        </Link>
                                    } />
                                    <MetaTile label="Client Type" value={client.caseFile.client_type === 'OFW' ? 'OFW' : 'Next of Kin'} />
                                    <MetaTile label="Referrals" value={client.caseFile.referrals?.length ?? 0} />
                                </>
                            )}
                            <MetaTile label="Created" value={formatDisplayDate(client.created_at)} />
                        </div>
                    </CardSection>
                </div>
            </div>
        </AppLayout>
    );
}
