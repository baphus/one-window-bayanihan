import { useMemo } from 'react';
import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
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
  PENDING:        { label: 'Awaiting receipt',  border: 'border-slate-300',            text: 'text-slate-500' },
  PROCESSING:     { label: 'In process',        border: 'border-primary',              text: 'text-primary' },
  FOR_COMPLIANCE: { label: 'Needs documents',   border: 'border-amber-500',            text: 'text-amber-600' },
  COMPLETED:      { label: 'Completed',         border: 'border-emerald-500',          text: 'text-emerald-600' },
  REJECTED:       { label: 'Unable to assist',  border: 'border-red-400',              text: 'text-red-500' },
};

const EVENT_ICON = {
  case_opened: 'folder_open',
  referral_sent: 'send',
  referral_status_changed: 'sync_alt',
  milestone_added: 'flag',
  case_closed: 'verified',
  case_reopened: 'restart_alt',
};

const EVENT_ICON_COLOR = {
  case_opened: 'text-blue-500',
  referral_sent: 'text-emerald-500',
  referral_status_changed: 'text-amber-500',
  milestone_added: 'text-orange-500',
  case_closed: 'text-green-600',
  case_reopened: 'text-purple-500',
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
    <li className="grid grid-cols-1 gap-x-5 gap-y-0.5 border-t border-slate-200 py-3 first:border-t-0 sm:grid-cols-[7.5rem_1fr]">
      <div className="pt-0.5">
        <p className="font-mono text-xs tabular-nums text-slate-500">{formatShortDate(item.date)}</p>
        <p className="hidden font-mono text-[11px] tabular-nums text-slate-400 sm:block">{formatTime(item.date)}</p>
      </div>
      <div className="min-w-0">
        <div className="flex items-start gap-2">
          <span aria-hidden="true" className={`material-symbols-outlined mt-px text-[16px] ${EVENT_ICON_COLOR[item.type] ?? 'text-slate-400'}`}>
            {EVENT_ICON[item.type] ?? 'flag'}
          </span>
          <div className="min-w-0">
            <p className="text-sm font-semibold leading-snug text-slate-800">{item.title}</p>
            {item.description && (
              <p className="mt-0.5 max-w-prose text-[13px] leading-relaxed text-slate-600">{item.description}</p>
            )}
            <p className="mt-0.5 text-[11px] text-slate-400">{relativeDays(item.date)}</p>
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
      <div className="absolute left-0 right-0 top-[9px] h-px bg-slate-200" />
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
                className={`flex h-[18px] w-[18px] items-center justify-center rounded-full ring-4 ring-white ${
                  isComplete ? 'bg-primary text-white' :
                  isActive ? 'border-2 border-primary bg-white' :
                  'bg-slate-200'
                }`}
              >
                {isComplete && <span aria-hidden="true" className="material-symbols-outlined text-[11px] font-bold">check</span>}
                {isActive && <span className="h-1.5 w-1.5 rounded-full bg-primary motion-safe:animate-pulse" />}
              </span>
              <span
                className={`mt-1.5 hidden max-w-[90px] px-1 text-center text-[10px] font-semibold leading-tight tracking-tight sm:line-clamp-2 ${
                  isActive ? 'text-primary' : isComplete ? 'text-slate-800' : 'text-slate-400'
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

/** One chapter per partner office — accordion: header always visible, body collapsible. */
function AgencyChapter({ agency, events, defaultOpen = false }) {
  const [open, setOpen] = useState(defaultOpen);
  const stamp = REFERRAL_STAMP[agency.status] ?? REFERRAL_STAMP.PENDING;
  const isRejected = agency.status === 'REJECTED';
  const requirements = agency.requirements ?? [];

  return (
    <section className="rounded-md border border-slate-300 bg-white shadow-sm">
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
        <span className={`shrink-0 border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] ${stamp.border} ${stamp.text}`}>
          {stamp.label}
        </span>
      </button>

      <div className={`grid transition-[grid-template-rows] duration-200 ease-out ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
        <div className="overflow-hidden">
          <div className="border-t border-slate-200 px-5 py-5">
            <StepBar steps={agency.steps ?? []} />
            {isRejected && (
              <p className="mt-4 text-[13px] leading-relaxed text-slate-600">
                This office was unable to process the referral. Your case manager will advise you on the next steps.
              </p>
            )}

            {requirements.length > 0 && (
              <div className="mt-5 border border-slate-300 bg-slate-50 p-4">
                <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                  Required documents
                </p>
                <ul className="mt-2.5 space-y-1.5">
                  {requirements.map((req, idx) => (
                    <li key={idx} className="flex items-baseline gap-2 text-[13px]">
                      <span className="material-symbols-outlined text-[14px] text-slate-400 shrink-0 mt-0.5">chevron_right</span>
                      <span className="text-slate-700">{req}</span>
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

const CLIENT_REQUEST_TYPE_LABELS = {
  DOCUMENT_REQUEST: 'Documents requested',
  QUESTION: 'Question from the agency',
  INFORMATION_UPDATE: 'Information update requested',
};

const CLIENT_REQUEST_STATUS_LABELS = {
  OPEN: 'Awaiting your response',
  IN_PROGRESS: 'Being reviewed',
  CLIENT_RESPONDED: 'Response sent',
  COMPLETED: 'Completed',
  CANCELLED: 'Closed',
};

function formatRequestDate(dateStr) {
  if (!dateStr) return null;
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) return null;

  return date.toLocaleDateString('en-PH', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function requestStateCopy(state) {
  switch (state) {
    case 'expired':
      return {
        title: 'This request link has expired',
        description: 'You can ask the agency to send a new link. No email address is needed here.',
        icon: 'schedule',
        tone: 'amber',
      };
    case 'no_email':
      return {
        title: 'Online requests are unavailable',
        description: 'A registered email address is required to securely receive and respond to client requests.',
        icon: 'mail_outline',
        tone: 'slate',
      };
    case 'replacement':
      return {
        title: 'A new request link is needed',
        description: 'The previous link is no longer available. Ask the agency to send a replacement link.',
        icon: 'link_off',
        tone: 'amber',
      };
    default:
      return {
        title: 'No client requests yet',
        description: 'There are no document or information requests from a partner agency at this time.',
        icon: 'inbox',
        tone: 'slate',
      };
  }
}

function ClientRequestPanel({ clientRequestPanel }) {
  const [body, setBody] = useState('');
  const [replying, setReplying] = useState(false);
  const [requestingReplacement, setRequestingReplacement] = useState(false);
  const [error, setError] = useState('');

  const state = clientRequestPanel?.state ?? 'empty';
  const request = clientRequestPanel?.activeRequest;
  const actions = clientRequestPanel?.actions ?? {};
  const replyAction = typeof actions.reply === 'string' && actions.reply.trim() ? actions.reply : null;
  const replacementAction = typeof actions.requestReplacement === 'string' && actions.requestReplacement.trim()
    ? actions.requestReplacement
    : null;
  const canReply = state === 'ready' && request && replyAction
    && !['COMPLETED', 'CANCELLED'].includes(request.status);
  const stateCopy = request ? null : requestStateCopy(state);
  const dueDate = formatRequestDate(request?.due_at);

  function getErrorMessage(errors) {
    if (!errors) return 'We could not complete that request. Please try again.';
    return Object.values(errors).flat().find(Boolean) || 'We could not complete that request. Please try again.';
  }

  function handleReply(event) {
    event.preventDefault();
    const trimmed = body.trim();
    if (!canReply || !trimmed || replying) return;

    setError('');
    setReplying(true);
    router.post(replyAction, { body: trimmed }, {
      preserveScroll: true,
      onSuccess: () => {
        setBody('');
        router.reload({ only: ['clientRequestPanel'], preserveScroll: true });
      },
      onError: (errors) => setError(getErrorMessage(errors)),
      onFinish: () => setReplying(false),
    });
  }

  function handleReplacement() {
    if (!replacementAction || requestingReplacement) return;

    setError('');
    setRequestingReplacement(true);
    router.post(replacementAction, {}, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ['clientRequestPanel'], preserveScroll: true }),
      onError: (errors) => setError(getErrorMessage(errors)),
      onFinish: () => setRequestingReplacement(false),
    });
  }

  return (
    <div className="min-h-screen bg-slate-50 font-body text-slate-800">
      <Head title="Client Request" />
      <AppHeader />

      <main className="mx-auto w-full max-w-4xl px-4 pt-24 pb-16 sm:px-6">
        <header className="bg-primary px-6 py-8 text-white shadow-2xl sm:px-10 sm:py-10">
          <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Secure client request</p>
          <h1 className="mt-2 font-headline text-2xl font-extrabold tracking-tight sm:text-3xl">
            {request?.title ?? 'Client request'}
          </h1>
          {request?.agency_name && (
            <p className="mt-2 text-sm text-primary-fixed/90">From {request.agency_name}</p>
          )}
          <p className="mt-5 max-w-2xl text-sm leading-relaxed text-primary-fixed/90">
            This page shows only the information needed to respond to this request.
          </p>
        </header>

        {!request ? (
          <section className="mt-8 rounded-md border border-slate-300 bg-white px-5 py-8 text-center shadow-sm">
            <span className={`material-symbols-outlined text-4xl ${stateCopy.tone === 'amber' ? 'text-amber-600' : 'text-slate-400'}`}>
              {stateCopy.icon}
            </span>
            <h2 className="mt-3 text-sm font-bold text-slate-800">{stateCopy.title}</h2>
            <p className="mx-auto mt-1 max-w-md text-[13px] leading-relaxed text-slate-500">{stateCopy.description}</p>
            {replacementAction && (
              <button
                type="button"
                onClick={handleReplacement}
                disabled={requestingReplacement}
                className="mt-5 inline-flex items-center gap-2 bg-blue-900 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <span className="material-symbols-outlined text-[17px]">{requestingReplacement ? 'progress_activity' : 'refresh'}</span>
                {requestingReplacement ? 'Sending request…' : 'Request a new link'}
              </button>
            )}
            {replacementAction && <p className="mt-2 text-[11px] text-slate-500">This notifies the agency. No email destination is required.</p>}
          </section>
        ) : (
          <div className="mt-8 space-y-6">
            <section className="rounded-md border border-slate-300 bg-white px-5 py-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-300 pb-4">
                <div>
                  <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Request details</p>
                  <p className="mt-2 text-sm font-semibold text-slate-800">
                    {CLIENT_REQUEST_TYPE_LABELS[request.type] ?? 'Information requested'}
                  </p>
                  {request.agency_name && <p className="mt-1 text-[13px] text-slate-500">Agency: {request.agency_name}</p>}
                </div>
                <span className="border border-primary px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-primary">
                  {CLIENT_REQUEST_STATUS_LABELS[request.status] ?? 'Request status unavailable'}
                </span>
              </div>

              {dueDate && <p className="mt-4 text-[13px] text-slate-500"><span className="font-semibold text-slate-800">Due date:</span> {dueDate}</p>}
              {request.instructions && <p className="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{request.instructions}</p>}

              {request.checklist?.length > 0 && (
                <div className="mt-5 border border-slate-300 bg-slate-50 p-4">
                  <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Documents to prepare</p>
                  <ul className="mt-2.5 space-y-2">
                    {request.checklist.map((item) => (
                      <li key={item.id ?? item.sort_order ?? item.label} className="flex items-start gap-2 text-[13px] text-slate-800">
                        <span className="material-symbols-outlined mt-px text-[16px] text-slate-400">description</span>
                        <span>{item.label}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </section>

            <section className="rounded-md border border-slate-300 bg-white px-5 py-5 shadow-sm">
              <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Messages</h2>
              {request.messages?.length > 0 ? (
                <div className="mt-4 space-y-3">
                  {request.messages.map((message) => {
                    const isClient = message.sender_kind === 'CLIENT_ACCESS';
                    return (
                      <article key={message.id} className={`border px-3 py-3 ${isClient ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50'}`}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                          <p className="text-[11px] font-bold text-slate-800">{isClient ? 'You' : 'Agency'}</p>
                          {formatRequestDate(message.created_at) && <time className="text-[10px] text-slate-400">{formatRequestDate(message.created_at)}</time>}
                        </div>
                        <p className="mt-1 whitespace-pre-wrap text-[13px] leading-relaxed text-slate-800">{message.body}</p>
                      </article>
                    );
                  })}
                </div>
              ) : (
                <p className="mt-3 text-[13px] text-slate-500">No messages yet. You can reply below.</p>
              )}

              {canReply && (
                <form onSubmit={handleReply} className="mt-5 border-t border-slate-300 pt-4">
                  <label htmlFor="client-request-reply" className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Your reply</label>
                  <textarea
                    id="client-request-reply"
                    value={body}
                    onChange={(event) => { setBody(event.target.value); setError(''); }}
                    rows={4}
                    maxLength={5000}
                    disabled={replying}
                    aria-describedby={error ? 'client-request-error' : undefined}
                    className="mt-2 w-full resize-y border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                    placeholder="Write a message to the agency…"
                  />
                  {error && <p id="client-request-error" role="alert" className="mt-2 text-[12px] font-semibold text-error">{error}</p>}
                  <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-[11px] text-slate-500">Your reply will be shared with the agency.</p>
                    <button type="submit" disabled={replying || !body.trim()} className="inline-flex items-center gap-2 bg-blue-900 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60">
                      <span className="material-symbols-outlined text-[17px]">{replying ? 'progress_activity' : 'send'}</span>
                      {replying ? 'Sending…' : 'Send reply'}
                    </button>
                  </div>
                </form>
              )}
              {!canReply && !['COMPLETED', 'CANCELLED'].includes(request.status) && !replyAction && (
                <p className="mt-4 border-t border-slate-300 pt-4 text-[12px] text-slate-500">Replies are not available for this request.</p>
              )}
            </section>
          </div>
        )}
      </main>

      <AppFooter />
      <ChatBot />
    </div>
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
  clientRequestPanel,
}) {
  if (clientRequestPanel) {
    return <ClientRequestPanel clientRequestPanel={clientRequestPanel} />;
  }

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
        <div className="min-h-screen bg-slate-50 font-body text-slate-800">
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
    <div className="min-h-screen bg-slate-50 font-body text-slate-800">
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
                    className={`flex-1 rounded-sm ${
                      a.status === 'COMPLETED' ? 'bg-emerald-300' :
                      a.status === 'REJECTED' ? 'bg-white/20' :
                      a.status === 'FOR_COMPLIANCE' ? 'bg-amber-300/60' :
                      a.status === 'PROCESSING' ? 'bg-blue-300/70' :
                      'bg-white/30'
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
              <section className="rounded-md border border-slate-300 bg-white px-5 py-4 shadow-sm">
                <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-600">Case summary</h2>
                <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{caseOverview.narrative}</p>
              </section>
            )}

            {/* Agency chapters */}
            <section className="mt-8">
              <h2 className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                <span className="h-2 w-2 rounded-full bg-primary"></span>
                What each office has done
                <span className="group relative">
                  <span className="material-symbols-outlined cursor-help text-[14px] text-slate-400">info</span>
                  <span className="pointer-events-none absolute left-1/2 top-full z-10 mt-2 w-56 -translate-x-1/2 rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-[11px] font-normal normal-case tracking-normal text-slate-600 shadow-lg opacity-0 transition-opacity duration-150 group-hover:opacity-100">
                    Each agency is shown as an expandable card. Press to expand and view details.
                  </span>
                </span>
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
                <div className="mt-3 rounded-md border border-dashed border-slate-300 bg-slate-50/50 px-5 py-8 text-center">
                  <span className="material-symbols-outlined text-3xl text-slate-300">hourglass_empty</span>
                  <p className="mt-2 text-sm font-semibold text-slate-700">No partner offices assigned yet</p>
                  <p className="mx-auto mt-1 max-w-xs text-[13px] text-slate-500">
                    Your case manager is reviewing the case. Referrals will appear here once they are sent.
                  </p>
                </div>
              )}
            </section>

            {/* Feedback request */}
            {showFeedback && (() => {
              return (
                <section className="mt-8 rounded-md border border-slate-300 bg-amber-50/50 px-5 py-5 shadow-sm">
                  <h2 className="text-sm font-bold text-slate-800">How was the service?</h2>
                  <p className="mt-1 max-w-prose text-[13px] leading-relaxed text-slate-500">
                    An office has completed its part of your case. A survey has been sent to your registered email address. Please check your inbox to provide your feedback.
                  </p>
                </section>
              );
            })()}
          </div>

          {/* Right column — Complete case history (sidebar on desktop) */}
          {milestoneTimeline.length > 0 && (
            <aside className="lg:sticky lg:top-24 lg:self-start">
              <section className="rounded-md border border-slate-300 bg-white shadow-sm">
                <header className="border-b border-slate-300 bg-blue-50/60 px-4 py-3 rounded-t-md">
                  <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-blue-600">
                    Complete case history
                  </h2>
                  <p className="mt-0.5 text-[11px] text-slate-400">
                    {milestoneTimeline.length} {milestoneTimeline.length === 1 ? 'entry' : 'entries'}
                  </p>
                </header>
                <ul className="max-h-[calc(100vh-12rem)] overflow-y-auto px-4 pb-4 pt-2 owb-scroll-wide rounded-b-md">
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
