import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { formatDisplayDate } from '@/lib/utils';

export default function ResponseIndex({ invitations, stats, filters = {} }) {
  const { data, current_page, last_page, total } = invitations;

  const goPage = (page) => {
    router.get(route('survey.responses.index'), { ...filters, page }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Survey Responses">
      <Head title="Survey Responses" />

      <div className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div data-tour="survey-responses-header" className="flex flex-col gap-3 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Surveys</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">Survey Responses</h1>
              <p className="mt-1 text-sm text-slate-500">View feedback submitted by clients after service completion.</p>
            </div>
          </div>
        </section>

        {/* Stats Cards */}
        <section data-tour="survey-responses-stats" className="grid gap-4 md:grid-cols-3">
          <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total Sent</p>
            <p className="mt-2 text-3xl font-bold text-slate-900">{stats.total_sent}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total Submitted</p>
            <p className="mt-2 text-3xl font-bold text-slate-900">{stats.total_submitted}</p>
          </div>
          <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Response Rate</p>
            <p className="mt-2 text-3xl font-bold text-slate-900">{stats.response_rate}%</p>
          </div>
        </section>

        {/* Responses Table */}
        <section data-tour="survey-responses-list" className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-5 py-4">
            <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Submitted responses</h2>
          </div>

          <div className="overflow-hidden">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Client</th>
                  <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Service</th>
                  <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Agency</th>
                  <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Survey Form</th>
                  <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Submitted</th>
                  <th className="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {data.length === 0 && (
                  <tr>
                    <td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">
                      No survey responses yet.
                    </td>
                  </tr>
                )}
                {data.map((invitation) => (
                  <tr key={invitation.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-5 py-4 text-sm font-medium text-slate-900">{invitation.client_name}</td>
                    <td className="px-5 py-4 text-sm text-slate-600">{invitation.service_name}</td>
                    <td className="px-5 py-4 text-sm text-slate-600">{invitation.agency?.name ?? '—'}</td>
                    <td className="px-5 py-4 text-sm text-slate-600">{invitation.survey_form?.title ?? '—'}</td>
                    <td className="px-5 py-4 text-sm text-slate-600">{formatDisplayDate(invitation.submitted_at)}</td>
                    <td className="px-5 py-4 text-right">
                      <Link
                        href={route('survey.responses.show', invitation.id)}
                        className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {/* Pagination */}
        {last_page > 1 && (
          <div className="flex items-center justify-between text-sm text-slate-600">
            <div>
              Showing page {current_page} of {last_page} ({total} total)
            </div>
            <div className="flex items-center gap-2">
              <button
                disabled={current_page <= 1}
                onClick={() => goPage(current_page - 1)}
                className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-slate-50 transition-colors"
              >
                Previous
              </button>
              <button
                disabled={current_page >= last_page}
                onClick={() => goPage(current_page + 1)}
                className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-slate-50 transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
