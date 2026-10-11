import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { MetaTile, InfoField, CardHeader } from '@/Components/ui/CardSection';
import AuditLogTimeline from '@/Components/AuditLogTimeline';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import UserAvatar, { getAvatarColor } from '@/Components/ui/UserAvatar';
import { formatDisplayDate } from '@/lib/utils';
import { formatResolvedAddress } from '@/lib/addressResolver';

const vulnConfig = {
  'PWD': { icon: 'accessibility', className: 'bg-purple-100 text-purple-800 border-purple-200' },
  'Senior Citizen': { icon: 'elderly', className: 'bg-orange-100 text-orange-800 border-orange-200' },
  'Solo Parent': { icon: 'family_home', className: 'bg-pink-100 text-pink-800 border-pink-200' },
  'Indigenous Person': { icon: 'groups', className: 'bg-teal-100 text-teal-800 border-teal-200' },
};

function getClientAge(dob) {
  if (!dob) return '\u2014';
  const birth = new Date(dob);
  if (Number.isNaN(birth.getTime())) return '\u2014';
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--;
  }
  return age;
}

function formatAddress(addr) {
  return formatResolvedAddress(addr, '');
}

const cardSectionClass = 'rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]';

export default function ClientShow({ client, cases: casesProp, auditLogs }) {
    const cases = casesProp ?? client.caseFiles ?? [];
    const fullName = [client.first_name, client.middle_name, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');
    const clientInitials = [client.first_name, client.last_name].filter(Boolean).map((part) => part[0]).join('').toUpperCase().slice(0, 2);
    const primaryAddress = client?.addresses?.[0] || null;
    const primaryEmployment = client?.employments?.[0] || null;

    const totalReferrals = cases.reduce(
        (sum, c) => sum + (c.referrals_count ?? c.referrals?.length ?? 0),
        0,
    );
    const activeCases = cases.filter((c) => c.status === 'OPEN' || c.status === 'IN_PROGRESS').length;
    const stats = [
        { label: 'Total cases', value: cases.length },
        { label: 'Active cases', value: activeCases },
        { label: 'Referrals', value: totalReferrals },
    ];

    return (
        <AppLayout title={fullName}>
            <Head title={fullName} />

            <div className="min-h-full bg-slate-50">
                <div className="mx-auto max-w-[1440px] p-6">
                    {/* Header — above the grid so both columns start on the same line (Case/Show pattern) */}
                    <div className="mb-4">
                        <Link href={route('clients.index')} className="text-sm text-slate-500 hover:text-slate-700">
                            &larr; Back to Clients
                        </Link>
                        <h1 className="mt-1 text-3xl font-bold text-slate-900">Client Details</h1>
                        <p className="mt-1 text-sm text-slate-500">Client profile, associated cases, and activity.</p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                        <div className="space-y-4 xl:col-span-2">
                            {/* Cases */}
                            <section className={cardSectionClass}>
                                <CardHeader
                                    title="Cases"
                                    meta={`${cases.length}`}
                                    actions={
                                        <Link
                                            href={route('cases.create', { client_id: client.id })}
                                            className="inline-flex items-center rounded-md bg-blue-900 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-800"
                                        >
                                            + Create New Case
                                        </Link>
                                    }
                                />
                                <div className="px-5 pb-5">
                                    {cases.length > 0 ? (
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
                                                { key: 'manager', title: 'Manager', render: (row) => <UserAvatar user={row.user} size="sm" fallbackSrc="/images/bayanihan-logo.svg" tooltip /> },
                                                { key: 'date_filed', title: 'Date Filed', render: (row) => formatDisplayDate(row.created_at) },
                                            ]}
                                            data={cases}
                                            keyExtractor={(row) => row.id}
                                            variant="embedded"
                                            hideControlBar
                                            hidePagination
                                        />
                                    ) : (
                                        <p className="text-sm text-slate-500">No cases on file.</p>
                                    )}
                                </div>
                            </section>

                            {/* Case Summary */}
                            {client.caseFile?.summary && (
                                <section className={cardSectionClass}>
                                    <CardHeader title="Case Summary" />
                                    <div className="px-5 pb-5">
                                        <p className="text-sm text-slate-700">{client.caseFile.summary}</p>
                                    </div>
                                </section>
                            )}

                            {/* Addresses — primary (latest) only */}
                            <section className={cardSectionClass}>
                                <CardHeader title="Address" />
                                <div className="px-5 pb-5">
                                    {primaryAddress ? (
                                        <>
                                            <div className="grid grid-cols-3 gap-4">
                                                <InfoField label="Region" value={primaryAddress.region} />
                                                <InfoField label="Province" value={primaryAddress.province} />
                                                <InfoField label="City/Municipality" value={primaryAddress.city_municipality} />
                                            </div>
                                            <div className="mt-4 grid grid-cols-2 gap-4">
                                                <InfoField label="Barangay" value={primaryAddress.barangay} />
                                                <InfoField label="Street" value={primaryAddress.street} />
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-sm text-slate-500">No address on file.</p>
                                    )}
                                </div>
                            </section>

                            {/* Employment History */}
                            {client.employments?.length > 0 && (
                                <section className={cardSectionClass}>
                                    <CardHeader title="Employment History" meta={`${client.employments.length}`} />
                                    <div className="space-y-4 px-5 pb-5">
                                        {client.employments.map((emp) => (
                                            <div key={emp.id}>
                                                <div className="grid grid-cols-3 gap-4">
                                                    <InfoField label="Last Country" value={emp.last_country || emp.country} />
                                                    <InfoField label="Last Occupation" value={emp.last_position || emp.position} />
                                                    <InfoField label="Date of Arrival" value={emp.date_of_arrival ? formatDisplayDate(emp.date_of_arrival) : null} />
                                                </div>
                                                {emp.employer_name && (
                                                    <div className="mt-4 grid grid-cols-2 gap-4">
                                                        <InfoField label="Employer" value={emp.employer_name} />
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {/* Next of Kin */}
                            <section className={cardSectionClass}>
                                <CardHeader title="Next of Kin" />
                                <div className="px-5 pb-5">
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
                                </div>
                            </section>

                            {/* Associated Referrals */}
                            {client.caseFile?.referrals?.length > 0 && (
                                <section className={cardSectionClass}>
                                    <CardHeader title="Associated Referrals" meta={`${client.caseFile.referrals.length}`} />
                                    <div className="px-5 pb-5">
                                        <UnifiedTable
                                            columns={[
                                                { key: 'caseManager', title: 'Case Manager', render: () => (
                                                    <UserAvatar user={client.caseFile?.user} size="sm" fallbackSrc="/images/bayanihan-logo.svg" tooltip />
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
                                    </div>
                                </section>
                            )}

                            {/* Activity */}
                            {auditLogs && (
                                <section className={cardSectionClass}>
                                    <CardHeader title="Activity" />
                                    <div className="px-5 pb-5">
                                        <AuditLogTimeline logs={auditLogs} client={client} />
                                    </div>
                                </section>
                            )}
                        </div>

                        {/* Right rail */}
                        <div className="space-y-4">
                            {/* Client Information — mirrors Case/Show.jsx sidebar card */}
                            <section data-tour="case-client-info" className={cardSectionClass}>
                                <CardHeader title="Client Information" />
                                <div className="space-y-4 px-5 pb-5">
                                    {/* Avatar — display identical to Case/Show; upload via small action below */}
                                    {client.avatar_url ? (
                                        <img
                                            src={client.avatar_url}
                                            alt=""
                                            className="h-[88px] w-[88px] shrink-0 rounded-2xl border border-slate-200 object-cover"
                                            onError={(e) => { e.target.style.display = 'none'; }}
                                        />
                                    ) : (
                                        <span
                                            className={`inline-flex h-[88px] w-[88px] shrink-0 items-center justify-center rounded-2xl text-[26px] font-bold text-white ${getAvatarColor(fullName)}`}
                                            aria-hidden="true"
                                        >
                                            {clientInitials || '?'}
                                        </span>
                                    )}
                                    {/* Change-photo trigger: label over the invisible ProfilePictureUpload avatar button;
                                        opacity-0 targets only its preview sub-block so crop/save/remove flow stays visible */}
                                    <div className="relative inline-block">
                                        <div className="[&>div:first-child]:opacity-0 [&_button]:cursor-pointer">
                                            <ProfilePictureUpload currentUrl={client.avatar_url} name={fullName} clientId={client.id} size="sm" />
                                        </div>
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none absolute left-0 top-0 flex h-12 w-12 items-center justify-center rounded-full border border-dashed border-slate-300 text-center text-[10px] font-medium leading-tight text-slate-500"
                                        >
                                            Change photo
                                        </span>
                                    </div>

                                    <InfoField label="Full name" value={fullName} />

                                    <InfoField label="Date of birth" value={client?.date_of_birth ? formatDisplayDate(client.date_of_birth) : null} />

                                    <div className="grid grid-cols-2 gap-3">
                                        <InfoField label="Age" value={client?.date_of_birth ? getClientAge(client.date_of_birth) : null} />
                                        <InfoField label="Sex" value={client?.sex} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <InfoField label="Email" value={client?.email} />
                                        <InfoField label="Contact no." value={client?.contact_number} />
                                    </div>

                                    {/* Vulnerability (from the client's primary case file) */}
                                    {(() => {
                                        const ofwVulns = (client.caseFile?.vulnerability_indicator || '').split(',').map(s => s.trim()).filter(v => v && v !== 'None');
                                        const nokVulns = (client.caseFile?.nok_vulnerability_indicator || '').split(',').map(s => s.trim()).filter(v => v && v !== 'None');
                                        const hasVulns = ofwVulns.length > 0 || nokVulns.length > 0;
                                        if (!hasVulns) return null;
                                        return (
                                            <div>
                                                <p className="text-[12px] font-medium text-slate-500">Vulnerability</p>
                                                <div className="mt-1.5 flex flex-wrap gap-1.5">
                                                    {ofwVulns.map((v) => (
                                                        <span key={`ofw-${v}`} className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold ${vulnConfig[v]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                                                            <span className="material-symbols-outlined text-[13px]">{vulnConfig[v]?.icon || 'warning'}</span>
                                                            OFW: {v}
                                                        </span>
                                                    ))}
                                                    {nokVulns.map((v) => (
                                                        <span key={`nok-${v}`} className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold ${vulnConfig[v]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                                                            <span className="material-symbols-outlined text-[13px]">{vulnConfig[v]?.icon || 'warning'}</span>
                                                            NOK: {v}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })()}

                                    <InfoField label="Address" value={primaryAddress ? formatAddress(primaryAddress) : null} fallback="No address recorded" />

                                    {primaryEmployment && (
                                        <>
                                            <hr className="border-slate-200" />
                                            <div className="grid grid-cols-2 gap-3">
                                                <InfoField
                                                    label="Work history"
                                                    value={`${primaryEmployment.last_country || primaryEmployment.country || 'N/A'} · ${primaryEmployment.last_position || primaryEmployment.position || 'N/A'}`}
                                                />
                                                <InfoField label="Arrival date" value={primaryEmployment.date_of_arrival ? formatDisplayDate(primaryEmployment.date_of_arrival) : null} />
                                            </div>
                                        </>
                                    )}

                                    <hr className="border-slate-200" />
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        {stats.map((s) => (
                                            <div key={s.label}>
                                                <p className="text-xs text-slate-500">{s.label}</p>
                                                <p className="text-lg font-semibold text-slate-900">{s.value}</p>
                                            </div>
                                        ))}
                                    </div>
                                    <hr className="border-slate-200" />
                                    <InfoField label="Client since" value={formatDisplayDate(client.created_at)} />
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
