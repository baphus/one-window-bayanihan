import { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TrackingNotFoundState from '@/Components/TrackingNotFoundState';
import ChatBot from '@/Components/ChatBot';

const STATUS_CONFIG = {
  IN_PROGRESS:    { label: 'In Progress',       icon: 'radio_button_checked', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
  RESOLVED:       { label: 'Resolved',          icon: 'check_circle',    bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  BEING_PREPARED: { label: 'Under Preparation',  icon: 'layers',          bg: 'bg-blue-50 text-blue-700 border-blue-200' },
  ARCHIVED:       { label: 'Archived',          icon: 'archive',         bg: 'bg-slate-100 text-slate-600 border-slate-200' },
  UNKNOWN:        { label: 'Status Unavailable', icon: 'help_outline',    bg: 'bg-slate-100 text-slate-600 border-slate-200' },
};

const EVENT_CONFIG = {
  case_opened:     { dot: 'bg-blue-50 border-blue-200 text-blue-600',       icon: 'folder' },
  referral_sent:   { dot: 'bg-purple-50 border-purple-200 text-purple-600',   icon: 'forward_to_inbox' },
  referral_status: { dot: 'bg-amber-50 border-amber-200 text-amber-600',       icon: 'sync_alt' },
  milestone:       { dot: 'bg-emerald-50 border-emerald-200 text-emerald-600', icon: 'flag' },
  case_closed:     { dot: 'bg-slate-100 border-slate-200 text-slate-600',     icon: 'lock' },
};

const EVENT_TYPE_OPTIONS = [
  { value: 'ALL',          label: 'All Events' },
  { value: 'case_opened',  label: 'Case Opened' },
  { value: 'referral',     label: 'Referrals' },
  { value: 'referral_status', label: 'Status Updates' },
  { value: 'milestone',    label: 'Milestones' },
  { value: 'case_closed',  label: 'Case Closed' },
];

function formatEventDate(dateStr) {
  const date = new Date(dateStr);
  const now = new Date();
  const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
  if (diffDays < 0) {
    return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }
  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays} days ago`;
  return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatHumanDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }) + ' at ' + date.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function AgencyCard({ name, note, status, steps = [], latestMilestoneLabel, compliance_requirements, milestonesUrl }) {
  const completedCount = steps.filter(s => s.state === 'complete').length;
  const activeIndex = steps.findIndex(s => s.state === 'active');
  const isRejected = status === 'REJECTED';

  const progressPercent = useMemo(() => {
    if (steps.length <= 1) return 0;
    if (activeIndex !== -1) {
      return (activeIndex / (steps.length - 1)) * 100;
    }
    return (completedCount / steps.length) * 100;
  }, [steps, completedCount, activeIndex]);

  const statusColor = {
    PENDING: 'bg-amber-50 text-amber-700 border-amber-200',
    PROCESSING: 'bg-blue-50 text-blue-700 border-blue-200',
    FOR_COMPLIANCE: 'bg-orange-50 text-orange-700 border-orange-200',
    COMPLETED: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    REJECTED: 'bg-red-50 text-red-700 border-red-100',
  }[status] || 'bg-slate-100 text-slate-700 border-slate-200';

  return (
    <article className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="px-5 py-4 flex items-center justify-between gap-4 bg-slate-50/70 border-b border-slate-200">
        <div className="min-w-0 flex items-center gap-3">
          <span className={`shrink-0 w-2.5 h-2.5 rounded-full ${
            status === 'COMPLETED' ? 'bg-emerald-500' :
            status === 'REJECTED' ? 'bg-red-500' :
            status === 'PROCESSING' ? 'bg-blue-500' :
            status === 'FOR_COMPLIANCE' ? 'bg-orange-500' :
            'bg-slate-400'
          }`} />
          <div>
            <h3 className="text-sm font-bold text-slate-900 leading-none">{name}</h3>
            {note && <p className="text-xs text-slate-500 mt-1 leading-normal max-w-md">{note}</p>}
          </div>
        </div>
        <span className={`shrink-0 px-2.5 py-1 text-[11px] font-bold rounded-full border ${statusColor}`}>
          {isRejected ? 'Not Accepted' : status.replace(/_/g, ' ')}
        </span>
      </div>

      <div className="p-5">
        {isRejected ? (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px] shrink-0">error</span>
            This agency was unable to process your case.
          </div>
        ) : (
          <>
            <div className="relative px-1 mt-2 mb-4">
              <div className="absolute left-0 right-0 top-[10px] h-[2px] bg-slate-100 rounded-full" />
              <div
                className="absolute left-0 top-[10px] h-[2px] bg-blue-600 rounded-full transition-all duration-500 ease-out"
                style={{ width: `${progressPercent}%` }}
              />

              <div
                className="relative z-10 grid"
                style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}
              >
                {steps.map((step, idx) => {
                  const isComplete = step.state === 'complete';
                  const isActive = step.state === 'active';

                  return (
                    <div key={step.label} className="flex flex-col items-center">
                      <div className={`flex h-5 w-5 items-center justify-center rounded-full transition-all duration-300 ${
                        isComplete ? 'bg-blue-600 text-white ring-4 ring-white shadow-sm' :
                        isActive   ? 'bg-white border-2 border-blue-600 ring-4 ring-white shadow-sm' :
                        'bg-slate-100 text-slate-400 ring-4 ring-white'
                      }`}>
                        {isComplete && (
                          <span className="material-symbols-outlined text-[12px] font-bold">check</span>
                        )}
                        {isActive && (
                          <span className="h-1.5 w-1.5 rounded-full bg-blue-600 animate-pulse" />
                        )}
                      </div>
                      <span className={`mt-2 text-[10px] font-semibold text-center tracking-tight px-1 max-w-[85px] truncate ${
                        isActive ? 'text-blue-600' : isComplete ? 'text-slate-800' : 'text-slate-400'
                      }`}>
                        {step.label}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>

            {latestMilestoneLabel && (
              <div className="mt-4 pt-3 border-t border-slate-200 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                <span className="material-symbols-outlined text-[14px] text-emerald-600">flag</span>
                <span>Latest Update: <span className="font-semibold text-slate-800">{latestMilestoneLabel}</span></span>
              </div>
            )}

            {compliance_requirements?.length > 0 && (
              <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <div className="flex items-center gap-1.5 mb-2.5">
                  <span className="material-symbols-outlined text-[15px] text-amber-700">assignment_late</span>
                  <span className="text-[11px] font-bold tracking-wide uppercase text-amber-800">Action Required</span>
                </div>
                <div className="space-y-2.5">
                  {compliance_requirements.map((cr) => (
                    <div key={cr.id} className="flex items-start justify-between gap-3 pt-2.5 border-t border-amber-100/60 first:border-t-0 first:pt-0">
                      <div className="min-w-0">
                        <p className="text-[10px] text-amber-700/80 font-semibold">{cr.service_name}</p>
                        <p className="text-xs font-bold text-amber-900 mt-0.5">{cr.requirement_name}</p>
                      </div>
                      <span className="shrink-0 px-2 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-800 border border-amber-200/60">
                        Pending Submission
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </>
        )}

        {milestonesUrl && (
          <div className="mt-4 pt-4 border-t border-slate-200 flex justify-end">
            <Link
              href={milestonesUrl}
              className="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-800 hover:bg-blue-100 hover:border-blue-300 transition-colors"
            >
              View milestones
              <span className="material-symbols-outlined text-[14px]">arrow_right_alt</span>
            </Link>
          </div>
        )}
      </div>
    </article>
  );
}

function StatCard({ icon, label, value, iconBg }) {
  return (
    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
      <div className="flex items-start justify-between mb-2">
        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{label}</p>
        <span className={`p-1.5 rounded-lg ${iconBg}`}>
          <span className="material-symbols-outlined text-[18px]">{icon}</span>
        </span>
      </div>
      <p className="text-lg font-bold text-slate-900">{value}</p>
    </div>
  );
}

export default function TrackingShow({ trackingId, trackedCase, caseOverview, caseTimeline, milestoneTimeline, trackingAgencies, caseNotifications, completionPercentage }) {
  const [timelineAgencyFilter, setTimelineAgencyFilter] = useState('ALL');
  const [timelineTypeFilter, setTimelineTypeFilter] = useState('ALL');

  const involvedAgencyCount = trackingAgencies.length;
  const config = STATUS_CONFIG[trackedCase?.status] ?? STATUS_CONFIG.UNKNOWN;

  const clientFirstName = useMemo(() => {
    if (!trackedCase?.clientName) return '';
    return trackedCase.clientName.split(' ')[0];
  }, [trackedCase]);

  const caseAgeInDays = useMemo(() => {
    if (!trackedCase?.createdAt) return 0;
    const created = new Date(trackedCase.createdAt);
    return Math.max(1, Math.round((Date.now() - created.getTime()) / (1000 * 60 * 60 * 24)));
  }, [trackedCase]);

  const caseAgeText = useMemo(() => {
    if (!trackedCase?.createdAt) return '';
    return trackedCase.status === 'RESOLVED'
      ? `Resolved in ${caseAgeInDays} day${caseAgeInDays !== 1 ? 's' : ''}`
      : `Opened ${caseAgeInDays} day${caseAgeInDays !== 1 ? 's' : ''} ago`;
  }, [trackedCase, caseAgeInDays]);

  const timelineAgencyNames = useMemo(() => {
    const names = milestoneTimeline
      ? milestoneTimeline.map(i => i.agency).filter((a, i, arr) => a && arr.indexOf(a) === i)
      : [];
    return names.sort((a, b) => a.localeCompare(b));
  }, [milestoneTimeline]);

  const timelineEventCount = milestoneTimeline?.length ?? 0;

  const filteredTimeline = useMemo(() => {
    if (!milestoneTimeline) return [];
    let items = [...milestoneTimeline];
    if (timelineAgencyFilter !== 'ALL') {
      items = items.filter(i => i.agency === timelineAgencyFilter || i.agency === null);
    }
    if (timelineTypeFilter === 'referral') {
      items = items.filter(i => i.type === 'referral_sent' || i.type === 'referral_status');
    } else if (timelineTypeFilter !== 'ALL') {
      items = items.filter(i => i.type === timelineTypeFilter);
    }
    return items.reverse();
  }, [milestoneTimeline, timelineAgencyFilter, timelineTypeFilter]);

  const hasActiveFilters = timelineAgencyFilter !== 'ALL' || timelineTypeFilter !== 'ALL';

  const clearFilters = () => {
    setTimelineAgencyFilter('ALL');
    setTimelineTypeFilter('ALL');
  };

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

  return (
    <div className="min-h-screen bg-surface font-body text-on-surface">
      <Head title={`Tracking — ${trackingId}`} />
      <AppHeader />

      <main className="mx-auto w-full max-w-5xl px-4 pt-24 pb-12 sm:px-6 lg:px-8 space-y-8">

        {/* Greeting */}
        {clientFirstName && (
          <div className="flex items-center gap-3">
            <span className="material-symbols-outlined text-3xl text-blue-600">waving_hand</span>
            <div>
              <h1 className="text-2xl font-extrabold text-slate-900 font-headline tracking-tight">
                Hello, {clientFirstName}!
              </h1>
              <p className="text-sm text-slate-500">Here&apos;s the latest on your case.</p>
              <p className="text-xs text-slate-400 font-mono mt-0.5">Ref: {trackingId}</p>
            </div>
            <div className="ml-auto">
              <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold shadow-sm ${config.bg}`}>
                <span className="material-symbols-outlined text-[16px]">{config.icon}</span>
                {config.label}
              </span>
            </div>
          </div>
        )}

        {/* Stat Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <StatCard
            icon="calendar_today"
            label="Case Age"
            value={caseAgeText || '—'}
            iconBg="bg-blue-50 text-blue-900"
          />
          <StatCard
            icon="business"
            label="Agencies"
            value={`${involvedAgencyCount}`}
            iconBg="bg-purple-50 text-purple-600"
          />
          <StatCard
            icon="timeline"
            label="Events"
            value={`${timelineEventCount}`}
            iconBg="bg-emerald-50 text-emerald-600"
          />
          <StatCard
            icon={completionPercentage === 100 ? 'check_circle' : 'donut_large'}
            label="Progress"
            value={`${completionPercentage}% Complete`}
            iconBg={completionPercentage === 100 ? 'bg-emerald-50 text-emerald-600' : completionPercentage >= 50 ? 'bg-blue-50 text-blue-900' : 'bg-amber-50 text-amber-700'}
          />
        </div>

        {/* Case Narrative */}
        {caseOverview?.narrative && (
          <section className="space-y-3">
            <h2 className="text-[11px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px] text-blue-600">description</span>
              Overview Narrative
            </h2>
            <article className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
              <p className="text-sm leading-relaxed text-slate-700 whitespace-pre-wrap">{caseOverview.narrative}</p>
            </article>
          </section>
        )}

        {/* Agency Workflows */}
        <section className="space-y-3">
          <h2 className="text-[11px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px] text-purple-600">account_tree</span>
            Involved Workflows
            <span className="ml-1.5 text-[10px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-full">{trackingAgencies.length}</span>
          </h2>
          {trackingAgencies.length > 0 ? (
            <div className="space-y-4">
              {trackingAgencies.map((agency) => (
                <AgencyCard key={agency.name} {...agency} />
              ))}
            </div>
          ) : (
            <article className="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center">
              <span className="material-symbols-outlined text-4xl text-slate-300 block mb-2">hourglass_empty</span>
              <h3 className="text-sm font-bold text-slate-900">No agencies assigned yet</h3>
              <p className="mt-1 text-sm text-slate-500 max-w-xs mx-auto">Your case manager is processing the initial assessments.</p>
            </article>
          )}
        </section>

        {/* Activity Timeline */}
        <section className="space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 className="text-[11px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px] text-emerald-600">history</span>
              Activity Timeline
            </h2>
            <div className="flex flex-wrap items-center gap-2">
              {/* Agency filter */}
              {timelineAgencyNames.length > 0 && (
                <select
                  value={timelineAgencyFilter}
                  onChange={e => setTimelineAgencyFilter(e.target.value)}
                  className="text-xs font-semibold text-slate-600 border border-slate-200 rounded-md px-2.5 py-1.5 bg-white hover:border-slate-300 transition-colors cursor-pointer outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="ALL">All Agencies</option>
                  {timelineAgencyNames.map(a => <option key={a} value={a}>{a}</option>)}
                </select>
              )}
              {/* Type filter */}
              <select
                value={timelineTypeFilter}
                onChange={e => setTimelineTypeFilter(e.target.value)}
                className="text-xs font-semibold text-slate-600 border border-slate-200 rounded-md px-2.5 py-1.5 bg-white hover:border-slate-300 transition-colors cursor-pointer outline-none focus:ring-1 focus:ring-blue-900"
              >
                {EVENT_TYPE_OPTIONS.map(opt => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
              {/* Clear filters */}
              {hasActiveFilters && (
                <button
                  onClick={clearFilters}
                  className="text-xs font-bold text-blue-600 hover:text-blue-800 px-2 py-1.5 transition-colors"
                >
                  Clear
                </button>
              )}
            </div>
          </div>

          {/* Filter results count */}
          {milestoneTimeline && milestoneTimeline.length > 0 && (
            <p className="text-[11px] text-slate-400 font-medium px-0.5">
              Showing {filteredTimeline.length} of {milestoneTimeline.length} events
            </p>
          )}

          {filteredTimeline.length === 0 ? (
            <article className="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center">
              <span className="material-symbols-outlined text-4xl text-slate-300 block mb-2">history</span>
              <p className="text-sm text-slate-500">No activity matches your filters.</p>
              {hasActiveFilters && (
                <button onClick={clearFilters} className="mt-2 text-xs font-bold text-blue-600 hover:text-blue-800 underline">
                  Clear filters
                </button>
              )}
            </article>
          ) : (
            <article className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
              <div className="relative">
                <div className="absolute left-[13px] top-2 bottom-2 w-px bg-slate-200" />
                <div className="space-y-6">
                  {filteredTimeline.map((item, index) => {
                    const cfg = EVENT_CONFIG[item.type] ?? EVENT_CONFIG.milestone;
                    return (
                      <div key={`${item.date}-${index}`} className="relative flex gap-4 items-start group">
                        <div className={`z-10 flex h-7 w-7 items-center justify-center rounded-full border bg-white ${cfg.dot} shadow-sm shrink-0`}>
                          <span className="material-symbols-outlined text-[14px]">{cfg.icon}</span>
                        </div>
                        <div className="min-w-0 pt-0.5 flex-1">
                          <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-tight">
                              {formatEventDate(item.date)}
                            </span>
                            <span className="text-[10px] text-slate-400 font-medium hidden sm:inline">
                              {formatHumanDate(item.date)}
                            </span>
                            {item.agency && (
                              <span className="text-[11px] font-semibold text-slate-400 border-l border-slate-200 pl-2">
                                {item.agency}
                              </span>
                            )}
                          </div>
                          <h4 className="text-sm font-bold text-slate-900 mt-1 leading-snug">{item.title}</h4>
                          {item.description && (
                            <p className="text-xs text-slate-500 mt-1 leading-relaxed max-w-prose">{item.description}</p>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </article>
          )}
        </section>

        {/* Feedback Request */}
        {(() => {
          const isCompleted = trackingAgencies.some(a => a.status === 'COMPLETED');
          const feedbackNtfn = caseNotifications?.items?.find(n => n.type === 'feedback_request');
          if (!isCompleted || !feedbackNtfn) return null;

          const d = feedbackNtfn.data || {};
          const params = new URLSearchParams({
            tracking_token: d.tracking_token || '',
            case_id: trackedCase?.id || '',
            agency_id: d.agency_id || '',
            referral_id: d.referral_id || '',
            service_name: d.service_name || '',
          }).toString();

          return (
            <section className="space-y-3">
              <h2 className="text-[11px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
                <span className="material-symbols-outlined text-[16px] text-blue-600">rate_review</span>
                Feedback
              </h2>
              <div className="bg-white rounded-xl border border-blue-200 shadow-sm p-5 flex items-start gap-4">
                <div className="shrink-0 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 border border-blue-100 text-blue-600">
                  <span className="material-symbols-outlined text-lg">rate_review</span>
                </div>
                <div className="min-w-0 flex-1">
                  <h3 className="text-sm font-bold text-slate-900">Share your feedback</h3>
                  <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                    Your input helps us improve our services.
                  </p>
                  <Link
                    href={`/feedbacks/submit-page?${params}`}
                    className="mt-3 inline-flex items-center gap-1.5 rounded-md bg-blue-900 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-800 transition-colors"
                  >
                    Provide Feedback
                    <span className="material-symbols-outlined text-[14px] text-blue-300">arrow_right_alt</span>
                  </Link>
                </div>
              </div>
            </section>
          );
        })()}

      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
