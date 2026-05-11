import { useEffect, useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TrackingNotFoundState from '@/Components/TrackingNotFoundState';
import ChatBot from '@/Components/ChatBot';

function AgencyCard({ name, note, status, statusTone, borderTone, textTone, lineTone, steps, latestMilestoneLabel, latestMilestonePath }) {
  const completedCount = steps.filter((s) => s.state === 'complete').length;
  const progressPercent = (completedCount / (steps.length - 1)) * 100;

  return (
    <article className={`rounded-xl border border-slate-200 bg-white p-4 sm:p-6 shadow-sm overflow-hidden relative transition hover:shadow-md border-t-4 ${borderTone.replace('border-', 'border-t-')}`}>
      <div className="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
        <div>
          <h3 className="text-lg font-bold text-slate-900 leading-tight">{name}</h3>
          <p className="text-xs text-slate-500 mt-1.5 leading-tight">{note}</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {latestMilestonePath && (
            <Link
              href={latestMilestonePath}
              className={`inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-bold uppercase tracking-wider ${textTone} hover:underline`}
            >
              <span className="material-symbols-outlined text-[13px]">list_alt</span>
              View Milestones
            </Link>
          )}
          <span className={`px-3 py-1 text-[11px] font-bold uppercase tracking-widest rounded-full ${statusTone} min-w-[88px] text-center`}>{status}</span>
        </div>
      </div>

      <div className="relative mt-8 px-2 sm:px-4">
        <div className="absolute left-[28px] right-[28px] sm:left-[32px] sm:right-[32px] top-[15px] h-[3px] rounded-full bg-slate-100" />
        <div className="absolute left-[28px] right-[28px] sm:left-[32px] sm:right-[32px] top-[15px] h-[3px] rounded-full">
          <div className={`h-full transition-all duration-500 ${lineTone}`} style={{ width: `${progressPercent}%` }} />
        </div>

        <div className="relative z-10 grid grid-cols-4 gap-2 sm:gap-3">
          {steps.map((step) => (
            <div key={`${name}-${step.label}`} className="flex min-w-0 flex-col items-center text-center">
              <div
                className={`mb-2.5 flex h-8 w-8 items-center justify-center rounded-full ${
                  step.state === 'complete' ? 'bg-white text-primary ring-2 ring-primary shadow-sm' :
                  step.state === 'active' ? 'bg-white text-secondary ring-2 ring-secondary shadow-sm' :
                  'bg-surface-container text-outline-variant font-black'
                }`}
              >
                {step.state === 'active' ? (
                  <span className="material-symbols-outlined text-[15px] text-secondary" style={{ fontVariationSettings: "'FILL' 1, 'wght' 700" }}>sync</span>
                ) : step.state === 'pending' ? (
                  <span className="material-symbols-outlined text-[10px] font-black">radio_button_unchecked</span>
                ) : (
                  <span className="material-symbols-outlined text-[16px] font-bold text-primary" style={{ fontVariationSettings: "'FILL' 1, 'wght' 700" }}>check</span>
                )}
              </div>
              <span
                className={`text-[10px] sm:text-[11px] leading-tight font-bold uppercase tracking-wider break-words ${
                  step.state === 'active' ? textTone :
                  step.state === 'complete' ? 'text-on-surface' :
                  'text-on-surface-variant'
                }`}
              >
                {step.label}
              </span>
              {step.state === 'active' && latestMilestoneLabel && (
                latestMilestonePath ? (
                  <Link href={latestMilestonePath} className="mt-1 text-center text-[10px] font-semibold text-primary underline decoration-primary/50 underline-offset-2 hover:text-primary">
                    {latestMilestoneLabel}
                  </Link>
                ) : (
                  <span className="mt-1 text-center text-[10px] font-semibold text-primary">{latestMilestoneLabel}</span>
                )
              )}
            </div>
          ))}
        </div>
      </div>
    </article>
  );
}

export default function TrackingShow({ trackingId, trackedCase, caseOverview, caseTimeline, trackingAgencies }) {
  const [timelineAgencyFilter, setTimelineAgencyFilter] = useState('ALL');

  const trackingAgencyNames = useMemo(() => {
    const names = caseTimeline
      .map((i) => i.agency)
      .filter((a, i, arr) => a && a !== 'Bayanihan' && arr.indexOf(a) === i);
    return names.sort((a, b) => a.localeCompare(b));
  }, [caseTimeline]);

  useEffect(() => {
    if (timelineAgencyFilter !== 'ALL' && !trackingAgencyNames.includes(timelineAgencyFilter)) {
      setTimelineAgencyFilter('ALL');
    }
  }, [timelineAgencyFilter, trackingAgencyNames]);

  const filteredTimeline = useMemo(() => {
    if (timelineAgencyFilter === 'ALL') return caseTimeline;
    return caseTimeline.filter((item) => item.agency === timelineAgencyFilter);
  }, [caseTimeline, timelineAgencyFilter]);

  if (!trackedCase) {
    return (
      <div className="bg-surface font-body text-on-surface">
        <Head title="Tracking ID Not Found" />
        <AppHeader />
        <main className="mx-auto w-full max-w-[1100px] px-5 py-9">
          <TrackingNotFoundState description="We could not find a case matching this tracking ID. Please verify your ID and try again." />
        </main>
        <AppFooter />
        <ChatBot />
      </div>
    );
  }

  const involvedAgencyCount = trackingAgencies.length;
  const isComplete = trackedCase.status === 'COMPLETED';
  const caseHealthTone = isComplete
    ? 'bg-slate-50 text-slate-700 border-slate-200'
    : 'bg-blue-50 text-blue-800 border-blue-200';

  return (
    <div className="bg-[#F5F7FA] min-h-screen font-body text-slate-900">
      <Head title={`Case ${trackedCase.caseNo}`} />
      <AppHeader />

      <main className="mx-auto w-full max-w-[1200px] px-4 py-6 sm:px-6 sm:py-8 lg:py-12">
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
          <section className="space-y-8 lg:col-span-8">
            <header className="rounded-xl border border-slate-200 bg-white px-4 py-5 sm:px-6 sm:py-6 shadow-sm flex flex-col justify-between gap-4 md:flex-row md:items-center relative overflow-hidden">
              <div className="absolute top-0 left-0 w-1 h-full bg-[#0b5c92]" />
              <div className="pl-2 sm:pl-3">
                <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1">Case Tracking Status</p>
                <h1 className="font-headline text-2xl sm:text-3xl font-black uppercase tracking-tight text-slate-900">
                  {trackingId}
                </h1>
                <p className="mt-2 text-sm font-medium text-slate-600">
                  {involvedAgencyCount === 0
                    ? 'Your case has been created, but no agency referrals have been sent yet.'
                    : involvedAgencyCount === 1
                    ? 'Your case is currently being handled by one agency.'
                    : `Your case is currently being handled by ${involvedAgencyCount} agencies.`}
                </p>
              </div>
              <div className={`flex items-center gap-2 self-start md:self-auto rounded-lg border px-4 py-2 ${caseHealthTone}`}>
                <span className="material-symbols-outlined text-[18px]" style={{ fontVariationSettings: "'FILL' 1, 'wght' 600" }}>
                  {isComplete ? 'check_circle' : 'pending_actions'}
                </span>
                <span className="text-[11px] font-bold uppercase tracking-widest">
                  Status: {trackedCase.status}
                </span>
              </div>
            </header>

            <section className="space-y-4">
              <h2 className="px-2 text-[11px] font-bold uppercase tracking-[0.15em] text-slate-500">Case Overview</h2>
              <article className="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
                {caseOverview.narrative && (
                  <div className="mb-8 rounded-lg bg-slate-50 p-5 border border-slate-100">
                    <h3 className="mb-3 text-[11px] font-bold uppercase tracking-[0.1em] text-[#0b5c92] flex items-center gap-1.5">
                      <span className="material-symbols-outlined text-[14px]">subject</span> Case Narrative
                    </h3>
                    <p className="text-sm leading-relaxed text-slate-700 whitespace-pre-wrap">{caseOverview.narrative}</p>
                  </div>
                )}

                <div className="grid grid-cols-1 gap-x-12 gap-y-10 lg:grid-cols-3">
                  {caseOverview.ofw && (
                    <div>
                      <h3 className="border-b border-slate-200 pb-3 text-[11px] font-bold uppercase tracking-widest text-slate-900">OFW Profile</h3>
                      <dl className="mt-5 space-y-4 text-sm">
                        <div>
                          <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Full Name</dt>
                          <dd className="font-semibold text-slate-900">{caseOverview.ofw.fullName}</dd>
                        </div>
                        <div>
                          <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Date of Birth</dt>
                          <dd className="font-semibold text-slate-900">{caseOverview.ofw.dateOfBirth || 'N/A'}</dd>
                        </div>
                        <div>
                          <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Gender</dt>
                          <dd className="font-semibold text-slate-900">{caseOverview.ofw.gender || 'N/A'}</dd>
                        </div>
                        <div>
                          <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Home Address</dt>
                          <dd className="font-semibold text-slate-900 leading-snug">{caseOverview.ofw.homeAddress || 'N/A'}</dd>
                        </div>
                      </dl>
                    </div>
                  )}

                  <div>
                    <h3 className="border-b border-slate-200 pb-3 text-[11px] font-bold uppercase tracking-widest text-slate-900">Work History</h3>
                    <dl className="mt-5 space-y-4 text-sm">
                      <div>
                        <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Last Country</dt>
                        <dd className="font-semibold text-slate-900">{caseOverview.workHistory?.lastCountry || 'N/A'}</dd>
                      </div>
                      <div>
                        <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Last Position</dt>
                        <dd className="font-semibold text-slate-900">{caseOverview.workHistory?.lastPosition || 'N/A'}</dd>
                      </div>
                      <div>
                        <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Arrival Date</dt>
                        <dd className="font-semibold text-slate-900">{caseOverview.workHistory?.arrivalDate || 'N/A'}</dd>
                      </div>
                    </dl>
                  </div>
                </div>
              </article>
            </section>

            <section className="space-y-4">
              <h2 className="px-2 text-[11px] font-bold uppercase tracking-[0.15em] text-slate-500">Agency Breakdown</h2>

              {trackingAgencies.length > 0 ? (
                <div className="grid grid-cols-1 gap-4">
                  {trackingAgencies.map((agency) => (
                    <AgencyCard
                      key={agency.name}
                      {...agency}
                    />
                  ))}
                </div>
              ) : (
                <article className="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                  <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                    <span className="material-symbols-outlined text-slate-400">hourglass_empty</span>
                  </div>
                  <h3 className="text-sm font-bold text-slate-900">No referrals yet</h3>
                  <p className="mt-2 text-sm text-slate-500">
                    This case exists in the system, but it has not been referred to any agencies yet.
                  </p>
                </article>
              )}
            </section>
          </section>

          <aside className="col-span-1 lg:col-span-4 mt-6 lg:mt-0">
            <div id="case-timeline" className="h-full rounded-xl border border-slate-200 bg-white p-4 sm:p-6 shadow-sm">
              <div className="mb-6 flex flex-col gap-4 border-b border-slate-100 pb-5">
                <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-[18px] text-[#0b5c92]">history</span>
                  <h2 className="text-[11px] font-bold uppercase tracking-widest text-[#0b5c92]">Case Timeline</h2>
                </div>
                <select
                  value={timelineAgencyFilter}
                  onChange={(e) => setTimelineAgencyFilter(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-[#0b5c92]"
                >
                  <option value="ALL">All agencies</option>
                  {trackingAgencyNames.map((agency) => (
                    <option key={agency} value={agency}>{agency}</option>
                  ))}
                </select>
              </div>

              <div className="relative pt-2">
                <div className="absolute left-[14px] top-6 bottom-6 w-0.5 bg-slate-100" />
                <div className="flex flex-col-reverse gap-6 sm:gap-8">
                  {filteredTimeline.map((item, index) => (
                    <article key={`${item.date}-${index}`} className="relative grid grid-cols-[28px_1fr] items-start gap-3 sm:gap-4">
                      <div className="z-10 flex h-7 w-7 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white shadow-sm p-0.5">
                        {item.logoUrl ? (
                          <img src={item.logoUrl} alt={`${item.agency} timeline source`} className="h-full w-full object-contain rounded-full" />
                        ) : (
                          <span className="material-symbols-outlined text-[12px] text-slate-400">account_balance</span>
                        )}
                      </div>
                      <div className="min-w-0">
                        <p className="mb-1 text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.1em] text-primary">{new Date(item.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                        <h3 className="mb-1 text-[12px] sm:text-[13px] font-bold leading-[1.35] text-on-surface">{item.title}</h3>
                        <p className="whitespace-pre-wrap text-[11px] sm:text-[12px] leading-[1.45] text-slate-500">{item.detail}</p>
                      </div>
                    </article>
                  ))}
                </div>
              </div>
            </div>
          </aside>
        </div>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
