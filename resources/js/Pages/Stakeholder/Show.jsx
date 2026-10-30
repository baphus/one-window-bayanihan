import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import AgencyMapView from '@/Components/AgencyMapView';
import KpiCard from '@/Components/ui/KpiCard';

/**
 * Stakeholder details — partner office record.
 *
 * A white partner-office header answers "who is this office", then the page
 * splits in two: the left column holds the about text and the services it
 * covers, the right column holds contact details, referral performance, and
 * the location map.
 */

function AgencyLogo({ agency, size = 'h-14 w-14' }) {
    const [hasError, setHasError] = useState(false);

    if (hasError || !agency.logo_url) {
        return (
            <div className={`${size} flex items-center justify-center bg-primary-fixed text-base font-extrabold text-primary`}>
                {agency.short?.charAt(0) ?? agency.name?.charAt(0)}
            </div>
        );
    }

    return (
        <img
            src={agency.logo_url}
            alt={`${agency.short} logo`}
            className={`${size} object-contain p-[2px]`}
            referrerPolicy="no-referrer"
            loading="lazy"
            onError={() => setHasError(true)}
        />
    );
}

/**
 * Contact details come from a single free-text field, so the address, phone,
 * and email are pulled out of its lines. The leading lines before the phone
 * and email make up the address; anything trailing them is kept as an extra
 * line so nothing is lost.
 */
function parseContact(contactInfo) {
    const lines = String(contactInfo ?? '')
        .split(/\r\n|\r|\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    const result = { address: [], telephone: '', email: '', extra: [] };
    let reachedContact = false;

    lines.forEach((line) => {
        if (!result.telephone && /^tel[:.]/i.test(line)) {
            result.telephone = line.replace(/^tel[:.]\s*/i, '');
            reachedContact = true;
            return;
        }

        if (!result.email && /^email[:.]/i.test(line)) {
            result.email = line.replace(/^email[:.]\s*/i, '');
            reachedContact = true;
            return;
        }

        if (reachedContact) {
            result.extra.push(line);
            return;
        }

        result.address.push(line);
    });

    return { ...result, address: result.address.join('\n') };
}

/** One covered service — name, description, lead time, and required documents. */
function ServiceBlock({ service }) {
    const requirements = service.requirements ?? [];

    return (
        <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="p-5">
                <h3 className="text-[15px] font-bold text-slate-900">{service.name}</h3>

                {service.description && (
                    <p className="mt-1 text-[13px] leading-relaxed text-slate-600">{service.description}</p>
                )}

                {service.processing_days && (
                    <p className="mt-2.5 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-primary">
                        <span aria-hidden="true" className="material-symbols-outlined text-[16px]">schedule</span>
                        Processing time: {service.processing_days} day{service.processing_days > 1 ? 's' : ''}
                    </p>
                )}
            </div>

            {requirements.length > 0 && (
                <div className="border-t border-slate-100 bg-surface-container-low px-5 py-4">
                    <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                        Required documents
                    </p>
                    <ul className="mt-2 flex flex-wrap gap-x-5 gap-y-1.5">
                        {requirements.map((req) => (
                            <li key={req.id} className="inline-flex items-center gap-1.5 text-[13px] text-slate-700">
                                <span aria-hidden="true" className="material-symbols-outlined text-[16px] text-slate-400">description</span>
                                {req.name}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </article>
    );
}

export default function StakeholderShow({ stakeholder, stats }) {
    const services = stakeholder.services ?? [];

    const { total_referrals: total = 0, active_referrals: active = 0, completed_referrals: completed = 0 } = stats ?? {};

    const contact = useMemo(() => parseContact(stakeholder.contact_info), [stakeholder.contact_info]);

    const completionPercent = total > 0 ? Math.round((completed / total) * 100) : 0;

    return (
        <AppLayout title={stakeholder.short ?? stakeholder.name}>
            <Head title={stakeholder.short ?? stakeholder.name} />

            {/* Back to Stakeholders */}
            <Link
                href={route('stakeholders.index')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Stakeholders
            </Link>

            <header data-tour="stakeholders-header" className="mb-6 mt-4">
                <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
                    Stakeholder details
                </h1>
                <p className="text-sm text-slate-400 font-body mt-0.5">
                    Agency information, services and referral performance.
                </p>
            </header>

            {/* Partner office header */}
            <section className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-4">
                    <div className="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-white">
                        <AgencyLogo agency={stakeholder} />
                    </div>
                    <div className="min-w-0">
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Partner office</p>
                        <h2 className="mt-1 text-xl font-bold text-slate-900">{stakeholder.name ?? 'Partner office'}</h2>
                        <p className="text-[13px] text-slate-500">{stakeholder.short}</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-md bg-primary-fixed px-2.5 py-1 text-[12px] font-semibold text-primary">
                        {services.length} {services.length === 1 ? 'service' : 'services'}
                    </span>
                    {!stakeholder.is_active && (
                        <span className="rounded-md border border-amber-300/60 bg-amber-50 px-2.5 py-1 text-[12px] font-semibold text-amber-700">
                            Inactive
                        </span>
                    )}
                </div>
            </section>

            {/* Main content + sidebar */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                {/* Left column — about + services */}
                <div className="min-w-0">
                    {stakeholder.description && (
                        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <h2 className="border-b border-slate-100 px-5 py-3.5 text-[15px] font-bold text-slate-900">
                                About
                            </h2>
                            <p className="whitespace-pre-wrap px-5 py-4 text-[13.5px] leading-relaxed text-slate-700">
                                {stakeholder.description}
                            </p>
                        </section>
                    )}

                    <section className={stakeholder.description ? 'mt-6' : ''}>
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-[15px] font-bold text-slate-900">Services covered</h2>
                            <p className="text-[12.5px] text-slate-500">
                                {services.length} {services.length === 1 ? 'service' : 'services'}
                            </p>
                        </div>

                        {services.length > 0 ? (
                            <div className="mt-3 space-y-3">
                                {services.map((service) => (
                                    <ServiceBlock key={service.id} service={service} />
                                ))}
                            </div>
                        ) : (
                            <div className="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/50 px-5 py-8 text-center">
                                <span aria-hidden="true" className="material-symbols-outlined text-3xl text-slate-300">inventory_2</span>
                                <p className="mt-2 text-sm font-semibold text-slate-700">No services configured yet</p>
                                <p className="mx-auto mt-1 max-w-xs text-[13px] text-slate-500">
                                    This office has not published any services. Cases cannot be referred here until services are added.
                                </p>
                            </div>
                        )}
                    </section>
                </div>

                {/* Right column — contact, performance, location */}
                <aside className="space-y-6">
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <h2 className="border-b border-slate-100 px-5 py-3.5 text-[15px] font-bold text-slate-900">
                            Contact information
                        </h2>
                        <dl className="space-y-4 px-5 py-4">
                            {contact.address && (
                                <div>
                                    <dt className="text-[12px] font-medium text-slate-500">Address</dt>
                                    <dd className="mt-0.5 whitespace-pre-line text-[13.5px] leading-relaxed text-slate-700">{contact.address}</dd>
                                </div>
                            )}
                            {contact.telephone && (
                                <div>
                                    <dt className="text-[12px] font-medium text-slate-500">Telephone</dt>
                                    <dd className="mt-0.5 text-[13.5px] text-primary">{contact.telephone}</dd>
                                </div>
                            )}
                            {contact.email && (
                                <div>
                                    <dt className="text-[12px] font-medium text-slate-500">Email</dt>
                                    <dd className="mt-0.5 break-words text-[13.5px] text-primary">{contact.email}</dd>
                                </div>
                            )}
                            {contact.extra.map((line) => (
                                <div key={line}>
                                    <dd className="text-[13.5px] leading-relaxed text-slate-700">{line}</dd>
                                </div>
                            ))}
                            {!contact.address && !contact.telephone && !contact.email && contact.extra.length === 0 && (
                                <div>
                                    <dd className="text-[13.5px] text-slate-400">No contact information available.</dd>
                                </div>
                            )}
                        </dl>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <h2 className="border-b border-slate-100 px-5 py-3.5 text-[15px] font-bold text-slate-900">
                            Referral performance
                        </h2>
                        <div className="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-3">
                            <KpiCard label="Total referrals" value={total} icon="sync_alt" />
                            <KpiCard label="Active" value={active} tone="attention" icon="pending_actions" />
                            <KpiCard label="Completed" value={completed} tone="positive" icon="task_alt" />
                        </div>

                            <div className="mt-4">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-[12px] font-medium text-slate-500">Referrals completed</p>
                                    <p className="text-[12.5px] font-semibold text-emerald-600">{completionPercent}%</p>
                                </div>
                                <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-emerald-500"
                                        style={{ width: `${completionPercent}%` }}
                                        role="progressbar"
                                        aria-valuenow={completionPercent}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-label="Referrals completed"
                                    />
                                </div>
                            </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <h2 className="border-b border-slate-100 px-5 py-3.5 text-[15px] font-bold text-slate-900">
                            Location
                        </h2>
                        <div className="p-2">
                            <AgencyMapView
                                mapLink={stakeholder.map_link}
                                latitude={stakeholder.latitude}
                                longitude={stakeholder.longitude}
                                locationQuery={stakeholder.location_query}
                                agencyName={stakeholder.name}
                                embedHeight="260px"
                            />
                        </div>
                        {stakeholder.location_query && (
                            <p className="flex items-start gap-1.5 border-t border-slate-100 px-4 py-3 text-[12.5px] text-slate-500">
                                <span aria-hidden="true" className="material-symbols-outlined mt-px text-[16px]">location_on</span>
                                {stakeholder.location_query}
                            </p>
                        )}
                    </section>
                </aside>
            </div>
        </AppLayout>
    );
}
