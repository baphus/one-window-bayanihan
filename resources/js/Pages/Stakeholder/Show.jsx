import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

function InfoItem({ label, value }) {
    return (
        <div className="rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2">
            <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">{label}</p>
            <p className="mt-1 text-[12px] font-semibold text-slate-700">{value}</p>
        </div>
    );
}

export default function StakeholderShow({ stakeholder, stats }) {
    const services = stakeholder.services || [];
    const mapSrc = stakeholder.location_query
        ? `https://maps.google.com/maps?q=${encodeURIComponent(stakeholder.location_query)}&output=embed`
        : null;

    return (
        <AppLayout title={stakeholder.short}>
            <Head title={stakeholder.short} />

            <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 mb-4">
                <Link href={route('stakeholders.index')} className="hover:text-indigo-600 transition">Stakeholders</Link>
                <span className="mx-2">&gt;</span>
                <span>{stakeholder.short}</span>
            </div>

            <div className="flex items-start justify-between gap-4 mb-6">
                <h1 className="text-2xl font-bold text-slate-900">Stakeholder Details</h1>
                <Link
                    href={route('stakeholders.index')}
                    className="inline-flex items-center gap-1 rounded-md border border-[#cbd5e1] bg-white px-3 py-2 text-[11px] font-bold text-slate-700 hover:bg-slate-50"
                >
                    <span className="material-symbols-outlined text-[16px]">arrow_back</span>
                    Back
                </Link>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
                <main className="xl:col-span-7 space-y-6">
                    <section className="rounded-lg border border-[#d8dee8] bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-4">
                            <div className="h-14 w-14 overflow-hidden rounded-full border border-[#d8dee8] bg-white shrink-0">
                                {stakeholder.logo_url ? (
                                    <img src={stakeholder.logo_url} alt={`${stakeholder.short} logo`} className="h-full w-full object-contain p-[2px]" />
                                ) : (
                                    <div className="h-full w-full flex items-center justify-center bg-blue-100 text-blue-900 text-sm font-bold">
                                        {stakeholder.short?.charAt(0)}
                                    </div>
                                )}
                            </div>
                            <div>
                                <p className="text-[16px] font-bold text-slate-800">{stakeholder.name}</p>
                                <p className="text-[10px] font-extrabold uppercase tracking-[0.1em] text-slate-500">{stakeholder.short}</p>
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <InfoItem label="Contact" value={stakeholder.contact_info || 'N/A'} />
                            <InfoItem label="Number of Services" value={String(services.length)} />
                            <InfoItem label="Total Referrals" value={String(stats.total_referrals)} />
                            <InfoItem label="Active Referrals" value={String(stats.active_referrals)} />
                            <InfoItem label="Completed Referrals" value={String(stats.completed_referrals)} />
                        </div>
                    </section>

                    <section className="rounded-lg border border-[#d8dee8] bg-white p-6 shadow-sm">
                        <h3 className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-slate-500 mb-3">Services ({services.length})</h3>
                        {services.length ? (
                            <div className="space-y-3">
                                {services.map((service) => (
                                    <div key={service.id} className="rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] p-4">
                                        <p className="text-[13px] font-bold text-slate-800">{service.name}</p>
                                        {service.description && (
                                            <p className="mt-1 text-[12px] leading-5 text-slate-600">{service.description}</p>
                                        )}
                                        {service.pivot?.processing_days && (
                                            <p className="mt-2 text-[11px] font-semibold text-indigo-600">
                                                Processing Time: {service.pivot.processing_days} day{service.pivot.processing_days > 1 ? 's' : ''}
                                            </p>
                                        )}
                                        {service.pivot?.required_documents && (() => {
                                            const docs = typeof service.pivot.required_documents === 'string'
                                                ? (() => { try { return JSON.parse(service.pivot.required_documents); } catch { return []; } })()
                                                : service.pivot.required_documents;
                                            return (
                                                <div className="mt-2">
                                                    <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Required Documents</p>
                                                    {Array.isArray(docs) && docs.length ? (
                                                        <ul className="mt-1 list-disc pl-5 text-[11px] leading-5 text-slate-600">
                                                            {docs.map((doc, i) => (
                                                                <li key={`${service.id}-${i}`}>{doc}</li>
                                                            ))}
                                                        </ul>
                                                    ) : (
                                                        <p className="mt-1 text-[11px] text-slate-500">No required documents listed.</p>
                                                    )}
                                                </div>
                                            );
                                        })()}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-[3px] border border-dashed border-[#cbd5e1] p-4 text-[12px] text-slate-500">
                                No configured services for this stakeholder.
                            </div>
                        )}
                    </section>
                </main>

                <aside className="xl:col-span-5 space-y-6">
                    <section className="rounded-lg border border-[#d8dee8] bg-white p-6 shadow-sm">
                        <h3 className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-slate-500 mb-3">Google Maps Location</h3>
                        {mapSrc ? (
                            <div className="overflow-hidden rounded-[3px] border border-[#d8dee8]">
                                <iframe
                                    title={`${stakeholder.name} location`}
                                    src={mapSrc}
                                    className="h-[420px] w-full"
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                />
                            </div>
                        ) : (
                            <div className="rounded-[3px] border border-dashed border-[#cbd5e1] p-4 text-[12px] text-slate-500">
                                No location data available.
                            </div>
                        )}
                    </section>

                    {stakeholder.description && (
                        <section className="rounded-lg border border-[#d8dee8] bg-white p-6 shadow-sm">
                            <h3 className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-slate-500 mb-3">About</h3>
                            <p className="text-[12px] leading-6 text-slate-600">{stakeholder.description}</p>
                        </section>
                    )}
                </aside>
            </div>
        </AppLayout>
    );
}
