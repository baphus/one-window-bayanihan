import { Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';

const REFERRAL_STATUS = {
    PENDING: { label: 'Awaiting Receipt', bg: 'bg-slate-50', text: 'text-slate-600', border: 'border-slate-300' },
    PROCESSING: { label: 'In Process', bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-300' },
    FOR_COMPLIANCE: { label: 'Needs Documents', bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-300' },
    COMPLETED: { label: 'Completed', bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-300' },
    REJECTED: { label: 'Unable to Assist', bg: 'bg-red-50', text: 'text-red-600', border: 'border-red-300' },
};

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function formatShortDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function ProgressBar({ percentage }) {
    return (
        <div className="w-full">
            <div className="flex items-center justify-between text-sm">
                <span className="font-medium text-gray-700">Overall Progress</span>
                <span className="font-bold text-blue-700">{percentage}%</span>
            </div>
            <div className="mt-2 h-3 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    className="h-full rounded-full bg-blue-600 transition-all duration-500 ease-out"
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

function AgencyCard({ agency }) {
    const status = REFERRAL_STATUS[agency.status] ?? REFERRAL_STATUS.PENDING;
    const services = agency.services ?? [];

    return (
        <div className={`rounded-lg border bg-white p-4 shadow-sm ${status.border}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <h3 className="text-sm font-bold text-gray-900">{agency.name}</h3>
                    {agency.latestMilestone && (
                        <p className="mt-1 text-xs text-gray-500">
                            Latest: {agency.latestMilestone}
                        </p>
                    )}
                    {services.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {services.map((svc) => (
                                <span
                                    key={svc}
                                    className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-medium text-blue-700"
                                >
                                    {svc}
                                </span>
                            ))}
                        </div>
                    )}
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${status.bg} ${status.text}`}>
                    {status.label}
                </span>
            </div>
        </div>
    );
}

function MilestoneTimeline({ milestones }) {
    if (!milestones?.length) return null;

    return (
        <div className="space-y-0">
            {milestones.map((item, index) => (
                <div key={`${item.date}-${index}`} className="relative flex gap-4 pb-6 last:pb-0">
                    {/* Timeline connector */}
                    {index < milestones.length - 1 && (
                        <div className="absolute left-[11px] top-6 bottom-0 w-px bg-gray-200" />
                    )}
                    {/* Dot */}
                    <div className="relative z-10 mt-1 flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full bg-blue-100">
                        <span className="material-symbols-outlined text-[12px] text-blue-600">
                            {item.type === 'case_closed' ? 'verified' :
                             item.type === 'referral_sent' ? 'send' :
                             item.type === 'milestone_added' ? 'flag' :
                             'circle'}
                        </span>
                    </div>
                    {/* Content */}
                    <div className="min-w-0 flex-1 pt-0.5">
                        <p className="text-sm font-medium text-gray-900">{item.title}</p>
                        {item.description && (
                            <p className="mt-0.5 text-xs text-gray-500">{item.description}</p>
                        )}
                        <p className="mt-1 text-xs text-gray-400">{formatShortDate(item.date)}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function CaseDetail({
    trackingId,
    trackedCase,
    caseOverview,
    milestoneTimeline = [],
    completionPercentage = 0,
    trackingAgencies = [],
}) {
    return (
        <OfwLayout title={`Case — ${trackingId ?? 'Details'}`}>
            {/* Back button */}
            <Link
                href={route('ofw.dashboard')}
                className="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900"
            >
                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to My Cases
            </Link>

            {/* Case header */}
            <div className="mt-4 rounded-lg bg-blue-900 px-6 py-6 text-white shadow">
                <p className="text-xs font-bold uppercase tracking-wider text-blue-200">Case Record</p>
                <h1 className="mt-1 font-mono text-xl font-bold">{trackingId}</h1>
                {trackedCase && (
                    <p className="mt-1 text-sm text-blue-200">
                        {trackedCase.clientName} · Opened {formatDate(trackedCase.createdAt)}
                    </p>
                )}
                {trackedCase?.categories?.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {trackedCase.categories.map((cat) => (
                            <span
                                key={cat.name}
                                className="rounded border border-white/30 px-2 py-0.5 text-xs font-medium"
                            >
                                {cat.name}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            {/* Progress bar */}
            <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <ProgressBar percentage={completionPercentage} />
            </div>

            {/* Case overview / narrative */}
            {caseOverview?.narrative && (
                <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 className="flex items-center gap-2 text-sm font-bold text-gray-900">
                        <span className="material-symbols-outlined text-[18px] text-blue-600">description</span>
                        Case Summary
                    </h2>
                    <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-gray-700">
                        {caseOverview.narrative}
                    </p>
                </div>
            )}

            {/* Personal info summary */}
            {caseOverview?.personalInfo && (
                <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 className="flex items-center gap-2 text-sm font-bold text-gray-900">
                        <span className="material-symbols-outlined text-[18px] text-blue-600">person</span>
                        Personal Information
                    </h2>
                    <dl className="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        {caseOverview.personalInfo.name && (
                            <div>
                                <dt className="text-xs text-gray-500">Name</dt>
                                <dd className="font-medium text-gray-900">{caseOverview.personalInfo.name}</dd>
                            </div>
                        )}
                        {caseOverview.personalInfo.country && (
                            <div>
                                <dt className="text-xs text-gray-500">Country of Deployment</dt>
                                <dd className="font-medium text-gray-900">{caseOverview.personalInfo.country}</dd>
                            </div>
                        )}
                        {caseOverview.personalInfo.employer && (
                            <div>
                                <dt className="text-xs text-gray-500">Employer</dt>
                                <dd className="font-medium text-gray-900">{caseOverview.personalInfo.employer}</dd>
                            </div>
                        )}
                    </dl>
                </div>
            )}

            {/* Referral agency cards */}
            {trackingAgencies.length > 0 && (
                <div className="mt-6">
                    <h2 className="flex items-center gap-2 text-sm font-bold text-gray-900">
                        <span className="material-symbols-outlined text-[18px] text-blue-600">apartment</span>
                        Partner Agencies
                    </h2>
                    <div className="mt-3 space-y-3">
                        {trackingAgencies.map((agency) => (
                            <AgencyCard
                                key={agency.referralId ?? agency.name}
                                agency={agency}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* Milestone timeline */}
            {milestoneTimeline.length > 0 && (
                <div className="mt-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 className="flex items-center gap-2 text-sm font-bold text-gray-900">
                        <span className="material-symbols-outlined text-[18px] text-blue-600">timeline</span>
                        Case Timeline
                    </h2>
                    <div className="mt-4">
                        <MilestoneTimeline milestones={milestoneTimeline} />
                    </div>
                </div>
            )}
        </OfwLayout>
    );
}
