import { Head, Link } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';

const STATUS_CONFIG = {
  IN_PROGRESS: { label: 'In Progress', icon: 'radio_button_checked', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
  RESOLVED: { label: 'Resolved', icon: 'check_circle', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  BEING_PREPARED: { label: 'Under Preparation', icon: 'layers', bg: 'bg-blue-50 text-blue-700 border-blue-200' },
  ARCHIVED: { label: 'Archived', icon: 'archive', bg: 'bg-slate-100 text-slate-600 border-slate-200' },
  UNKNOWN: { label: 'Status Unavailable', icon: 'help_outline', bg: 'bg-slate-100 text-slate-600 border-slate-200' },
};

const REFERRAL_STATUS_CONFIG = {
  PENDING:        { label: 'Awaiting receipt',    icon: 'schedule',              bg: 'bg-slate-100 text-slate-600 border-slate-200' },
  PROCESSING:     { label: 'In process',          icon: 'radio_button_checked',  bg: 'bg-blue-50 text-blue-700 border-blue-200' },
  FOR_COMPLIANCE: { label: 'Needs documents',     icon: 'description',           bg: 'bg-amber-50 text-amber-700 border-amber-200' },
  COMPLETED:      { label: 'Completed',           icon: 'check_circle',          bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  REJECTED:       { label: 'Unable to assist',    icon: 'cancel',                bg: 'bg-red-50 text-red-600 border-red-200' },
};

function formatHumanDate(dateStr) {
  const date = new Date(dateStr);

  return `${date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })} at ${date.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })}`;
}

function formatStatusLabel(status) {
  if (!status) return 'Unavailable';

  return status
    .replace(/_/g, ' ')
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function InfoCard({ icon, label, value, tone = 'slate' }) {
  const toneClasses = {
    slate: 'bg-slate-50 text-slate-700 border-slate-200',
    blue: 'bg-blue-50 text-blue-700 border-blue-200',
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    amber: 'bg-amber-50 text-amber-700 border-amber-200',
  }[tone];

  return (
    <div className="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{label}</p>
          <p className="mt-2 text-sm font-semibold text-slate-900 leading-relaxed whitespace-pre-wrap">{value}</p>
        </div>
        <span className={`flex h-10 w-10 items-center justify-center rounded-lg border ${toneClasses}`}>
          <span className="material-symbols-outlined text-[18px]">{icon}</span>
        </span>
      </div>
    </div>
  );
}

export default function AgencyMilestones({ trackingId, trackedCase, agencyMilestones }) {
  const config = STATUS_CONFIG[trackedCase?.status] ?? STATUS_CONFIG.UNKNOWN;
  const milestoneCount = agencyMilestones?.milestoneCount ?? 0;
  const milestones = agencyMilestones?.milestones ?? [];
  const latestUpdate = agencyMilestones?.latestUpdate;

  return (
    <div className="min-h-screen bg-slate-50 font-body text-slate-800">
      <Head title={`${agencyMilestones?.agencyName ?? 'Agency'} milestones`} />
      <AppHeader />

      <main className="mx-auto w-full max-w-5xl px-4 pt-8 pb-12 sm:px-6 lg:px-8 space-y-8">
        <div className="flex flex-wrap items-center justify-between gap-3 pt-20">
          <Link
            href={route('track.show', { tracker_number: trackingId })}
            className="inline-flex items-center gap-2 text-sm font-semibold text-blue-800 hover:text-blue-900 transition-colors"
          >
            <span className="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to case tracking
          </Link>

          <div className="flex items-center gap-2 text-sm text-slate-500">
            <span className="text-slate-400">Case status:</span>
            <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold shadow-sm ${config.bg}`}>
              <span className="material-symbols-outlined text-[14px]">{config.icon}</span>
              {config.label}
            </span>
          </div>
        </div>

        <header className="bg-primary px-6 py-8 text-white shadow-2xl sm:px-10 sm:py-10">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Agency milestones</p>
              <h1 className="mt-2 font-headline text-2xl font-extrabold tracking-tight sm:text-3xl">
                {agencyMilestones?.agencyName ?? 'Agency'}
              </h1>
              {trackedCase?.clientName && (
                <p className="mt-1.5 text-sm text-primary-fixed/90">{trackedCase.clientName} · Tracking ID: {trackingId}</p>
              )}
            </div>
            {agencyMilestones?.status && (() => {
              const refConfig = REFERRAL_STATUS_CONFIG[agencyMilestones.status] ?? REFERRAL_STATUS_CONFIG.PENDING;
              return (
                <p className="shrink-0 text-sm text-primary-fixed/70">
                  Referral status: <span className={`ml-1.5 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold ${refConfig.bg}`}><span className="material-symbols-outlined text-[14px]">{refConfig.icon}</span>{formatStatusLabel(agencyMilestones.status)}</span>
                </p>
              );
            })()}
          </div>

          <div className="mt-4 flex flex-wrap items-center gap-2 text-xs">
            {trackedCase?.caseNo && (
              <span className="rounded-full border border-white/30 px-3 py-1.5 font-semibold">{trackedCase.caseNo}</span>
            )}
            {milestoneCount > 0 && (
              <span className="rounded-full border border-emerald-300/50 bg-emerald-500/20 px-3 py-1.5 font-semibold text-emerald-100">{milestoneCount} milestone{milestoneCount !== 1 ? 's' : ''}</span>
            )}
          </div>

          <p className="mt-5 max-w-2xl text-sm leading-relaxed text-primary-fixed/80">
            A clear view of the milestones for this agency's referral, with the latest update and current status in one place.
          </p>
        </header>

        <section className="grid gap-4 md:grid-cols-2">
          <InfoCard
            icon="assignment"
            label="Requested services"
            value={agencyMilestones?.requiredServices || 'No service request details were provided.'}
            tone={agencyMilestones?.requiredServices ? 'blue' : 'slate'}
          />

          <InfoCard
            icon="sticky_note_2"
            label="Agency notes"
            value={agencyMilestones?.notes || 'No notes were added for this referral.'}
            tone={agencyMilestones?.notes ? 'amber' : 'slate'}
          />
        </section>

        <section className="space-y-3">
          <h2 className="text-[11px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px] text-emerald-600">update</span>
            Latest update
          </h2>

          <article className="rounded-md border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
            {latestUpdate ? (
              <div className="flex items-start gap-4">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-white text-emerald-600 shadow-sm">
                  <span className="material-symbols-outlined text-[20px]">flag</span>
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <h3 className="text-sm font-bold text-slate-900">{latestUpdate.title}</h3>
                    <span className="text-[11px] text-slate-400">{formatHumanDate(latestUpdate.date)}</span>
                  </div>
                  {latestUpdate.description && <p className="mt-1 text-sm leading-relaxed text-slate-600">{latestUpdate.description}</p>}
                  <p className="mt-2 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">{latestUpdate.by}</p>
                </div>
              </div>
            ) : (
              <div className="flex items-start gap-4">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-white text-emerald-600 shadow-sm">
                  <span className="material-symbols-outlined text-[20px]">hourglass_empty</span>
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">No update yet</h3>
                  <p className="mt-1 text-sm leading-relaxed text-slate-600">This referral is still waiting for the first milestone update.</p>
                </div>
              </div>
            )}
          </article>
        </section>

        <section className="space-y-3">
          <h2 className="text-[11px] font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
            <span className="material-symbols-outlined text-[16px] text-blue-600">timeline</span>
            Milestone timeline
          </h2>

          {milestones.length === 0 ? (
            <article className="rounded-md border border-slate-300 bg-white p-10 text-center shadow-sm">
              <span className="material-symbols-outlined block text-4xl text-slate-300">hourglass_empty</span>
              <h3 className="mt-3 text-sm font-bold text-slate-900">No milestones recorded yet</h3>
              <p className="mx-auto mt-1 max-w-md text-sm leading-relaxed text-slate-500">
                The agency has received your referral, but no milestone updates have been posted yet.
              </p>
            </article>
          ) : (
            <article className="rounded-md border border-slate-300 bg-white p-6 shadow-sm sm:p-8">
              <div className="relative">
                <div className="absolute left-[13px] top-2 bottom-2 w-px bg-slate-200" />
                <div className="space-y-6">
                  {milestones.map((milestone) => (
                    <div key={`${milestone.date}-${milestone.title}`} className="relative flex gap-4 items-start">
                      <div className="z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 shadow-sm">
                        <span className="material-symbols-outlined text-[14px]">milestone</span>
                      </div>
                      <div className="min-w-0 flex-1 pt-0.5">
                        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                          <span className="text-[11px] font-bold uppercase tracking-tight text-slate-500">{formatHumanDate(milestone.date)}</span>
                          <span className="text-[11px] font-semibold text-slate-400">{milestone.by}</span>
                        </div>
                        <h3 className="mt-1 text-sm font-bold text-slate-900 leading-snug">{milestone.title}</h3>
                        {milestone.description && <p className="mt-1 text-sm leading-relaxed text-slate-600 max-w-prose">{milestone.description}</p>}
                      </div>
                    </div>
                  ))}
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
