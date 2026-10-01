import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';
import UnifiedTimeline, { TimelineEmptyState } from '@/Components/Timeline';
import ReferralStamp from '@/Components/ReferralStamp';
import ClientStepBar from '@/Components/ClientStepBar';
import OfficeChecklist from '@/Components/CaseProgressChecklist';
import ActionNeededBanner from '@/Components/ActionNeededBanner';
import { formatClientLongDate as formatLongDate } from '@/Components/clientDates';

/**
 * My Cases — case record detail.
 *
 * Reads like the public tracking portal's official case logbook: a navy record
 * header answers "where is my case right now", then one chapter per partner
 * office shows what that office has done, in ledger form. The complete
 * chronological record sits in a sticky sidebar on wide screens.
 */

/** One chapter per partner office — accordion: header always visible, body collapsible. */
function AgencyChapter({ agency, events, defaultOpen = false }) {
    const [open, setOpen] = useState(defaultOpen);
    const isRejected = agency.status === 'REJECTED';
    const services = agency.services ?? [];

    return (
        <section
            id={agency.referralId ? `agency-${agency.referralId}` : undefined}
            className="scroll-mt-24 rounded-md border border-slate-300 bg-white shadow-sm"
        >
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center justify-between gap-3 bg-slate-50 px-5 py-3.5 text-left transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                aria-expanded={open}
            >
                <div className="flex min-w-0 items-center gap-3">
                    <span
                        aria-hidden="true"
                        className={`material-symbols-outlined text-[18px] transition-transform duration-200 ${open ? 'rotate-90' : ''} text-slate-400`}
                    >
                        chevron_right
                    </span>
                    <h3 className="truncate font-headline text-sm font-extrabold tracking-tight text-slate-800">{agency.name}</h3>
                </div>
                <ReferralStamp status={agency.status} />
            </button>

            <div className={`grid transition-[grid-template-rows] duration-200 ease-out ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                <div className="overflow-hidden">
                    <div className="border-t border-slate-200 px-5 py-5">
                        <ClientStepBar steps={agency.steps ?? []} />
                        {isRejected && (
                            <p className="mt-4 text-[13px] leading-relaxed text-slate-600">
                                This office was unable to process the referral. Your case manager will advise you on the next steps.
                            </p>
                        )}

                        {(services.length > 0 || agency.latestMilestoneLabel) && (
                            <div className="mt-4 space-y-2">
                                {agency.latestMilestoneLabel && (
                                    <p className="text-[13px] text-slate-600">
                                        <span className="font-semibold text-slate-800">Latest:</span> {agency.latestMilestoneLabel}
                                    </p>
                                )}
                                {services.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5">
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
                        )}

                        {events.length > 0 && (
                            <div className="mt-5">
                                <UnifiedTimeline variant="ledger" items={events} />
                            </div>
                        )}

                        {agency.status === 'PENDING' && (
                            <p className="mt-5 border-t border-slate-300 pt-3 text-[13px] text-slate-500">
                                Waiting for {agency.name} to receive your referral. Updates will appear here.
                            </p>
                        )}

                        {agency.milestonesUrl && (
                            <div className="mt-4 flex justify-end border-t border-slate-300 pt-3">
                                <Link
                                    href={agency.milestonesUrl}
                                    className="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
                                >
                                    View all updates from {agency.name}
                                    <span aria-hidden="true" className="material-symbols-outlined text-[14px]">arrow_right_alt</span>
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

/** Compact OFW profile summary drawn from the case overview payload. */
function OfwProfile({ ofw, workHistory, nextOfKin }) {
    const hasAny = ofw || workHistory || nextOfKin;

    return hasAny ? (
        <section className="mt-4 rounded-md border border-slate-300 bg-white px-5 py-4 shadow-sm">
            <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-600">Case overview</h2>
            <dl className="mt-3 grid grid-cols-1 gap-3 text-[13px] sm:grid-cols-2">
                {ofw?.fullName && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">OFW</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">{ofw.fullName}</dd>
                    </div>
                )}
                {ofw?.gender && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Sex</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">
                            {ofw.gender.charAt(0).toUpperCase() + ofw.gender.slice(1).toLowerCase()}
                        </dd>
                    </div>
                )}
                {ofw?.dateOfBirth && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Date of birth</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">{formatLongDate(ofw.dateOfBirth)}</dd>
                    </div>
                )}
                {ofw?.homeAddress && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Home address</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">{ofw.homeAddress}</dd>
                    </div>
                )}
                {workHistory?.lastCountry && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Country of deployment</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">{workHistory.lastCountry}</dd>
                    </div>
                )}
                {workHistory?.lastPosition && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Position</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">{workHistory.lastPosition}</dd>
                    </div>
                )}
                {nextOfKin?.fullName && (
                    <div>
                        <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Emergency contact</dt>
                        <dd className="mt-0.5 font-medium text-slate-800">
                            {nextOfKin.fullName}
                            {nextOfKin.relationship && <span className="text-slate-500"> · {nextOfKin.relationship}</span>}
                        </dd>
                    </div>
                )}
            </dl>
        </section>
    ) : null;
}

export default function CaseDetail({
    trackingId,
    trackedCase,
    caseOverview,
    milestoneTimeline = [],
    completionPercentage = 0,
    trackingAgencies = [],
    rejectedCount = 0,
    clientSwimlaneTimeline = null,
}) {
    const totalAgencies = trackingAgencies.length;
    const completedAgencies = trackingAgencies.filter((a) => a.status === 'COMPLETED').length;

    const statusLine = useMemo(() => {
        const rejectedNote = rejectedCount > 0
            ? ` ${rejectedCount} ${rejectedCount === 1 ? 'office was' : 'offices were'} unable to assist.`
            : '';

        switch (trackedCase?.status) {
            case 'RESOLVED':
                return `This case has been resolved.${rejectedNote}`;
            case 'BEING_PREPARED':
                return 'This case is still being prepared by the One Window Bayanihan team.';
            case 'ARCHIVED':
                return 'This case has been archived.';
            case 'IN_PROGRESS':
                if (totalAgencies === 0) {
                    return 'Your case is being reviewed. Referrals to partner offices will appear here.';
                }
                return `In progress — ${completedAgencies} of ${totalAgencies} partner ${totalAgencies === 1 ? 'office has' : 'offices have'} completed their part.${rejectedNote}`;
            default:
                return 'Case status is currently unavailable.';
        }
    }, [trackedCase, totalAgencies, completedAgencies, rejectedCount]);

    const eventsByReferral = useMemo(() => {
        const map = {};
        for (const item of milestoneTimeline) {
            if (!item.referralId) continue;
            (map[item.referralId] ??= []).push(item);
        }
        return map;
    }, [milestoneTimeline]);

    const { ofw, workHistory, nextOfKin } = caseOverview ?? {};

    return (
        <OfwLayout title={trackingId ? `Case — ${trackingId}` : 'Case Details'}>
            {/* Back to My Cases */}
            <Link
                href={route('ofw.dashboard')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to My Cases
            </Link>

            {/* Case record header */}
            <header className="mt-4 rounded-lg bg-primary px-6 py-8 text-white shadow-2xl sm:px-8">
                <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Case record</p>
                <h1 className="mt-2 font-headline text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {trackingId ?? 'Case details'}
                </h1>
                <p className="mt-1.5 text-sm text-primary-fixed/90">
                    {trackedCase?.clientName ?? 'Unknown'} · Case opened {formatLongDate(trackedCase?.createdAt)}
                </p>
                {trackedCase?.categories?.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {trackedCase.categories.map((category) => (
                            <span key={category.name} className="border border-white/30 px-2 py-1 text-[11px] font-semibold">
                                {category.name}
                            </span>
                        ))}
                    </div>
                )}

                <p className="mt-6 max-w-2xl font-headline text-lg font-bold leading-snug sm:text-xl">
                    {statusLine}
                </p>

                {totalAgencies > 0 && (
                    <OfficeChecklist
                        agencies={trackingAgencies}
                        milestoneTimeline={milestoneTimeline}
                        clientSwimlaneTimeline={clientSwimlaneTimeline}
                        caption={`Last updated ${formatLongDate(trackedCase?.updatedAt)} · ${completionPercentage}% of processing complete`}
                        formatDate={formatLongDate}
                    />
                )}
            </header>

            {/* Two-column layout: main content + complete case history sidebar */}
            <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
                {/* Left column — main content */}
                <div className="min-w-0">
                    {caseOverview?.narrative && (
                        <section className="rounded-md border border-slate-300 bg-white px-5 py-4 shadow-sm">
                            <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-600">Case summary</h2>
                            <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{caseOverview.narrative}</p>
                        </section>
                    )}

                    <ActionNeededBanner agencies={trackingAgencies} />

                    <section className="mt-8">
                        <h2 className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                            <span className="h-2 w-2 rounded-full bg-primary"></span>
                            What each office has done
                        </h2>
                        {totalAgencies > 0 ? (
                            <div className="mt-3 space-y-2">
                                {trackingAgencies.map((agency, index) => (
                                    <AgencyChapter
                                        key={agency.referralId ?? agency.name}
                                        agency={agency}
                                        events={eventsByReferral[agency.referralId] ?? []}
                                        defaultOpen={index === 0 || agency.status === 'PROCESSING' || agency.status === 'FOR_COMPLIANCE'}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="mt-3">
                                <TimelineEmptyState
                                    icon="hourglass_empty"
                                    title="No partner offices assigned yet"
                                    action={
                                        <p className="mx-auto mt-1 max-w-xs text-[13px] text-slate-500">
                                            Your case manager is reviewing the case. Referrals will appear here once they are sent.
                                        </p>
                                    }
                                />
                            </div>
                        )}
                    </section>

                    <OfwProfile ofw={ofw} workHistory={workHistory} nextOfKin={nextOfKin} />
                </div>

                {/* Right column — complete case history (sticky on desktop) */}
                {milestoneTimeline.length > 0 && (
                    <aside className="lg:sticky lg:top-24 lg:self-start">
                        <section className="rounded-md border border-slate-300 bg-white shadow-sm">
                            <header className="rounded-t-md border-b border-slate-300 bg-blue-50/60 px-4 py-3">
                                <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-600">
                                    Complete case history
                                </h2>
                                <p className="mt-0.5 text-[11px] text-slate-400">
                                    {milestoneTimeline.length} {milestoneTimeline.length === 1 ? 'entry' : 'entries'}
                                </p>
                            </header>
                            <UnifiedTimeline
                                variant="ledger"
                                items={milestoneTimeline}
                                listClassName="max-h-[calc(100vh-12rem)] overflow-y-auto px-4 pb-4 pt-2 owb-scroll-wide rounded-b-md"
                            />
                        </section>
                    </aside>
                )}
            </div>
        </OfwLayout>
    );
}
