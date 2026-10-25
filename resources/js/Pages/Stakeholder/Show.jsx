import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import AgencyMapView from '@/Components/AgencyMapView';
import { SectionCard } from '@/Components/Dashboard/primitives';

/**
 * Stakeholder details — partner office record.
 *
 * Mirrors the case record detail page: a navy record header answers "who is
 * this office and how is it performing", then one card per facet of the
 * office. Referral performance sits in a sticky sidebar on wide screens.
 */

function AgencyLogo({ agency, size = 'h-14 w-14' }) {
    const [hasError, setHasError] = useState(false);

    if (hasError || !agency.logo_url) {
        return (
            <div className={`${size} flex items-center justify-center bg-white text-base font-extrabold text-primary`}>
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

/** Stat row used by the sticky "Referral performance" sidebar card. */
function StatRow({ label, value, tone = 'text-slate-900' }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">{label}</dt>
            <dd className={`text-xl font-black leading-none ${tone}`}>{value}</dd>
        </div>
    );
}

/** One covered service — name, description, lead time, and required documents. */
function ServiceBlock({ service }) {
    const requirements = service.requirements ?? [];

    return (
        <div className="rounded-md border border-slate-200 bg-slate-50/60 p-4">
            <p className="text-[13px] font-bold text-slate-800">{service.name}</p>

            {service.description && (
                <p className="mt-1 text-[12px] leading-relaxed text-slate-600">{service.description}</p>
            )}

            {service.processing_days && (
                <p className="mt-2 text-[11px] font-semibold text-primary">
                    Processing time: {service.processing_days} day{service.processing_days > 1 ? 's' : ''}
                </p>
            )}

            {requirements.length > 0 && (
                <div className="mt-3 border-t border-slate-200 pt-2.5">
                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                        Required documents
                    </p>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {requirements.map((req) => (
                            <span
                                key={req.id}
                                className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-medium text-primary"
                            >
                                {req.name}
                            </span>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

export default function StakeholderShow({ stakeholder, stats }) {
    const services = stakeholder.services ?? [];

    const { total_referrals: total = 0, active_referrals: active = 0, completed_referrals: completed = 0 } = stats ?? {};

    // Segments are drawn from the three counts we already have: completed,
    // active, then whatever is left over (rejected / archived).
    const segments = useMemo(() => {
        if (total <= 0) return [];

        const remaining = Math.max(total - active - completed, 0);

        return [
            { key: 'completed', count: completed, className: 'bg-emerald-300', label: 'completed' },
            { key: 'active', count: active, className: 'bg-amber-300/60', label: 'active' },
            { key: 'other', count: remaining, className: 'bg-white/30', label: 'closed without completion' },
        ].filter((segment) => segment.count > 0);
    }, [total, active, completed]);

    const completionPercent = total > 0 ? Math.round((completed / total) * 100) : 0;

    return (
        <AppLayout title={stakeholder.short ?? stakeholder.name}>
            <Head title={stakeholder.short ?? stakeholder.name} />

            {/* Back to Stakeholders */}
            <Link
                href={route('stakeholders.index')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Stakeholders
            </Link>

            {/* Partner office record header */}
            <header className="mt-4 rounded-lg bg-primary px-6 py-8 text-white shadow-2xl sm:px-8">
                <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Partner office</p>

                <div className="mt-3 flex items-start gap-4">
                    <div className="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-white">
                        <AgencyLogo agency={stakeholder} />
                    </div>
                    <div className="min-w-0">
                        <h1 className="font-headline text-2xl font-extrabold tracking-tight sm:text-3xl">
                            {stakeholder.name ?? 'Partner office'}
                        </h1>
                        <p className="mt-1 text-sm text-primary-fixed/90">
                            {stakeholder.short}
                            {stakeholder.contact_info && <span className="text-primary-fixed/70"> · {stakeholder.contact_info}</span>}
                        </p>
                    </div>
                </div>

                <div className="mt-3 flex flex-wrap gap-2">
                    <span className="border border-white/30 px-2 py-1 text-[11px] font-semibold">
                        {services.length} {services.length === 1 ? 'service' : 'services'}
                    </span>
                    {!stakeholder.is_active && (
                        <span className="border border-amber-300/60 px-2 py-1 text-[11px] font-semibold text-amber-200">
                            Inactive
                        </span>
                    )}
                </div>

                {total > 0 && (
                    <div className="mt-6">
                        <div
                            className="flex h-1.5 gap-1"
                            role="img"
                            aria-label={`${completionPercent}% of referrals completed`}
                        >
                            {segments.map((segment) => (
                                <span
                                    key={segment.key}
                                    title={`${segment.count} ${segment.label}`}
                                    style={{ flexGrow: segment.count }}
                                    className={`rounded-sm ${segment.className}`}
                                >
                                    <span className="sr-only">{`${segment.count} ${segment.label}`}</span>
                                </span>
                            ))}
                        </div>
                        <p className="mt-2 text-[11px] text-primary-fixed-dim/80">
                            {completionPercent}% of referrals completed · {total} total
                        </p>
                    </div>
                )}
            </header>

            {/* Two-column layout: main content + referral performance sidebar */}
            <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
                {/* Left column — main content */}
                <div className="min-w-0">
                    {stakeholder.description && (
                        <SectionCard title="About">
                            <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
                                {stakeholder.description}
                            </p>
                        </SectionCard>
                    )}

                    <section className={stakeholder.description ? 'mt-8' : ''}>
                        <h2 className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                            <span className="h-2 w-2 rounded-full bg-primary"></span>
                            Services covered
                        </h2>

                        {services.length > 0 ? (
                            <div className="mt-3 space-y-3">
                                {services.map((service) => (
                                    <ServiceBlock key={service.id} service={service} />
                                ))}
                            </div>
                        ) : (
                            <div className="mt-3 rounded-md border border-dashed border-slate-300 bg-slate-50/50 px-5 py-8 text-center">
                                <span className="material-symbols-outlined text-3xl text-slate-300">inventory_2</span>
                                <p className="mt-2 text-sm font-semibold text-slate-700">No services configured yet</p>
                                <p className="mx-auto mt-1 max-w-xs text-[13px] text-slate-500">
                                    This office has not published any services. Cases cannot be referred here until services are added.
                                </p>
                            </div>
                        )}
                    </section>
                </div>

                {/* Right column — referral performance + location (sticky on desktop) */}
                <aside className="lg:sticky lg:top-24 lg:self-start">
                    <div className="space-y-6">
                        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
                            <header className="rounded-t-xl border-b border-slate-100 bg-surface-container-low px-4 py-3">
                                <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-primary">
                                    Referral performance
                                </h2>
                                <p className="mt-0.5 text-[11px] text-slate-400">
                                    {total} {total === 1 ? 'referral' : 'referrals'} received
                                </p>
                            </header>
                            <dl className="space-y-3 px-4 py-4">
                                <StatRow label="Total" value={total} />
                                <StatRow label="Active" value={active} tone={active > 0 ? 'text-amber-600' : 'text-slate-300'} />
                                <StatRow label="Completed" value={completed} tone={completed > 0 ? 'text-emerald-600' : 'text-slate-300'} />
                            </dl>
                        </section>

                        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
                            <header className="rounded-t-xl border-b border-slate-100 bg-surface-container-low px-4 py-3">
                                <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-primary">Location</h2>
                            </header>
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
                        </section>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
