import { Head } from '@inertiajs/react';
import OfwBreadcrumbs from '@/Components/OfwBreadcrumbs';
import AgencyLogo from '@/Components/AgencyLogo';
import safeRoute from '@/utils/safeRoute';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import UnifiedTimeline from '@/Components/Timeline';

const MILESTONE_EVENT_CONFIG = {
  milestone_added: { dot: 'bg-emerald-50 border-emerald-200 text-emerald-600', icon: 'flag_circle' },
};

const STATUS_CONFIG = {
  IN_PROGRESS: { label: 'In Progress', icon: 'radio_button_checked', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
  RESOLVED: { label: 'Resolved', icon: 'check_circle', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  BEING_PREPARED: { label: 'Under Preparation', icon: 'layers', bg: 'bg-info-container text-on-info-container border-info' },
  ARCHIVED: { label: 'Archived', icon: 'archive', bg: 'bg-slate-100 text-slate-600 border-slate-200' },
  UNKNOWN: { label: 'Status Unavailable', icon: 'help_outline', bg: 'bg-slate-100 text-slate-600 border-slate-200' },
};

const REFERRAL_STATUS_CONFIG = {
  PENDING: { label: 'Awaiting receipt', icon: 'schedule', bg: 'bg-slate-100 text-slate-600 border-slate-200' },
  PROCESSING: { label: 'In process', icon: 'radio_button_checked', bg: 'bg-info-container text-on-info-container border-info' },
  FOR_COMPLIANCE: { label: 'Needs documents', icon: 'description', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
  COMPLETED: { label: 'Completed', icon: 'check_circle', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  REJECTED: { label: 'Unable to assist', icon: 'cancel', bg: 'bg-red-50 text-red-600 border-red-200' },
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

function Panel({ title, icon, children }) {
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
        <span className="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">{icon}</span>
        <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">{title}</h2>
      </header>
      <div className="p-5">{children}</div>
    </section>
  );
}

export default function AgencyMilestones({ trackingId, trackedCase, agencyMilestones, backUrl }) {
  const config = STATUS_CONFIG[trackedCase?.status] ?? STATUS_CONFIG.UNKNOWN;
  const milestoneCount = agencyMilestones?.milestoneCount ?? 0;
  const milestones = agencyMilestones?.milestones ?? [];
  const latestUpdate = agencyMilestones?.latestUpdate;
  const backHref = backUrl ?? route('track.show', { tracker_number: trackingId });
  const referralConfig = REFERRAL_STATUS_CONFIG[agencyMilestones?.status] ?? REFERRAL_STATUS_CONFIG.PENDING;

  const content = (
    <div className="space-y-6">
      <div>
        <OfwBreadcrumbs items={backUrl ? [
          { label: 'My Cases', href: safeRoute('ofw.dashboard', undefined, '/my-cases') },
          { label: `Case ${trackingId}`, href: backHref },
          { label: `${agencyMilestones?.agencyShort || agencyMilestones?.agencyName || 'Agency'} referral` },
        ] : [
          { label: 'Case tracking', href: backHref },
          { label: 'Agency Milestones' },
        ]} />
        <h1 className="mt-2 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">Agency referral details</h1>
        <p className="mt-1 text-sm text-slate-500">Follow this agency referral from receipt through requests, responses, and completion. Newest activity appears first.</p>
      </div>

      <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(280px,1fr)]">
        <div className="min-w-0 space-y-5">
          <Panel title="Referral activity" icon="timeline">
            {milestones.length === 0 ? (
              <div className="py-6 text-center">
                <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">hourglass_empty</span>
                <h3 className="mt-2 text-sm font-bold text-slate-900">No referral activity recorded yet</h3>
                <p className="mx-auto mt-1 max-w-md text-sm leading-relaxed text-slate-500">Recorded updates will appear here as your referral progresses.</p>
              </div>
            ) : <UnifiedTimeline items={milestones.map((milestone) => ({ ...milestone, actor: milestone.actor ?? milestone.by }))} eventConfig={MILESTONE_EVENT_CONFIG} />}
          </Panel>
        </div>

        <aside className="min-w-0 space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex min-w-0 items-start gap-3">
              <AgencyLogo name={agencyMilestones?.agencyName ?? 'Agency'} logoUrl={agencyMilestones?.agencyLogoUrl} className="h-12 w-12" />
              <div className="min-w-0">
                <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">Referred agency</p>
                <h2 className="mt-1 min-w-0 break-words [overflow-wrap:anywhere] font-headline text-[16px] font-extrabold tracking-tight text-slate-900">{agencyMilestones?.agencyName ?? 'Agency'}</h2>
              </div>
            </div>
            <dl className="mt-4 space-y-3 border-t border-slate-100 pt-4">
              <div className="flex items-start justify-between gap-3">
                <dt className="text-xs text-slate-500">Referral status</dt>
                <dd className={`inline-flex max-w-[65%] items-center gap-1.5 rounded-md border px-2 py-1 text-right text-[11px] font-semibold ${referralConfig.bg}`}>
                  <span className="material-symbols-outlined text-[14px]" aria-hidden="true">{referralConfig.icon}</span>
                  {formatStatusLabel(agencyMilestones?.status ?? 'PENDING')}
                </dd>
              </div>
              <div className="flex items-start justify-between gap-3">
                <dt className="text-xs text-slate-500">Case status</dt>
                <dd className={`inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold ${config.bg}`}>
                  <span className="material-symbols-outlined text-[14px]" aria-hidden="true">{config.icon}</span>{config.label}
                </dd>
              </div>
              {trackedCase?.caseNo && <div className="flex items-start justify-between gap-3"><dt className="text-xs text-slate-500">Case number</dt><dd className="text-right text-xs font-semibold text-slate-700">{trackedCase.caseNo}</dd></div>}
              {trackedCase?.clientName && <div className="flex items-start justify-between gap-3"><dt className="text-xs text-slate-500">Client</dt><dd className="min-w-0 break-words text-right text-xs font-semibold text-slate-700">{trackedCase.clientName}</dd></div>}
              <div className="flex items-start justify-between gap-3"><dt className="shrink-0 text-xs text-slate-500">Tracking number</dt><dd className="min-w-0 break-words [overflow-wrap:anywhere] text-right text-xs font-semibold text-slate-700">{trackingId}</dd></div>
              <div className="flex items-start justify-between gap-3"><dt className="text-xs text-slate-500">Milestones</dt><dd className="text-right text-xs font-semibold text-slate-700">{milestoneCount}</dd></div>
            </dl>
          </section>

          <Panel title="Latest update" icon="update">
            {latestUpdate ? (
              <div className="flex items-start gap-3">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700"><span className="material-symbols-outlined text-[18px]" aria-hidden="true">flag</span></span>
                <div className="min-w-0 flex-1">
                  <h3 className="break-words text-sm font-bold text-slate-900">{latestUpdate.title}</h3>
                  <p className="mt-1 text-[11px] text-slate-400">{formatHumanDate(latestUpdate.date)}</p>
                  {latestUpdate.description && <p className="mt-2 text-[13px] leading-relaxed text-slate-600">{latestUpdate.description}</p>}
                  <p className="mt-2 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">{latestUpdate.actor ?? latestUpdate.by}</p>
                </div>
              </div>
            ) : <div><h3 className="text-sm font-bold text-slate-900">No update yet</h3><p className="mt-1 text-[13px] leading-relaxed text-slate-600">This referral is still waiting for the first milestone update.</p></div>}
          </Panel>
        </aside>
      </div>
    </div>
  );

  return backUrl
    ? <OfwPortalLayout title={`${agencyMilestones?.agencyName ?? 'Agency'} milestones`}>{content}</OfwPortalLayout>
    : <>
      <Head title={`${agencyMilestones?.agencyName ?? 'Agency'} milestones`} />
      <AppHeader />
      <div className="min-h-screen bg-slate-50 font-body text-slate-800">
        <main className="mx-auto w-full max-w-7xl space-y-6 px-4 pb-12 pt-24 sm:px-6 lg:px-8">{content}</main>
      </div>
      <AppFooter />
      <ChatBot />
    </>;
}
