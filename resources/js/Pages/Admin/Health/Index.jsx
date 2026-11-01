import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

const STATUS_STYLES = {
  ok: {
    badge: 'border-green-200 bg-green-100 text-green-800',
    dot: 'bg-green-500',
    label: 'Operational',
  },
  warn: {
    badge: 'border-amber-200 bg-amber-100 text-amber-800',
    dot: 'bg-amber-500',
    label: 'Degraded',
  },
  fail: {
    badge: 'border-red-200 bg-red-100 text-red-800',
    dot: 'bg-red-500',
    label: 'Failing',
  },
  unknown: {
    badge: 'border-slate-200 bg-slate-100 text-slate-600',
    dot: 'bg-slate-400',
    label: 'Unknown',
  },
};

function formatCheckedAt(value) {
  if (!value) return 'unknown';
  return new Date(value).toLocaleString();
}

export default function Index({ checks = [], healthy, checkedAt }) {
  const overall = STATUS_STYLES[healthy ? 'ok' : 'fail'];

  return (
    <AppLayout title="System Health">
      <Head title="System Health" />

      <div className="max-w-4xl space-y-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
              System Health
            </h1>
            <p className="text-sm text-slate-400 font-body mt-0.5">
              The same checks the readiness probe runs, with the detail it withholds.
            </p>
          </div>
          <button
            onClick={() => router.reload({ preserveScroll: true })}
            className="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50"
          >
            Re-check
          </button>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-slate-500">Overall</p>
              <span className={`mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-semibold ${overall.badge}`}>
                {healthy ? 'All checks passing' : 'One or more checks failing'}
              </span>
            </div>
            <p className="text-right text-xs text-slate-400">
              Checked {formatCheckedAt(checkedAt)}
            </p>
          </div>
        </div>

        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Check
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Status
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Detail
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {checks.map((check) => {
                const style = STATUS_STYLES[check.status] ?? STATUS_STYLES.unknown;

                return (
                  <tr key={check.key}>
                    <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                      {check.label}
                    </td>
                    <td className="whitespace-nowrap px-6 py-4">
                      <span className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold ${style.badge}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${style.dot}`} aria-hidden="true" />
                        {style.label}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-600">
                      <div>{check.summary}</div>
                      {/* The detail line is advisory: it says what to change,
                          never a credential or a connection string. */}
                      {check.detail && (
                        <div className="mt-1 text-xs text-slate-400">{check.detail}</div>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <p className="text-xs text-slate-400">
          Error-level detail lives in Sentry. Scheduler heartbeat, queue depth and mail
          transport are also exposed to external monitoring at <code>/api/readyz</code>.
        </p>
      </div>
    </AppLayout>
  );
}
