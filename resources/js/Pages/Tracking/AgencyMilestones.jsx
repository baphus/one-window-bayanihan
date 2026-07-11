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
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
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
    <div className="min-h-screen bg-surface font-body text-on-surface">
      <Head title={`${agencyMilestones?.agencyName ?? 'Agency'} milestones`} />
      <AppHeader />

      <main className="mx-auto w-full max-w-5xl px-4 pt-24 pb-12 sm:px-6 lg:px-8 space-y-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Link
            href={route('track.show', { tracker_number: trackingId })}
            className="inline-flex items-center gap-2 text-sm font-semibold text-blue-800 hover:text-blue-900 transition-colors"
          >
            <span className="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to case tracking
          </Link>

          <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold shadow-sm ${config.bg}`}>
            <span className="material-symbols-outlined text-[16px]">{config.icon}</span>
            {config.label}
          </span>
        </div>

        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="bg-gradient-to-br from-slate-50 via-white to-blue-50/60 px-5 py-6 sm:px-8 sm:py-8">
            <div className="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
              <div className="max-w-3xl space-y-3">
                <p className="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-400">Agency milestones</p>
                <h1 className="font-headline text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                  {agencyMilestones?.agencyName ?? 'Agency'}
                </h1>
                <p className="max-w-2xl text-sm leading-relaxed text-slate-600">
                  A clear view of the milestones for this agency’s referral, with the latest update and current status in one place.
                </p>
                {trackedCase?.clientName && (
                  <p className="text-sm font-semibold text-slate-700">Case holder: {trackedCase.clientName}</p>
                )}

                <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                  <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 font-semibold">Tracking ID: {trackingId}</span>
                  {trackedCase?.caseNo && (
                    <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 font-semibold">Case No. {trackedCase.caseNo}</span>
                  )}
                  {milestoneCount > 0 && (
                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 font-semibold text-emerald-700">{milestoneCount} milestone{milestoneCount !== 1 ? 's' : ''}</span>
                  )}
                </div>
              </div>

              <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:min-w-[240px]">
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Case status</p>
                <div className="mt-2 flex items-center gap-2">
                  <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold ${config.bg}`}>
                    <span className="material-symbols-outlined text-[16px]">{config.icon}</span>
                    {config.label}
                  </span>
                </div>
                <p className="mt-3 text-sm leading-relaxed text-slate-600">
                  {agencyMilestones?.status ? `Referral status: ${formatStatusLabel(agencyMilestones.status)}` : 'Referral status is unavailable.'}
                </p>
              </div>
            </div>
          </div>
        </section>

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

          <article className="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
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
            <article className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
              <span className="material-symbols-outlined block text-4xl text-slate-300">hourglass_empty</span>
              <h3 className="mt-3 text-sm font-bold text-slate-900">No milestones recorded yet</h3>
              <p className="mx-auto mt-1 max-w-md text-sm leading-relaxed text-slate-500">
                The agency has received your referral, but no milestone updates have been posted yet.
              </p>
            </article>
          ) : (
            <article className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
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

        <div>
          <Link
            href={route('track.show', { tracker_number: trackingId })}
            className="inline-flex items-center gap-2 rounded-lg bg-blue-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-blue-800"
          >
            <span className="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to tracking overview
          </Link>
        </div>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
