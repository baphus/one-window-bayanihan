import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { CardSection, MetaTile, InfoCell, SubsectionCard } from '@/Components/ui/CardSection';
import AuditLogTimeline from '@/Components/AuditLogTimeline';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import CaseManagerAvatar from '@/Components/CaseManagerAvatar';
import { formatDisplayDate } from '@/lib/utils';

export default function ClientShow({ client, cases: casesProp, auditLogs }) {
    const cases = casesProp ?? client.caseFiles ?? [];
    const fullName = [client.first_name, client.middle_name, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');

    const totalReferrals = cases.reduce(
        (sum, c) => sum + (c.referrals_count ?? c.referrals?.length ?? 0),
        0,
    );
    const activeCases = cases.filter((c) => c.status === 'OPEN' || c.status === 'IN_PROGRESS').length;

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
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0"
                    >
                        &larr; Back to Clients
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    {/* Cases */}
                    {cases.length > 0 && (
                        <CardSection title={`Cases (${cases.length})`}>
                            <UnifiedTable
                                columns={[
                                    {
                                        key: 'case_number',
                                        title: 'Case Number',
                                        render: (row) => (
                                            <Link href={route('cases.show', row.id)} className="text-indigo-600 hover:text-indigo-900 font-medium">
                                                {row.case_number}
                                            </Link>
                                        ),
                                    },
                                    { key: 'type', title: 'Type', render: (row) => row.client_type === 'OFW' ? 'OFW' : 'Next of Kin' },
                                    { key: 'status', title: 'Status', render: (row) => <StatusBadge status={row.status} /> },
                                    { key: 'manager', title: 'Manager', render: (row) => <CaseManagerAvatar user={row.user} size="sm" /> },
                                    { key: 'date_filed', title: 'Date Filed', render: (row) => formatDisplayDate(row.created_at) },
                                    { key: 'referrals', title: 'Referrals', render: (row) => row.referrals_count ?? row.referrals?.length ?? 0 },
                                    {
                                        key: 'actions',
                                        title: '',
                                        render: (row) => (
                                            <Link href={route('cases.show', row.id)} className="text-indigo-600 hover:text-indigo-900">View</Link>
                                        ),
                                    },
                                ]}
                                data={cases}
                                keyExtractor={(row) => row.id}
                                variant="embedded"
                                hideControlBar
                                hidePagination
                            />
                        </CardSection>
                    )}

                    {/* Client Information */}
                    <CardSection title="Client Information">
                        <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-surface-variant border-b border-surface-variant">
                            <InfoCell label="Full Name" value={
                                <div className="flex items-center gap-3">
                                    <ProfilePictureUpload currentUrl={client.avatar_url} name={fullName} clientId={client.id} size="md" />
                                    <span className="text-sm font-medium text-slate-900">{fullName}</span>
                                </div>
                            } />
                            <InfoCell label="Sex" value={client.sex || 'N/A'} />
                            <InfoCell label="Date of Birth" value={client.date_of_birth ? formatDisplayDate(client.date_of_birth) : 'N/A'} />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-surface-variant">
                            <InfoCell label="Email" value={
                                client.email
                                    ? <a href={`mailto:${client.email}`} className="text-indigo-600 hover:text-indigo-900">{client.email}</a>
                                    : 'N/A'
                            } />
                            <InfoCell label="Contact Number" value={
                                client.contact_number
                                    ? <a href={`tel:${client.contact_number}`} className="text-indigo-600 hover:text-indigo-900">{client.contact_number}</a>
                                    : 'N/A'
                            } />
                        </div>
                    </CardSection>

                    {client.caseFile?.summary && (
                        <CardSection title="Case Summary">
                            <p className="text-sm text-slate-700">{client.caseFile.summary}</p>
                        </CardSection>
                    )}

                    {/* Next of Kin */}
                    <CardSection title="Next of Kin">
                        {client.nextOfKin?.length > 0 ? (
                            <div className="space-y-3">
                                {client.nextOfKin.map((nok) => (
                                    <div key={nok.id} className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <MetaTile label="Name" value={
                                            [nok.first_name, nok.middle_name, nok.last_name].filter(Boolean).join(' ') || 'N/A'
                                        } />
                                        <MetaTile label="Relationship" value={nok.relationship || 'N/A'} />
                                        <MetaTile label="Contact" value={
                                            nok.phone_number
                                                ? <a href={`tel:${nok.phone_number}`} className="text-indigo-600 hover:text-indigo-900">{nok.phone_number}</a>
                                                : (nok.email ? <a href={`mailto:${nok.email}`} className="text-indigo-600 hover:text-indigo-900">{nok.email}</a> : 'N/A')
                                        } />
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-slate-500">No next of kin on file.</p>
                        )}
                    </CardSection>

                    {/* Addresses */}
                    {client.addresses?.length > 0 && (
                        <CardSection title="Addresses">
                            {client.addresses.map((addr, idx) => (
                                <SubsectionCard key={addr.id} title={`Address ${idx + 1}`}>
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
                                        <MetaTile label="Last Occupation" value={emp.last_position || emp.position || 'N/A'} />
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
                                    { key: 'caseManager', title: 'Case Manager', render: (row) => (
                                        <CaseManagerAvatar user={client.caseFile?.user} size="sm" />
                                    )},
                                    { key: 'agency', title: 'Agency', render: (row) => (
                                        <div className="flex items-center gap-2">
                                            {row.agency?.logo_url && (
                                                <img src={row.agency.logo_url} alt="" className="h-6 w-6 rounded-full object-cover border border-slate-200"
                                                    onError={(e) => { e.target.style.display = 'none' }} />
                                            )}
                                            <span>{row.agency?.name ?? 'N/A'}</span>
                                        </div>
                                    )},
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

                    {auditLogs && (
                        <CardSection title="Activity Timeline">
                            <AuditLogTimeline logs={auditLogs} client={client} />
                        </CardSection>
                    )}
                </div>

                <div className="space-y-6">
                    <CardSection title="Quick Facts">
                        <div className="space-y-2">
                            <MetaTile label="Full Name" value={fullName} />
                            <MetaTile label="Contact" value={
                                client.contact_number
                                    ? <a href={`tel:${client.contact_number}`} className="text-indigo-600 hover:text-indigo-900">{client.contact_number}</a>
                                    : 'N/A'
                            } />
                            <MetaTile label="Email" value={
                                client.email
                                    ? <a href={`mailto:${client.email}`} className="text-indigo-600 hover:text-indigo-900">{client.email}</a>
                                    : 'N/A'
                            } />
                            <MetaTile label="Total Cases" value={cases.length} />
                            <MetaTile label="Active Cases" value={activeCases} />
                            <MetaTile label="Referrals" value={totalReferrals} />
                            <MetaTile label="Client Since" value={formatDisplayDate(client.created_at)} />
                        </div>
                        <div className="mt-4">
                            <Link
                                href={route('cases.create', { client_id: client.id })}
                                className="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                <svg className="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create New Case
                            </Link>
                        </div>
                    </CardSection>
                </div>
            </div>
        </AppLayout>
    );
}
