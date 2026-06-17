import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TrackingNotFoundState from '@/Components/TrackingNotFoundState';
import ChatBot from '@/Components/ChatBot';

const STATUS_CONFIG = {
  IN_PROGRESS:    { label: 'Your case is being processed',  icon: 'pending_actions', bg: 'bg-blue-50',   border: 'border-blue-200',  text: 'text-blue-800',  topBorder: 'border-t-blue-500'  },
  RESOLVED:       { label: 'Your case has been resolved',   icon: 'check_circle',    bg: 'bg-green-50',  border: 'border-green-200', text: 'text-green-800', topBorder: 'border-t-green-500' },
  BEING_PREPARED: { label: 'Your case is being prepared',   icon: 'hourglass_empty', bg: 'bg-amber-50',  border: 'border-amber-200', text: 'text-amber-800', topBorder: 'border-t-amber-500' },
  ARCHIVED:       { label: 'This case has been archived',   icon: 'archive',         bg: 'bg-slate-50',  border: 'border-slate-200', text: 'text-slate-700', topBorder: 'border-t-slate-400' },
  UNKNOWN:        { label: 'Case status unavailable',       icon: 'help_outline',    bg: 'bg-slate-50',  border: 'border-slate-200', text: 'text-slate-700', topBorder: 'border-t-slate-400' },
};

const EVENT_CONFIG = {
  case_opened:     { dot: 'bg-blue-500',   icon: 'folder_open',  iconColor: 'text-white' },
  referral_sent:   { dot: 'bg-purple-500', icon: 'send',         iconColor: 'text-white' },
  referral_status: { dot: 'bg-orange-400', icon: 'sync_alt',     iconColor: 'text-white' },
  milestone:       { dot: 'bg-green-500',  icon: 'flag',         iconColor: 'text-white' },
  case_closed:     { dot: 'bg-slate-500',  icon: 'check_circle', iconColor: 'text-white' },
};

function formatEventDate(dateStr) {
  const date = new Date(dateStr);
  const now = new Date();
  const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays} days ago`;
  return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}

function AgencyCard({ name, note, status, statusTone, borderTone, textTone, lineTone, steps, latestMilestoneLabel }) {
  const completedCount = steps.filter(s => s.state === 'complete').length;
  const isRejected = status === 'REJECTED';

  return (
    <article className={`rounded-xl border bg-white shadow-sm overflow-hidden border-t-4 ${borderTone}`}>
      <div className="px-5 py-4 sm:px-6 flex items-start justify-between gap-3 border-b border-slate-100">
        <div className="min-w-0">
          <h3 className="text-base font-bold text-slate-900 leading-tight truncate">{name}</h3>
          {note && <p className="text-xs text-slate-500 mt-1 leading-snug">{note}</p>}
        </div>
        <span className={`shrink-0 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider rounded-full ${statusTone}`}>
          {isRejected ? 'Not Accepted' : status.replace('_', ' ')}
        </span>
      </div>

      <div className="px-5 py-5 sm:px-6">
        {isRejected ? (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <span className="material-symbols-outlined text-[16px] align-middle mr-1" style={{ fontVariationSettings: "'FILL' 1" }}>cancel</span>
            This agency was unable to process your case.
          </div>
        ) : (
          <>
            <div className="relative px-2">
              <div className="absolute left-[20px] right-[20px] top-[14px] h-[3px] rounded-full bg-slate-100" />
              <div
                className={`absolute left-[20px] top-[14px] h-[3px] rounded-full transition-all duration-500 ${lineTone}`}
                style={{ width: `${completedCount > 0 ? ((completedCount - 1) / (steps.length - 1)) * 100 : 0}%` }}
              />
              <div className="relative z-10 grid grid-cols-4 gap-1">
                {steps.map((step) => (
                  <div key={step.label} className="flex flex-col items-center text-center">
                    <div className={`mb-2 flex h-7 w-7 items-center justify-center rounded-full ${
                      step.state === 'complete' ? 'bg-white ring-2 ring-primary shadow-sm' :
                      step.state === 'active'   ? 'bg-white ring-2 ring-blue-400 shadow-sm' :
                      'bg-slate-100'
                    }`}>
                      {step.state === 'complete' ? (
                        <span className="material-symbols-outlined text-[14px] text-primary" style={{ fontVariationSettings: "'FILL' 1, 'wght' 700" }}>check</span>
                      ) : step.state === 'active' ? (
                        <span className="material-symbols-outlined text-[13px] text-blue-500 animate-spin" style={{ fontVariationSettings: "'FILL' 1" }}>sync</span>
                      ) : (
                        <span className="material-symbols-outlined text-[11px] text-slate-400">radio_button_unchecked</span>
                      )}
                    </div>
                    <span className={`text-[10px] font-semibold uppercase tracking-wide leading-tight ${
                      step.state === 'active'   ? textTone :
                      step.state === 'complete' ? 'text-slate-700' :
                      'text-slate-400'
                    }`}>{step.label}</span>
                  </div>
                ))}
              </div>
            </div>
            {latestMilestoneLabel && (
              <p className="mt-4 text-xs text-slate-500 text-center">
                <span className="material-symbols-outlined text-[12px] align-middle mr-1 text-green-500">flag</span>
                Latest: <span className="font-semibold text-slate-700">{latestMilestoneLabel}</span>
              </p>
            )}
          </>
        )}
      </div>
    </article>
  );
}

export default function TrackingShow({ trackingId, trackedCase, caseOverview, caseTimeline, milestoneTimeline, trackingAgencies, caseNotifications }) {
  const [timelineAgencyFilter, setTimelineAgencyFilter] = useState('ALL');

  const involvedAgencyCount = trackingAgencies.length;
  const config = STATUS_CONFIG[trackedCase?.status] ?? STATUS_CONFIG.UNKNOWN;

  const caseAgeText = useMemo(() => {
    const created = new Date(trackedCase.createdAt);
    const days = Math.max(1, Math.round((Date.now() - created.getTime()) / (1000 * 60 * 60 * 24)));
    if (trackedCase.status === 'RESOLVED') {
      return `Resolved after ${days} day${days !== 1 ? 's' : ''}`;
    }
    return `Opened ${days} day${days !== 1 ? 's' : ''} ago`;
  }, [trackedCase]);

  const agencyCountText = involvedAgencyCount === 0
    ? 'No agencies assigned yet'
    : involvedAgencyCount === 1
    ? 'Handled by 1 agency'
    : `Handled by ${involvedAgencyCount} agencies`;

  const timelineAgencyNames = useMemo(() => {
    const names = milestoneTimeline
      .map(i => i.agency)
      .filter((a, i, arr) => a && a !== null && arr.indexOf(a) === i);
    return names.sort((a, b) => a.localeCompare(b));
  }, [milestoneTimeline]);

  const filteredTimeline = useMemo(() => {
    if (timelineAgencyFilter === 'ALL') return [...milestoneTimeline].reverse();
    return [...milestoneTimeline].filter(i => i.agency === timelineAgencyFilter || i.agency === null).reverse();
  }, [milestoneTimeline, timelineAgencyFilter]);

  if (!trackedCase) {
    return (
      <div className="bg-[#F5F7FA] min-h-screen font-body">
        <Head title="Tracking ID Not Found" />
        <AppHeader />
        <main className="mx-auto w-full max-w-[640px] px-4 pt-[88px] pb-6 sm:px-6">
          <TrackingNotFoundState description="We could not find a case matching this tracking ID. Please verify your ID and try again." />
        </main>
        <AppFooter />
        <ChatBot />
      </div>
    );
  }

  return (
    <div className="bg-[#F5F7FA] min-h-screen font-body text-slate-900">
      <Head title={`Tracking — ${trackingId}`} />
      <AppHeader />

      <main className="mx-auto w-full max-w-[640px] px-4 pt-[88px] pb-6 sm:px-6 sm:pt-[104px] sm:pb-10 space-y-6">

        {/* Status Banner */}
        <div className={`rounded-xl border ${config.border} bg-white shadow-sm overflow-hidden border-t-4 ${config.topBorder}`}>
          <div className="p-5 sm:p-6">
            <p className="text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500 mb-1">Case Tracking</p>
            <h1 className="text-2xl sm:text-3xl font-black uppercase tracking-tight text-slate-900 mb-3">{trackingId}</h1>
            <div className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 ${config.bg} ${config.border}`}>
              <span className="material-symbols-outlined text-[15px]" style={{ fontVariationSettings: "'FILL' 1" }}>{config.icon}</span>
              <span className={`text-[11px] font-bold uppercase tracking-wider ${config.text}`}>{config.label}</span>
            </div>
            <div className="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
              <span className="flex items-center gap-1">
                <span className="material-symbols-outlined text-[13px]">groups</span>
                {agencyCountText}
              </span>
              <span className="flex items-center gap-1">
                <span className="material-symbols-outlined text-[13px]">schedule</span>
                {caseAgeText}
              </span>
            </div>
          </div>
        </div>

        {/* Case Narrative */}
        {caseOverview?.narrative && (
          <section>
            <h2 className="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-500 mb-3">About This Case</h2>
            <article className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
              <p className="text-sm leading-relaxed text-slate-700 whitespace-pre-wrap">{caseOverview.narrative}</p>
            </article>
          </section>
        )}

        {/* Agencies */}
        <section>
          <h2 className="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-500 mb-3">
            Agencies Handling Your Case
          </h2>
          {trackingAgencies.length > 0 ? (
            <div className="space-y-4">
              {trackingAgencies.map((agency) => (
                <AgencyCard key={agency.name} {...agency} />
              ))}
            </div>
          ) : (
            <article className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
              <span className="material-symbols-outlined text-4xl text-slate-300 block mb-3">hourglass_empty</span>
              <h3 className="text-sm font-bold text-slate-700">No agencies assigned yet</h3>
              <p className="mt-1 text-sm text-slate-500">Your case manager will refer your case to the appropriate agencies.</p>
            </article>
          )}
        </section>

        {/* Timeline */}
        <section>
          <div className="flex items-center justify-between mb-3 gap-3">
            <h2 className="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-500 flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[14px]">history</span>
              Case Timeline
            </h2>
            {timelineAgencyNames.length > 0 && (
              <select
                value={timelineAgencyFilter}
                onChange={e => setTimelineAgencyFilter(e.target.value)}
                className="text-xs font-semibold text-slate-700 border border-slate-200 rounded-lg px-2 py-1.5 bg-white outline-none focus:border-primary"
              >
                <option value="ALL">All agencies</option>
                {timelineAgencyNames.map(a => <option key={a} value={a}>{a}</option>)}
              </select>
            )}
          </div>

          {filteredTimeline.length === 0 ? (
            <article className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
              <span className="material-symbols-outlined text-4xl text-slate-300 block mb-3">history</span>
              <p className="text-sm text-slate-500">No activity recorded yet. Your case manager will update you as things progress.</p>
            </article>
          ) : (
            <article className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
              <div className="relative">
                <div className="absolute left-[13px] top-2 bottom-2 w-0.5 bg-slate-100" />
                <div className="space-y-6">
                  {filteredTimeline.map((item, index) => {
                    const cfg = EVENT_CONFIG[item.type] ?? EVENT_CONFIG.milestone;
                    return (
                      <div key={`${item.date}-${index}`} className="relative grid grid-cols-[28px_1fr] gap-3 items-start">
                        <div className={`z-10 flex h-7 w-7 items-center justify-center rounded-full ${cfg.dot} shadow-sm shrink-0`}>
                          <span className={`material-symbols-outlined text-[13px] ${cfg.iconColor}`} style={{ fontVariationSettings: "'FILL' 1, 'wght' 600" }}>{cfg.icon}</span>
                        </div>
                        <div className="min-w-0 pt-0.5">
                          <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-0.5">
                            {formatEventDate(item.date)}
                            {item.agency && <span className="ml-2 text-slate-400">· {item.agency}</span>}
                          </p>
                          <h3 className="text-sm font-semibold text-slate-900 leading-snug">{item.title}</h3>
                          {item.description && <p className="text-xs text-slate-500 mt-0.5 leading-relaxed">{item.description}</p>}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </article>
          )}
        </section>

      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
