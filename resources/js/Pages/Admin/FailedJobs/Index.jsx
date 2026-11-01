import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

export default function Index({ jobs, filterValues = {} }) {
  const [search, setSearch] = useState(filterValues.search ?? '');
  const [confirmUuid, setConfirmUuid] = useState(null);

  const applySearch = () => {
    const params = { search, per_page: filterValues.per_page ?? '' };
    Object.keys(params).forEach((key) => {
      if (params[key] === '') delete params[key];
    });
    router.get(route('admin.failed-jobs.index'), params, { preserveState: true, preserveScroll: true });
  };

  const retry = (uuid) => {
    router.post(route('admin.failed-jobs.retry', uuid), {}, { preserveScroll: true });
  };

  const rows = jobs?.data ?? [];

  return (
    <AppLayout title="Failed Jobs">
      <Head title="Failed Jobs" />

      <div className="space-y-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Failed Jobs</h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">
            Queued work that threw. Payloads are kept for one week.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <label className="flex-1 min-w-[220px]">
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Queue or exception</span>
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') applySearch(); }}
              placeholder="mail, timeout, connection refused…"
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
            />
          </label>
          <button
            onClick={applySearch}
            className="rounded-md bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-slate-800"
          >
            Filter
          </button>
        </div>

        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  {['Queue', 'Failed at', 'Exception', ''].map((heading) => (
                    <th key={heading} scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                      {heading}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-6 py-10 text-center text-sm text-slate-400">
                      No failed jobs. That is the good outcome.
                    </td>
                  </tr>
                )}
                {rows.map((job) => (
                  <tr key={job.id} className="hover:bg-slate-50">
                    <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{job.queue}</td>
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{formatDateTime(job.failed_at)}</td>
                    <td className="px-6 py-4 text-sm text-slate-600">{job.exception_summary}</td>
                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                      <div className="flex justify-end gap-4">
                        <Link href={route('admin.failed-jobs.show', job.uuid)} className="font-semibold text-slate-700 hover:text-slate-900">
                          Details
                        </Link>
                        <button onClick={() => setConfirmUuid(job.uuid)} className="font-semibold text-slate-700 hover:text-slate-900">
                          Retry
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {jobs?.links?.length > 3 && (
          <nav className="flex flex-wrap gap-1" aria-label="Pagination">
            {jobs.links.map((link, index) => (
              <Link
                key={index}
                href={link.url ?? '#'}
                preserveScroll
                className={`rounded-md border px-3 py-1.5 text-xs font-semibold ${
                  link.active
                    ? 'border-slate-900 bg-slate-900 text-white'
                    : link.url
                      ? 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                      : 'border-slate-100 bg-slate-50 text-slate-300'
                }`}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </nav>
        )}
      </div>

      <ConfirmDialog
        open={confirmUuid !== null}
        title="Retry failed job"
        message="This re-queues the job with its original payload. If it fails for the same reason, it will appear here again."
        confirmLabel="Retry"
        cancelLabel="Cancel"
        onConfirm={() => { retry(confirmUuid); setConfirmUuid(null); }}
        onCancel={() => setConfirmUuid(null)}
      />
    </AppLayout>
  );
}
