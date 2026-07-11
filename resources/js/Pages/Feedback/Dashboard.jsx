import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatDisplayDate } from '@/lib/utils';

const WINDOW_OPTIONS = [
  { value: 'all', label: 'All time' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: '90d', label: 'Last 90 days' },
  { value: 'quarter', label: 'This quarter' },
  { value: 'year', label: 'This year' },
];

const DIMENSION_ORDER = ['Tangibles', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];

const DIMENSION_META = {
  Tangibles: { tone: 'text-indigo-700', bar: 'bg-indigo-600' },
  Reliability: { tone: 'text-emerald-700', bar: 'bg-emerald-600' },
  Responsiveness: { tone: 'text-amber-700', bar: 'bg-amber-600' },
  Assurance: { tone: 'text-violet-700', bar: 'bg-violet-600' },
  Empathy: { tone: 'text-rose-700', bar: 'bg-rose-600' },
};

const RATING_META = {
  1: 'bg-rose-600',
  2: 'bg-orange-600',
  3: 'bg-amber-500',
  4: 'bg-emerald-600',
  5: 'bg-emerald-700',
};

function toNumber(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function roundToTwo(value) {
  if (value === null || value === undefined || value === '') return null;
  return Math.round(Number(value) * 100) / 100;
}

function StarRating({ rating }) {
  const value = Math.round(toNumber(rating));
  return (
    <div className="flex items-center gap-0.5" aria-label={`Rating ${value} out of 5`}>
      {[1, 2, 3, 4, 5].map((star) => (
        <svg key={star} className={`h-4 w-4 ${star <= value ? 'text-yellow-400' : 'text-slate-200'}`} fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      ))}
    </div>
  );
}

function StatCard({ label, value, hint, icon }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-center gap-3">
        <div className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
          <span className="material-symbols-outlined text-[20px] leading-none">{icon}</span>
        </div>
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{label}</p>
          <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">{value}</p>
          {hint ? <p className="mt-0.5 text-xs text-slate-500">{hint}</p> : null}
        </div>
      </div>
    </div>
  );
}

function SectionCard({ title, description, children, dataTour }) {
  return (
    <section data-tour={dataTour} className="rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-100 px-5 py-4">
        <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">{title}</h2>
        {description ? <p className="mt-1 text-sm text-slate-500">{description}</p> : null}
      </div>
      <div className="px-5 py-5">{children}</div>
    </section>
  );
}

function EmptyState({ title, description }) {
  return (
    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
      <span className="material-symbols-outlined text-3xl text-slate-300">inbox</span>
      <p className="mt-2 text-sm font-semibold text-slate-800">{title}</p>
      <p className="mt-1 text-sm text-slate-500">{description}</p>
    </div>
  );
}

export default function FeedbackDashboard({
  stats = {},
  rating_distribution = {},
  dimension_averages = {},
  service_breakdown = [],
  recent_feedback = [],
  window: selectedWindow = 'all',
}) {
  const totalResponses = toNumber(stats.total_submitted);
  const ratings = [1, 2, 3, 4, 5].map((rating) => ({
    rating,
    count: toNumber(rating_distribution?.[rating] ?? rating_distribution?.[String(rating)] ?? 0),
  }));
  const maxDistributionCount = Math.max(...ratings.map((item) => item.count), 0);
  const hasDistribution = maxDistributionCount > 0;

  const handleWindowChange = (value) => {
    router.get(route('feedbacks.index'), { window: value }, { preserveState: true, preserveScroll: true });
  };

  return (
    <AppLayout title="Feedback Dashboard">
      <Head title="Feedback Dashboard" />

      <div className="space-y-6">
        <section data-tour="feedbacks-header" className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-col gap-4 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-2xl">
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Feedback</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">Feedback Dashboard</h1>
              <p className="mt-1 text-sm text-slate-500">Response volume, satisfaction ratings, and recent submissions for the selected period.</p>
            </div>

            <div data-tour="feedbacks-filters" className="w-full max-w-xs">
              <label htmlFor="feedback-window" className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Time window</label>
              <select
                id="feedback-window"
                value={selectedWindow}
                onChange={(e) => handleWindowChange(e.target.value)}
                className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-blue-900 focus:ring-1 focus:ring-blue-900"
              >
                {WINDOW_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </select>
            </div>
          </div>
        </section>

        <section data-tour="feedbacks-kpis" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <StatCard label="Total Sent" value={toNumber(stats.total_sent)} hint="Invitations issued" icon="send" />
          <StatCard label="Responses" value={totalResponses} hint="Submitted feedback" icon="rate_review" />
          <StatCard label="Response Rate" value={`${roundToTwo(stats.response_rate) ?? 0}%`} hint="Responses ÷ sent" icon="query_stats" />
          <StatCard label="Avg Rating" value={roundToTwo(stats.avg_rating) ?? '—'} hint="Overall rating" icon="star" />
          <StatCard label="Avg SERVQUAL" value={roundToTwo(stats.avg_servqual) ?? '—'} hint="Average dimension score" icon="insights" />
        </section>

        <div data-tour="feedbacks-breakdown" className="grid gap-6 xl:grid-cols-2">
          <SectionCard title="Rating distribution" description="Overall ratings by count.">
            {!hasDistribution ? (
              <EmptyState title="No ratings yet" description="Ratings will appear once clients start submitting feedback." />
            ) : (
              <div className="space-y-3">
                {ratings.slice().reverse().map((item) => {
                  const width = maxDistributionCount ? (item.count / maxDistributionCount) * 100 : 0;
                  const percent = totalResponses ? Math.round((item.count / totalResponses) * 100) : 0;

                  return (
                    <div key={item.rating} className="grid grid-cols-[44px,1fr,86px] items-center gap-3 text-sm">
                      <div className="flex items-center gap-1.5 text-slate-700">
                        <span className="font-semibold">{item.rating}</span>
                        <span className="material-symbols-outlined text-[16px] text-amber-500">star</span>
                      </div>
                      <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div className={`h-full rounded-full ${RATING_META[item.rating]}`} style={{ width: `${width}%` }} />
                      </div>
                      <div className="text-right text-xs text-slate-500">
                        {item.count} {item.count === 1 ? 'response' : 'responses'}{totalResponses ? <span className="ml-1">· {percent}%</span> : null}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </SectionCard>

          <SectionCard title="SERVQUAL dimensions" description="Average score by dimension.">
            <div className="space-y-3">
              {DIMENSION_ORDER.map((dimension) => {
                const score = roundToTwo(dimension_averages?.[dimension]);
                const value = score ?? 0;
                const meta = DIMENSION_META[dimension];

                return (
                  <div key={dimension} className="rounded-lg border border-slate-200 p-3">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-slate-900">{dimension}</p>
                        <p className="text-xs text-slate-500">Average score out of 5</p>
                      </div>
                      <p className={`text-lg font-bold ${meta.tone}`}>{score ?? '—'}</p>
                    </div>
                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                      <div className={`h-full rounded-full ${meta.bar}`} style={{ width: `${Math.min((value / 5) * 100, 100)}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          </SectionCard>
        </div>

        <SectionCard title="Service breakdown" description="Invitations sent, responses, response rate, and average rating by service.">
          {service_breakdown.length === 0 ? (
            <EmptyState title="No service data yet" description="Service totals will appear here once feedback is recorded." />
          ) : (
            <div className="overflow-hidden rounded-lg border border-slate-200">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Service</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Invitations Sent</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Responses</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Response Rate</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Avg Rating</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {service_breakdown.map((row, index) => (
                    <tr key={row.service_id ?? `${row.service_name}-${index}`} className="hover:bg-slate-50">
                      <td className="px-4 py-4 text-sm font-semibold text-slate-900">{row.service_name}</td>
                      <td className="px-4 py-4 text-right text-sm text-slate-600">{toNumber(row.invitations_sent ?? 0)}</td>
                      <td className="px-4 py-4 text-right text-sm text-slate-600">{toNumber(row.count)}</td>
                      <td className="px-4 py-4 text-right text-sm text-slate-600">
                        {row.response_rate != null ? `${roundToTwo(row.response_rate)}%` : '—'}
                      </td>
                      <td className="px-4 py-4 text-right text-sm text-slate-600">{roundToTwo(row.avg_rating) ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </SectionCard>

        <SectionCard dataTour="feedbacks-list" title="Recent feedback" description="Latest submissions from clients.">
          {recent_feedback.length === 0 ? (
            <EmptyState title="No recent feedback" description="Recent submissions will appear here once clients respond." />
          ) : (
            <div className="overflow-hidden rounded-lg border border-slate-200">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Date</th>
                    <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Client</th>
                    <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Agency</th>
                    <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Service</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Rating</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">SERVQUAL Avg</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {recent_feedback.map((fb) => (
                    <tr key={fb.id} className="hover:bg-slate-50">
                      <td className="whitespace-nowrap px-4 py-4 text-sm text-slate-600">{formatDisplayDate(fb.created_at)}</td>
                      <td className="px-4 py-4 text-sm font-semibold text-slate-900">{fb.client_name || 'Anonymous'}</td>
                      <td className="px-4 py-4 text-sm text-slate-600">{fb.agency_name || 'N/A'}</td>
                      <td className="px-4 py-4 text-sm text-slate-600">{fb.service_name || 'N/A'}</td>
                      <td className="px-4 py-4 text-right text-sm text-slate-600">{roundToTwo(fb.overall_rating) ?? '—'}</td>
                      <td className="px-4 py-4 text-right text-sm text-slate-600">{roundToTwo(fb.servqual_avg) ?? '—'}</td>
                      <td className="px-4 py-4 text-right">
                        <Link href={route('feedbacks.show', fb.id)} className="inline-flex h-9 items-center rounded-md border border-blue-900 px-3 text-xs font-semibold text-blue-900 hover:bg-blue-50">
                          View
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </SectionCard>
      </div>
    </AppLayout>
  );
}
