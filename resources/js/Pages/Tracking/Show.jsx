import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import UnifiedTimeline from '@/Components/Timeline';
import { buildCaseStatusLine } from '@/lib/caseStatusLine';

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

function formatLongDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-PH', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
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

const CLIENT_REQUEST_BANNER = {
  COMPLETED: {
    title: 'This request is completed',
    description: 'Your response and documents have been received. Review the conversation below to confirm what you submitted.',
    icon: 'check_circle',
    container: 'border-emerald-500/40 bg-emerald-50',
    iconClass: 'text-emerald-600',
    titleClass: 'text-emerald-900',
    textClass: 'text-emerald-800',
  },
  CANCELLED: {
    title: 'This request was closed',
    description: 'This request is no longer open, and replies are not accepted.',
    icon: 'block',
    container: 'border-slate-300 bg-slate-100',
    iconClass: 'text-slate-500',
    titleClass: 'text-slate-800',
    textClass: 'text-slate-600',
  },
};

const CLIENT_REQUEST_STATUS_BADGE = {
  COMPLETED: 'border-emerald-500 text-emerald-700',
  CANCELLED: 'border-slate-400 text-slate-500',
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

const MAX_ATTACHMENTS = 5;
const MAX_ATTACHMENT_MB = 20;
const ACCEPTED_ATTACHMENT_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

function formatFileSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
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
  const [files, setFiles] = useState([]);
  const [fileItems, setFileItems] = useState([]);
  const [fileError, setFileError] = useState('');
  const [replying, setReplying] = useState(false);
  const [requestingReplacement, setRequestingReplacement] = useState(false);
  const [error, setError] = useState('');
  const [tokenError, setTokenError] = useState(false);

  const state = clientRequestPanel?.state ?? 'empty';
  const request = clientRequestPanel?.activeRequest;
  const actions = clientRequestPanel?.actions ?? {};
  const replyAction = typeof actions.reply === 'string' && actions.reply.trim() ? actions.reply : null;
  const replacementAction = typeof actions.requestReplacement === 'string' && actions.requestReplacement.trim()
    ? actions.requestReplacement
    : null;
  const exchangeAction = typeof actions.exchange === 'string' && actions.exchange.trim() ? actions.exchange : null;
  const canReply = state === 'ready' && request && replyAction
    && !['COMPLETED', 'CANCELLED'].includes(request.status);
  const stateCopy = request ? null : requestStateCopy(state);
  const dueDate = formatRequestDate(request?.due_at);
  const requestBanner = CLIENT_REQUEST_BANNER[request?.status];
  const statusBadgeClass = CLIENT_REQUEST_STATUS_BADGE[request?.status] ?? 'border-primary text-primary';

  // A magic-link token can arrive while an earlier session is still active
  // (links live 7 days). Exchange it so the panel switches to the request the
  // token belongs to instead of showing the stale conversation.
  useEffect(() => {
    if (!exchangeAction) return;
    const hash = window.location.hash.replace(/^#/, '');
    const token = new URLSearchParams(hash).get('token');
    if (!token) return;

    window.history.replaceState(null, document.title, `${window.location.pathname}${window.location.search}`);
    setTokenError(false);
    router.post(exchangeAction, { token }, {
      preserveScroll: true,
      onError: () => setTokenError(true),
    });
  }, [exchangeAction]);

  function getErrorMessage(errors) {
    if (!errors) return 'We could not complete that request. Please try again.';
    return Object.values(errors).flat().find(Boolean) || 'We could not complete that request. Please try again.';
  }

  function handleFileChange(event) {
    setFileError('');
    const incoming = Array.from(event.target.files ?? []);
    if (incoming.length === 0) return;

    const room = MAX_ATTACHMENTS - files.length;
    if (room <= 0) {
      setFileError(`You can attach up to ${MAX_ATTACHMENTS} documents. Remove one to add another.`);
      event.target.value = '';
      return;
    }

    const accepted = [];
    for (const file of incoming.slice(0, room)) {
      if (!ACCEPTED_ATTACHMENT_TYPES.includes(file.type)) {
        setFileError(`"${file.name}" is not an accepted file type. Use PDF, JPG, PNG, DOC or DOCX.`);
        continue;
      }
      if (file.size > MAX_ATTACHMENT_MB * 1024 * 1024) {
        setFileError(`"${file.name}" is larger than ${MAX_ATTACHMENT_MB} MB.`);
        continue;
      }
      accepted.push(file);
    }

    if (accepted.length > 0) {
      setFiles((prev) => [...prev, ...accepted].slice(0, MAX_ATTACHMENTS));
      setFileItems((prev) => [...prev, ...accepted.map(() => '')].slice(0, MAX_ATTACHMENTS));
    }
    event.target.value = '';
  }

  function handleReply(event) {
    event.preventDefault();
    const trimmed = body.trim();
    if (!canReply || (!trimmed && files.length === 0) || replying) return;

    setError('');
    setReplying(true);

    const formData = new FormData();
    if (trimmed) formData.append('body', trimmed);
    files.forEach((file, index) => {
      formData.append('attachments[]', file);
      formData.append('request_item_ids[]', fileItems[index] ?? '');
    });

    router.post(replyAction, formData, {
      preserveScroll: true,
      onSuccess: () => {
        setBody('');
        setFiles([]);
        setFileItems([]);
        setFileError('');
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
        <h1 className="font-headline text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[30px]">
          Document request and response
        </h1>
        <p className="mt-1.5 text-[14px] text-slate-600">
          Respond to the agency request for case
          {request?.case_number ? <span className="font-semibold text-slate-800"> {request.case_number}</span> : null}.
        </p>

        {tokenError ? (
          <section className="mt-8 rounded-md border border-slate-300 bg-white px-5 py-8 text-center shadow-sm">
            <span className="material-symbols-outlined text-4xl text-slate-400">link_off</span>
            <h2 className="mt-3 text-sm font-bold text-slate-800">Secure request unavailable</h2>
            <p className="mx-auto mt-1 max-w-md text-[13px] leading-relaxed text-slate-500">
              This secure request link is unavailable or has expired. Please return to the tracking portal to continue.
            </p>
            <Link
              href={route('track.index')}
              className="mt-5 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-container"
            >
              <span className="material-symbols-outlined text-[17px]">arrow_back</span>
              Back to Tracking
            </Link>
          </section>
        ) : !request ? (
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
                className="mt-5 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
              >
                <span className="material-symbols-outlined text-[17px]">{requestingReplacement ? 'progress_activity' : 'refresh'}</span>
                {requestingReplacement ? 'Sending request…' : 'Request a new link'}
              </button>
            )}
            {replacementAction && <p className="mt-2 text-[11px] text-slate-500">This notifies the agency. No email destination is required.</p>}
          </section>
        ) : (
          <div className="mt-8 space-y-6">
            {requestBanner && (
              <section role="status" aria-live="polite" className={`flex items-start gap-3 rounded-md border px-4 py-4 ${requestBanner.container}`}>
                <span aria-hidden="true" className={`material-symbols-outlined mt-px text-[22px] ${requestBanner.iconClass}`}>{requestBanner.icon}</span>
                <div>
                  <p className={`text-sm font-bold ${requestBanner.titleClass}`}>{requestBanner.title}</p>
                  <p className={`mt-0.5 text-[13px] leading-relaxed ${requestBanner.textClass}`}>{requestBanner.description}</p>
                </div>
              </section>
            )}
            <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                <div className="min-w-0">
                  <h2 className="font-headline text-[19px] font-extrabold tracking-tight text-slate-900">
                    Respond to Agency Request
                  </h2>
                  {request.agency_name && (
                    <p className="mt-1 text-[12px] text-slate-500">{request.agency_name}</p>
                  )}
                </div>
                <Link
                  href={route('track.index')}
                  aria-label="Close and return to tracking"
                  className="shrink-0 text-slate-400 transition-colors hover:text-slate-700"
                >
                  <span aria-hidden="true" className="material-symbols-outlined text-[22px]">close</span>
                </Link>
              </div>

              <div className="px-5 py-5 sm:px-6">
                <span className={`inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-semibold ${statusBadgeClass}`}>
                  <span className="h-1.5 w-1.5 rounded-full bg-current" />
                  {CLIENT_REQUEST_STATUS_LABELS[request.status] ?? 'Request status unavailable'}
                </span>

                <h3 className="mt-4 font-headline text-[19px] font-extrabold tracking-tight text-slate-900">
                  {request.title ?? CLIENT_REQUEST_TYPE_LABELS[request.type] ?? 'Information requested'}
                </h3>
                <p className="mt-1 text-[13px] text-slate-500">
                  {[
                    CLIENT_REQUEST_TYPE_LABELS[request.type],
                    request.agency_name,
                    `Requested ${formatRequestDate(request.created_at)}`,
                  ].filter(Boolean).join(' · ')}
                </p>
                {request.case_number && (
                  <p className="mt-0.5 text-[13px] text-slate-500">
                    Case <span className="font-semibold text-slate-800">{request.case_number}</span>
                  </p>
                )}

                {request.instructions && (
                  <div className="mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3.5">
                    <span aria-hidden="true" className="material-symbols-outlined mt-px shrink-0 text-[18px] leading-none text-amber-600">error</span>
                    <div className="text-[13px]">
                      <p className="font-bold text-amber-800">{request.instructions}</p>
                      <p className="mt-0.5 text-amber-700">
                        {dueDate ? `Please respond by ${dueDate}.` : 'No deadline is recorded for this request.'}
                      </p>
                    </div>
                  </div>
                )}

                {request.checklist?.length > 0 && (
                  <div className="mt-5">
                    <p className="text-[13px] font-bold text-slate-800">Documents to prepare</p>
                    <ul className="mt-2 space-y-1.5">
                      {request.checklist.map((item) => (
                        <li key={item.id ?? item.sort_order ?? item.label} className="flex items-start gap-2 text-[13px] text-slate-700">
                          <span aria-hidden="true" className="material-symbols-outlined mt-px text-[16px] text-slate-400">description</span>
                          <span>{item.label}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </section>

            <section className="overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm">
              <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-50/80 px-5 py-3">
                <span aria-hidden="true" className="material-symbols-outlined text-[16px] text-slate-400">forum</span>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Messages</h2>
                {request.messages?.length > 0 && (
                  <span className="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                    {request.messages.length} {request.messages.length === 1 ? 'message' : 'messages'}
                  </span>
                )}
              </div>

              <div className="max-h-[26rem] overflow-y-auto overscroll-contain bg-slate-50/50 px-4 py-4 sm:px-5">
                {request.messages?.length > 0 ? (
                  <div className="space-y-4">
                    {request.messages.map((message) => {
                      const isClient = message.sender_kind === 'CLIENT_ACCESS';
                      const senderName = isClient ? 'You' : 'Agency';
                      const messageDate = formatRequestDate(message.created_at);
                      return (
                        <article key={message.id} className={`flex items-end gap-2 ${isClient ? 'flex-row-reverse' : ''}`}>
                          <span
                            aria-hidden="true"
                            className={`flex h-7 w-7 shrink-0 select-none items-center justify-center rounded-full text-[11px] font-bold ${isClient ? 'bg-primary text-white' : 'bg-slate-200 text-slate-600'}`}
                          >
                            {senderName.charAt(0)}
                          </span>
                          <div className={`flex max-w-[85%] flex-col ${isClient ? 'items-end' : 'items-start'}`}>
                            <div className={`rounded-xl px-3.5 py-2.5 text-[13px] leading-relaxed shadow-sm ${isClient ? 'rounded-br-sm bg-primary text-white' : 'rounded-bl-sm border border-slate-200 bg-white text-slate-800'}`}>
                              {message.body && <p className="whitespace-pre-wrap">{message.body}</p>}
                              {message.attachments?.length > 0 && (
                                <ul className={`mt-2 space-y-1.5 ${message.body ? '' : ''}`}>
                                  {message.attachments.map((attachment) => (
                                    <li key={attachment.id}>
                                      <a
                                        href={route('track.request.attachments.download', { attachment: attachment.id })}
                                        className={`inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-1 text-[12px] font-semibold transition-colors ${isClient ? 'border-white/20 bg-white/10 text-primary-fixed hover:bg-white/20' : 'border-slate-200 bg-slate-50 text-primary hover:bg-slate-100'}`}
                                      >
                                        <span aria-hidden="true" className="material-symbols-outlined shrink-0 text-[14px]">description</span>
                                        <span className="truncate">{attachment.file_name}</span>
                                        {attachment.size > 0 && <span className={`shrink-0 text-[10px] font-normal ${isClient ? 'text-primary-fixed-dim' : 'text-slate-400'}`}>{formatFileSize(attachment.size)}</span>}
                                      </a>
                                    </li>
                                  ))}
                                </ul>
                              )}
                            </div>
                            {messageDate && (
                              <p className={`mt-1 text-[10px] text-slate-400 ${isClient ? 'text-right' : ''}`}>
                                {senderName} · {messageDate}
                              </p>
                            )}
                          </div>
                        </article>
                      );
                    })}
                  </div>
                ) : (
                  <div className="flex flex-col items-center justify-center py-8 text-center">
                    <span aria-hidden="true" className="material-symbols-outlined text-[28px] text-slate-300">forum</span>
                    <p className="mt-2 text-[13px] text-slate-500">
                      {['COMPLETED', 'CANCELLED'].includes(request.status) ? 'No messages yet.' : 'No messages yet. You can reply below.'}
                    </p>
                  </div>
                )}
              </div>

              {canReply && (
                <form onSubmit={handleReply} className="border-t border-slate-200 bg-white px-5 py-4">
                  <label htmlFor="client-request-reply" className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Your reply</label>
                  <textarea
                    id="client-request-reply"
                    value={body}
                    onChange={(event) => { setBody(event.target.value); setError(''); }}
                    rows={3}
                    maxLength={5000}
                    disabled={replying}
                    aria-describedby={error ? 'client-request-error' : undefined}
                    className="mt-2 w-full resize-none rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                    placeholder="Write a message to the agency…"
                  />
                  {error && <p id="client-request-error" role="alert" className="mt-2 text-[12px] font-semibold text-error">{error}</p>}

                  <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-md border border-dashed border-slate-300 bg-slate-50/70 px-3 py-2.5">
                    <label htmlFor="client-request-files" className="inline-flex cursor-pointer items-center gap-1.5 text-[12px] font-bold text-primary hover:underline">
                      <span aria-hidden="true" className="material-symbols-outlined text-[15px]">attach_file</span>
                      Attach documents
                      <input
                        id="client-request-files"
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        disabled={replying}
                        onChange={handleFileChange}
                        className="sr-only"
                      />
                    </label>
                    <p className="text-[11px] text-slate-500">PDF, JPG, PNG, DOC or DOCX · up to {MAX_ATTACHMENT_MB} MB · max {MAX_ATTACHMENTS} files</p>
                  </div>

                  {files.length > 0 && (
                    <ul className="mt-2 space-y-1.5">
                      {files.map((file, index) => (
                        <li key={`${file.name}-${index}`} className="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5">
                          <span aria-hidden="true" className="material-symbols-outlined shrink-0 text-[15px] text-slate-500">description</span>
                          <span className="min-w-0 flex-1 truncate text-[12px] font-medium text-slate-800">{file.name}</span>
                          <span className="shrink-0 text-[10px] text-slate-400">{formatFileSize(file.size)}</span>
                          <button
                            type="button"
                            onClick={() => setFiles((prev) => prev.filter((_, i) => i !== index))}
                            disabled={replying}
                            aria-label={`Remove ${file.name}`}
                            className="shrink-0 text-slate-400 transition-colors hover:text-error disabled:cursor-not-allowed disabled:opacity-50"
                          >
                            <span aria-hidden="true" className="material-symbols-outlined text-[15px]">close</span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                  {fileError && <p role="alert" className="mt-2 text-[12px] font-semibold text-error">{fileError}</p>}

                  <div className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                    <p className="text-[11px] text-slate-500">Your reply and any documents will be shared with the agency.</p>
                    <button type="submit" disabled={replying || (!body.trim() && files.length === 0)} className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60">
                      <span aria-hidden="true" className="material-symbols-outlined text-[17px]">{replying ? 'progress_activity' : 'send'}</span>
                      {replying ? 'Sending…' : 'Send reply'}
                    </button>
                  </div>
                </form>
              )}
              {!canReply && !['COMPLETED', 'CANCELLED'].includes(request.status) && !replyAction && (
                <p className="border-t border-slate-200 bg-white px-5 py-4 text-[12px] text-slate-500">Replies are not available for this request.</p>
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

  const statusLine = useMemo(
    () => buildCaseStatusLine({
      status: trackedCase?.status,
      totalAgencies,
      completedAgencies,
      rejectedCount,
    }),
    [trackedCase, totalAgencies, completedAgencies, rejectedCount],
  );

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
          <section className="border-l-[3px] border-red-600 bg-white px-[28px] py-[24px] shadow-[0_1px_3px_rgba(0,0,0,0.05)]">
            <h1 className="font-headline text-[26px] font-[900] uppercase tracking-[-0.02em] text-red-700">Tracking ID Not Found</h1>
            <p className="mt-[8px] text-[11px] font-[500] text-on-surface-variant">We could not find a case matching this tracking ID. Please verify your ID and try again.</p>
            <Link
              href={route('track.index')}
              className="mt-4 inline-flex px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-container transition-colors"
            >
              &larr; Back to Tracking Search
            </Link>
          </section>
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

      <main className="mx-auto w-full max-w-6xl px-4 pt-24 pb-16 sm:px-6 lg:px-8">
        <p className="text-[12px] text-slate-500">Home / Track Your Case / Case Details</p>
        <h1 className="mt-2 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">My Case Tracking</h1>
        <p className="mt-1.5 text-[14px] text-slate-600">Track your case and follow updates from partner agencies.</p>

        <section className="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="px-5 py-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Request for Assistance</p>
              <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600"><span className="h-1.5 w-1.5 rounded-full bg-current" />Read-only tracking</span>
            </div>
            <h2 className="mt-2 font-headline font-mono text-[26px] font-extrabold tracking-tight text-slate-900">{trackingId}</h2>
            <p className="mt-2 text-[13px] leading-relaxed text-slate-600">{statusLine}</p>
            <p className="mt-1 text-[12px] text-slate-400">{trackedCase.clientName} · Opened {formatLongDate(trackedCase.createdAt)}</p>
            {caseOverview?.narrative && (
              <div className="mt-5 border-t border-slate-100 pt-4">
                <h3 className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Case Summary</h3>
                <p className="mt-2 whitespace-pre-wrap text-[13px] leading-relaxed text-slate-700">{caseOverview.narrative}</p>
              </div>
            )}
          </div>
          {totalAgencies > 0 && <div className="border-t border-slate-100 px-5 py-4"><div className="flex h-1.5 gap-1" role="img" aria-label={`${completionPercentage}% of processing complete`}>{trackingAgencies.map((a) => <span key={a.referralId ?? a.name} className={`flex-1 rounded-sm ${a.status === 'COMPLETED' ? 'bg-emerald-300' : a.status === 'REJECTED' ? 'bg-rose-300/60' : a.status === 'FOR_COMPLIANCE' ? 'bg-amber-300/70' : a.status === 'PROCESSING' ? 'bg-blue-300/80' : 'bg-slate-200'}`} />)}</div><p className="mt-2 text-[11px] text-slate-400">Last updated {formatLongDate(trackedCase.updatedAt)} · {completionPercentage}% of processing complete</p></div>}
        </section>

        {/* Two-column layout: main content + case history sidebar */}
        <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">

          {/* Left column — main content */}
          <div className="min-w-0">
            {/* Case timeline */}
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-100 px-5 py-4">
                <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">Case Timeline</h2>
                <p className="mt-0.5 text-[12px] text-slate-500">Follow recorded milestones and updates for your case.</p>
              </header>
              {milestoneTimeline.length > 0 ? (
                <UnifiedTimeline
                  variant="ledger"
                  items={milestoneTimeline}
                  listClassName="max-h-[32rem] overflow-y-auto px-5 pb-5 pt-2 owb-scroll-wide"
                />
              ) : (
                <div className="px-5 py-8 text-center">
                  <span className="material-symbols-outlined text-3xl text-slate-300">history</span>
                  <p className="mt-2 text-[13px] font-semibold text-slate-700">No milestones recorded yet</p>
                  <p className="mt-1 text-[12px] text-slate-500">Updates will appear here as your case progresses.</p>
                </div>
              )}
            </section>

            {/* Agency chapters */}
            <section className="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-100 px-5 py-4">
                <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">Agencies Handling Your Case</h2>
                <p className="mt-0.5 text-[12px] text-slate-500">Each agency has a separate referral status.</p>
              </header>
              <div className="p-5">
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
              </div>
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
                <header className="border-b border-slate-300 bg-info-container/60 px-4 py-3 rounded-t-md">
                  <h2 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-700">
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
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
