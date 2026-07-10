import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { formatDisplayDate } from '@/lib/utils';

const WINDOW_OPTIONS = [
  { value: 'all', label: 'All time' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: '90d', label: 'Last 90 days' },
  { value: 'quarter', label: 'This quarter' },
  { value: 'year', label: 'This year' },
];

const MIN_RATING_OPTIONS = ['', '1', '2', '3', '4', '5'];

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

function cleanQuery(values) {
  return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined));
}

function Field({ label, children }) {
  return (
    <div>
      <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{label}</label>
      {children}
    </div>
  );
}

export default function AdminDashboard({ agencySummary = [], feedbacks, filters = {}, allAgencies = [], allServices = [] }) {
  const feedbackRows = feedbacks?.data ?? [];

  const defaultFilters = useMemo(
    () => ({
      agency_id: filters.agency_id ?? '',
      service_id: filters.service_id ?? '',
      date_from: filters.date_from ?? '',
      date_to: filters.date_to ?? '',
      min_rating: filters.min_rating ?? '',
      window: filters.window ?? 'all',
    }),
    [filters],
  );

  const [form, setForm] = useState(defaultFilters);

  useEffect(() => {
    setForm(defaultFilters);
  }, [defaultFilters]);

  const updateField = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const applyFilters = () => {
    router.get(route('admin.feedbacks.dashboard'), cleanQuery(form), { preserveState: true, preserveScroll: true });
  };

  const clearFilters = () => {
    const cleared = { agency_id: '', service_id: '', date_from: '', date_to: '', min_rating: '', window: 'all' };
    setForm(cleared);
    router.get(route('admin.feedbacks.dashboard'), cleanQuery(cleared), { preserveState: true, preserveScroll: true });
  };

  const hasPagination = feedbacks?.last_page > 1 && Array.isArray(feedbacks?.links);

  return (
    <AppLayout title="Feedback Overview">
      <Head title="Feedback Overview" />

      <div className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="px-5 py-5">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Feedback</p>
            <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">Feedback Overview</h1>
            <p className="mt-1 text-sm text-slate-500">Review agency performance and filter feedback records across the system.</p>
          </div>
        </section>

        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Filters</h2>
            <p className="mt-1 text-sm text-slate-500">Narrow the list by agency, service, date, rating, or time window.</p>
          </div>
          <div className="px-5 py-5">
            <div className="grid gap-4 lg:grid-cols-3 xl:grid-cols-6">
              <Field label="Agency">
                <select value={form.agency_id} onChange={(e) => updateField('agency_id', e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900">
                  <option value="">All agencies</option>
                  {allAgencies.map((agency) => (
                    <option key={agency.id} value={agency.id}>{agency.name}</option>
                  ))}
                </select>
              </Field>

              <Field label="Service">
                <select value={form.service_id} onChange={(e) => updateField('service_id', e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900">
                  <option value="">All services</option>
                  {allServices.map((service) => (
                    <option key={service.id} value={service.id}>{service.name}</option>
                  ))}
                </select>
              </Field>

              <Field label="Date from">
                <input type="date" value={form.date_from} onChange={(e) => updateField('date_from', e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900" />
              </Field>

              <Field label="Date to">
                <input type="date" value={form.date_to} onChange={(e) => updateField('date_to', e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900" />
              </Field>

              <Field label="Minimum rating">
                <select value={form.min_rating} onChange={(e) => updateField('min_rating', e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900">
                  <option value="">Any rating</option>
                  {MIN_RATING_OPTIONS.filter(Boolean).map((rating) => (
                    <option key={rating} value={rating}>{rating} stars & up</option>
                  ))}
                </select>
              </Field>

              <Field label="Window">
                <select value={form.window} onChange={(e) => updateField('window', e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900">
                  {WINDOW_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </select>
              </Field>
            </div>

            <div className="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
              <button type="button" onClick={clearFilters} className="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Clear
              </button>
              <button type="button" onClick={applyFilters} className="inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800">
                Apply filters
              </button>
            </div>
          </div>
        </section>

        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Agency summary</h2>
            <p className="mt-1 text-sm text-slate-500">Response totals and averages by agency.</p>
          </div>
          <div className="px-5 py-5">
            <div className="overflow-hidden rounded-lg border border-slate-200">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Agency</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Sent</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Submitted</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Response Rate</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Avg Rating</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {agencySummary.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-4 py-8 text-center text-sm text-slate-500">No agency feedback data yet.</td>
                    </tr>
                  ) : (
                    agencySummary.map((agency) => (
                      <tr key={agency.id} className="hover:bg-slate-50">
                        <td className="px-4 py-4 text-sm font-semibold text-slate-900">{agency.name}</td>
                        <td className="px-4 py-4 text-right text-sm text-slate-600">{toNumber(agency.total_sent)}</td>
                        <td className="px-4 py-4 text-right text-sm text-slate-600">{toNumber(agency.total_submitted)}</td>
                        <td className="px-4 py-4 text-right text-sm text-slate-600">{roundToTwo(agency.response_rate) ?? '—'}%</td>
                        <td className="px-4 py-4 text-right text-sm text-slate-600">{roundToTwo(agency.avg_rating) ?? '—'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Feedback records</h2>
            <p className="mt-1 text-sm text-slate-500">Detailed responses matching the selected filters.</p>
          </div>
          <div className="px-5 py-5">
            {feedbackRows.length === 0 ? (
              <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">No feedback records match the current filters.</div>
            ) : (
              <>
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
                      {feedbackRows.map((fb) => (
                        <tr key={fb.id} className="hover:bg-slate-50">
                          <td className="whitespace-nowrap px-4 py-4 text-sm text-slate-600">{formatDisplayDate(fb.created_at)}</td>
                          <td className="px-4 py-4 text-sm font-semibold text-slate-900">{fb.client_name || 'Anonymous'}</td>
                          <td className="px-4 py-4 text-sm text-slate-600">{fb.agency_name || 'N/A'}</td>
                          <td className="px-4 py-4 text-sm text-slate-600">{fb.service_name || 'N/A'}</td>
                          <td className="px-4 py-4 text-right text-sm text-slate-600">
                            <div className="inline-flex items-center gap-2">
                              <StarRating rating={fb.overall_rating} />
                              <span>{roundToTwo(fb.overall_rating) ?? '—'}</span>
                            </div>
                          </td>
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

                {hasPagination ? (
                  <div className="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-slate-600">Showing {feedbacks.from ?? 0} to {feedbacks.to ?? 0} of {feedbacks.total ?? 0}</p>
                    <div className="flex flex-wrap gap-2">
                      {feedbacks.links.map((link, index) => (
                        <Link
                          key={`${link.label}-${index}`}
                          href={link.url || '#'}
                          className={`inline-flex items-center rounded-md border px-3 py-2 text-sm ${link.active ? 'border-blue-900 bg-blue-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ))}
                    </div>
                  </div>
                ) : null}
              </>
            )}
          </div>
        </section>
      </div>
    </AppLayout>
  );
}
