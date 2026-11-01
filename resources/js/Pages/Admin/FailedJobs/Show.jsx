import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

export default function Show({ job }) {
  return (
    <AppLayout title="Failed Job">
      <Head title="Failed Job" />

      <div className="max-w-4xl space-y-6">
        <div className="flex items-center justify-between gap-4">
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Failed Job</h1>
          <Link href={route('admin.failed-jobs.index')} className="text-sm font-semibold text-slate-600 hover:text-slate-900">
            Back to failed jobs
          </Link>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <dl className="grid gap-5 sm:grid-cols-2">
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">Queue</dt>
              <dd className="mt-1 text-sm text-slate-900">{job.queue}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">Connection</dt>
              <dd className="mt-1 text-sm text-slate-900">{job.connection}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">Failed at</dt>
              <dd className="mt-1 text-sm text-slate-900">{formatDateTime(job.failed_at)}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">UUID</dt>
              <dd className="mt-1 font-mono text-xs text-slate-500 break-all">{job.uuid}</dd>
            </div>
          </dl>
        </div>

        <div className="rounded-lg border border-red-200 bg-red-50 p-6">
          <h2 className="text-sm font-semibold text-red-900">Exception</h2>
          <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-xs text-red-800">{job.exception}</pre>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-sm font-semibold text-slate-900">Payload</h2>
          <p className="mt-1 text-xs text-slate-400">
            What the job was carrying. This is the serialized job data, not the request that queued it.
          </p>
          <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words rounded-md bg-slate-50 p-4 text-xs text-slate-700">
            {JSON.stringify(job.payload, null, 2)}
          </pre>
        </div>
      </div>
    </AppLayout>
  );
}
