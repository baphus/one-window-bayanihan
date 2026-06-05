import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import StatusBadge from '@/Components/ui/StatusBadge';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { CardSection, MetaTile, InfoCell, SubsectionCard } from '@/Components/ui/CardSection';
import AuditLogTimeline from '@/Components/AuditLogTimeline';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import CaseManagerAvatar from '@/Components/CaseManagerAvatar';
import { formatDisplayDate } from '@/lib/utils';

export default function ClientShow({ client, auditLogs }) {
    const [addressNames, setAddressNames] = useState({});
    const fullName = [client.first_name, client.middle_name, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');

    useEffect(() => {
        if (!client.addresses?.length) return;
        const codes = [];
        client.addresses.forEach(addr => {
            if (addr.region) codes.push(addr.region);
            if (addr.province) codes.push(addr.province);
            if (addr.city_municipality) codes.push(addr.city_municipality);
            if (addr.barangay) codes.push(addr.barangay);
        });
        if (codes.length === 0) return;
        const unique = [...new Set(codes)];
        const params = new URLSearchParams();
        unique.forEach(c => params.append('codes[]', c));
        fetch(`/api/address/resolve?${params.toString()}`)
            .then(r => r.json())
            .then(data => setAddressNames(data))
            .catch(() => {});
    }, [client.addresses]);

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
                            <InfoCell label="Full Name" value={
                                <div className="flex items-center gap-3">
                                    <ProfilePictureUpload currentUrl={client.avatar_url} name={fullName} clientId={client.id} size="md" />
                                    <span className="text-sm font-medium text-slate-900">{fullName}</span>
                                </div>
                            } />
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
                                        <MetaTile label="Region" value={addressNames[addr.region] || addr.region || 'N/A'} />
                                        <MetaTile label="Province" value={addressNames[addr.province] || addr.province || 'N/A'} />
                                        <MetaTile label="City/Municipality" value={addressNames[addr.city_municipality] || addr.city_municipality || 'N/A'} />
                                        <MetaTile label="Barangay" value={addressNames[addr.barangay] || addr.barangay || 'N/A'} />
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
                        {!client.caseFile && (
                            <div className="mt-4">
                                <Link
                                    href={route('cases.create', { client_id: client.id })}
                                    className="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                >
                                    <svg className="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Create New Case
                                </Link>
                            </div>
                        )}
                    </CardSection>
                </div>
            </div>
        </AppLayout>
    );
}
