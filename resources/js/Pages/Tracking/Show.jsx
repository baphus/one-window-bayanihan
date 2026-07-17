import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TrackingNotFoundState from '@/Components/TrackingNotFoundState';
import ChatBot from '@/Components/ChatBot';

/**
 * Track Your Case — results page.
 *
 * Reads as an official case logbook: a navy record header answers "where is
 * my case right now", then one chapter per partner office shows what that
 * office has done, in ledger form. The complete chronological record sits
 * behind a disclosure at the end.
 */

const REFERRAL_STAMP = {
  PENDING:        { label: 'Awaiting receipt',  border: 'border-outline',            text: 'text-on-surface-variant' },
  PROCESSING:     { label: 'In process',        border: 'border-primary',            text: 'text-primary' },
  FOR_COMPLIANCE: { label: 'Needs documents',   border: 'border-on-tertiary-fixed-variant', text: 'text-on-tertiary-fixed-variant' },
  COMPLETED:      { label: 'Completed',         border: 'border-secondary',          text: 'text-secondary' },
  REJECTED:       { label: 'Unable to assist',  border: 'border-error',              text: 'text-error' },
};

const EVENT_ICON = {
  case_opened: 'folder_open',
  referral_sent: 'send',
  referral_status_changed: 'sync_alt',
  milestone_added: 'flag',
  compliance_fulfilled: 'task_alt',
  case_closed: 'verified',
  case_reopened: 'restart_alt',
};

function formatLongDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-PH', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function formatShortDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-PH', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function formatTime(dateStr) {
  return new Date(dateStr).toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function relativeDays(dateStr) {
  const diffDays = Math.floor((Date.now() - new Date(dateStr).getTime()) / 86400000);
  if (diffDays <= 0) return 'today';
  if (diffDays === 1) return 'yesterday';
  if (diffDays < 30) return `${diffDays} days ago`;
  const months = Math.floor(diffDays / 30);
  return months === 1 ? 'about a month ago' : `about ${months} months ago`;
}

/** Two-column ledger row: fixed date column, entry on the right. */
function EventRow({ item }) {
  return (
    <li className="grid grid-cols-1 gap-x-5 gap-y-0.5 border-t border-outline-variant/60 py-3 first:border-t-0 sm:grid-cols-[7.5rem_1fr]">
      <div className="pt-0.5">
        <p className="font-mono text-xs tabular-nums text-on-surface-variant">{formatShortDate(item.date)}</p>
        <p className="hidden font-mono text-[11px] tabular-nums text-on-surface-variant/60 sm:block">{formatTime(item.date)}</p>
      </div>
      <div className="min-w-0">
        <div className="flex items-start gap-2">
          <span aria-hidden="true" className="material-symbols-outlined mt-px text-[16px] text-on-surface-variant/70">
            {EVENT_ICON[item.type] ?? 'flag'}
          </span>
          <div className="min-w-0">
            <p className="text-sm font-semibold leading-snug text-on-surface">{item.title}</p>
            {item.description && (
              <p className="mt-0.5 max-w-prose text-[13px] leading-relaxed text-on-surface-variant">{item.description}</p>
            )}
            <p className="mt-0.5 text-[11px] text-on-surface-variant/60">{relativeDays(item.date)}</p>
          </div>
        </div>
      </div>
    </li>
  );
}

function StepBar({ steps }) {
  const activeIndex = steps.findIndex((s) => s.state === 'active');
  const activeLabel = steps[activeIndex]?.label
    ?? (steps.length && steps.every((s) => s.state === 'complete') ? steps[steps.length - 1].label : null);
  const completedCount = steps.filter((s) => s.state === 'complete').length;
  const progressPercent = steps.length <= 1
    ? 0
    : activeIndex !== -1
      ? (activeIndex / (steps.length - 1)) * 100
      : (completedCount / steps.length) * 100;

  return (
    <div className="relative px-1">
      <div className="absolute left-0 right-0 top-[9px] h-px bg-outline-variant" />
      <div
        className="absolute left-0 top-[9px] h-px bg-primary transition-all duration-500 ease-out"
        style={{ width: `${progressPercent}%` }}
      />
      <ol
        className="relative z-10 grid"
        style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}
      >
        {steps.map((step) => {
          const isComplete = step.state === 'complete';
          const isActive = step.state === 'active';

          return (
            <li key={step.label} className="flex flex-col items-center">
              <span
                className={`flex h-[18px] w-[18px] items-center justify-center rounded-full ring-4 ring-surface-container-lowest ${
                  isComplete ? 'bg-primary text-white' :
                  isActive ? 'border-2 border-primary bg-white' :
                  'bg-surface-container-high'
                }`}
              >
                {isComplete && <span aria-hidden="true" className="material-symbols-outlined text-[11px] font-bold">check</span>}
                {isActive && <span className="h-1.5 w-1.5 rounded-full bg-primary motion-safe:animate-pulse" />}
              </span>
              <span
                className={`mt-1.5 hidden max-w-[90px] px-1 text-center text-[10px] font-semibold leading-tight tracking-tight sm:line-clamp-2 ${
                  isActive ? 'text-primary' : isComplete ? 'text-on-surface' : 'text-on-surface-variant/70'
                }`}
                title={step.label}
              >
                {step.label}
              </span>
            </li>
          );
        })}
      </ol>
      {/* Labels collide at phone widths — name only the current step there. */}
      {activeLabel && (
        <p className="mt-2 text-center text-[11px] font-semibold text-primary sm:hidden">
          Current step: {activeLabel}
        </p>
      )}
    </div>
  );
}

/** One chapter per partner office: stamp, step bar, that office's ledger. */
function AgencyChapter({ agency, events }) {
  const stamp = REFERRAL_STAMP[agency.status] ?? REFERRAL_STAMP.PENDING;
  const isRejected = agency.status === 'REJECTED';
  // Only unresolved requirements warrant an action block — fulfilled ones
  // already appear in the ledger as compliance_fulfilled entries.
  const pendingRequirements = (agency.compliance_requirements ?? []).filter((cr) => cr.status === 'PENDING');

  return (
    <section className="border border-outline-variant bg-surface-container-lowest">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant bg-surface-container-low px-5 py-3.5">
        <h3 className="font-headline text-sm font-extrabold tracking-tight text-on-surface">{agency.name}</h3>
        <span className={`border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] ${stamp.border} ${stamp.text}`}>
          {stamp.label}
        </span>
      </header>

      <div className="px-5 py-5">
        <StepBar steps={agency.steps ?? []} />
        {isRejected && (
          <p className="mt-4 text-[13px] leading-relaxed text-on-surface-variant">
            This office was unable to process the referral. Your case manager will advise you on the next steps.
          </p>
        )}

        {pendingRequirements.length > 0 && (
          <div className="mt-5 border border-on-tertiary-fixed-variant/30 bg-tertiary-fixed/30 p-4">
            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-on-tertiary-fixed-variant">
              Action needed — documents to prepare
            </p>
            <ul className="mt-2.5 space-y-2">
              {pendingRequirements.map((cr) => (
                <li key={cr.id} className="flex items-baseline justify-between gap-3 text-[13px]">
                  <span className="min-w-0">
                    <span className="font-semibold text-on-surface">{cr.requirement_name}</span>
                    <span className="text-on-surface-variant"> · {cr.service_name}</span>
                  </span>
                  <span className="shrink-0 text-[11px] font-semibold text-on-tertiary-fixed-variant">To submit</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {events.length > 0 && (
          <ul className="mt-5">
            {events.map((item, index) => (
              <EventRow key={`${item.date}-${index}`} item={item} />
            ))}
          </ul>
        )}

        {agency.status === 'PENDING' && (
          <p className="mt-5 border-t border-outline-variant/60 pt-3 text-[13px] text-on-surface-variant">
            Waiting for {agency.name} to receive your referral. Updates will appear here.
          </p>
        )}

        {agency.milestonesUrl && (
          <div className="mt-4 flex justify-end border-t border-outline-variant/60 pt-3">
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
    </section>
  );
}

export default function TrackingShow({
  trackingId,
  trackedCase,
  caseOverview,
  milestoneTimeline = [],
  trackingAgencies = [],
  caseNotifications,
  completionPercentage = 0,
  rejectedCount = 0,
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

  if (!trackedCase) {
    return (
      <div className="min-h-screen bg-surface font-body text-on-surface">
        <Head title="Tracking ID Not Found" />
        <AppHeader />
        <main className="mx-auto w-full max-w-xl px-4 pt-24 pb-12 sm:px-6">
          <TrackingNotFoundState description="We could not find a case matching this tracking ID. Please verify your ID and try again." />
        </main>
        <AppFooter />
        <ChatBot />
      </div>
    );
  }

  const feedbackNtfn = caseNotifications?.items?.find((n) => n.type === 'feedback_request');
  const showFeedback = completedAgencies > 0 && feedbackNtfn;

  return (
    <div className="min-h-screen bg-surface font-body text-on-surface">
      <Head title={`Case Record — ${trackingId}`} />
      <AppHeader />

      <main className="mx-auto w-full max-w-7xl px-4 pt-24 pb-16 sm:px-6">

        {/* Case record header — full width */}
        <header className="bg-primary px-6 py-8 text-white shadow-2xl sm:px-10 sm:py-10">
          <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Case record</p>
          <h1 className="mt-2 font-headline font-mono text-2xl font-extrabold tracking-tight sm:text-3xl">
            {trackingId}
          </h1>
          <p className="mt-1.5 text-sm text-primary-fixed/90">
            {trackedCase.clientName} · Case opened {formatLongDate(trackedCase.createdAt)}
          </p>
          {trackedCase.categories?.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {trackedCase.categories.map((category) => (
                <span key={category.name} className="border border-white/30 px-2 py-1 text-[11px] font-semibold">{category.name}</span>
              ))}
            </div>
          )}

          <p className="mt-6 max-w-2xl font-headline text-lg font-bold leading-snug sm:text-xl">
            {statusLine}
          </p>

          {totalAgencies > 0 && (
            <div className="mt-5">
              <div className="flex h-1.5 gap-1" role="img" aria-label={`${completionPercentage}% of processing complete`}>
                {trackingAgencies.map((a) => (
                  <span
                    key={a.referralId ?? a.name}
                    title={`${a.name} — ${(REFERRAL_STAMP[a.status] ?? REFERRAL_STAMP.PENDING).label}`}
                    className={`flex-1 ${
                      a.status === 'COMPLETED' ? 'bg-secondary-fixed-dim' :
                      a.status === 'REJECTED' ? 'bg-white/20' :
                      'bg-primary-fixed-dim/40'
                    }`}
                  />
                ))}
              </div>
              <p className="mt-2 text-[11px] text-primary-fixed-dim/80">
                Last updated {formatLongDate(trackedCase.updatedAt)} · {completionPercentage}% of processing complete
              </p>
            </div>
          )}
        </header>

        {/* Two-column layout: main content + case history sidebar */}
        <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[1fr_20rem]">

          {/* Left column — main content */}
          <div className="min-w-0">
            {/* Overview narrative */}
            {caseOverview?.narrative && (
              <section className="border border-outline-variant bg-surface-container-lowest px-5 py-4">
                <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Case summary</h2>
                <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-on-surface">{caseOverview.narrative}</p>
              </section>
            )}

            {/* Agency chapters */}
            <section className="mt-8">
              <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">
                What each office has done
              </h2>
              {totalAgencies > 0 ? (
                <div className="mt-3 space-y-5">
                  {trackingAgencies.map((agency) => (
                    <AgencyChapter
                      key={agency.referralId ?? agency.name}
                      agency={agency}
                      events={eventsByReferral[agency.referralId] ?? []}
                    />
                  ))}
                </div>
              ) : (
                <div className="mt-3 border border-outline-variant bg-surface-container-lowest px-5 py-8 text-center">
                  <p className="text-sm font-semibold text-on-surface">No partner offices assigned yet</p>
                  <p className="mx-auto mt-1 max-w-xs text-[13px] text-on-surface-variant">
                    Your case manager is reviewing the case. Referrals will appear here once they are sent.
                  </p>
                </div>
              )}
            </section>

            {/* Feedback request */}
            {showFeedback && (() => {
              return (
                <section className="mt-8 border border-outline-variant bg-surface-container-lowest px-5 py-5">
                  <h2 className="text-sm font-bold text-on-surface">How was the service?</h2>
                  <p className="mt-1 max-w-prose text-[13px] leading-relaxed text-on-surface-variant">
                    An office has completed its part of your case. A survey has been sent to your registered email address. Please check your inbox to provide your feedback.
                  </p>
                </section>
              );
            })()}
          </div>

          {/* Right column — Complete case history (sidebar on desktop) */}
          {milestoneTimeline.length > 0 && (
            <aside className="lg:sticky lg:top-24 lg:self-start">
              <section className="border border-outline-variant bg-surface-container-lowest">
                <header className="border-b border-outline-variant bg-surface-container-low px-4 py-3">
                  <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">
                    Complete case history
                  </h2>
                  <p className="mt-0.5 text-[11px] text-on-surface-variant/60">
                    {milestoneTimeline.length} {milestoneTimeline.length === 1 ? 'entry' : 'entries'}
                  </p>
                </header>
                <ul className="max-h-[calc(100vh-12rem)] overflow-y-auto px-4 pb-4 pt-2">
                  {milestoneTimeline.map((item, index) => (
                    <EventRow key={`${item.date}-${index}`} item={item} />
                  ))}
                </ul>
              </section>
            </aside>
          )}

        </div>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
