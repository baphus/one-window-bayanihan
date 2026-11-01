import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

function StatusBadge({ status }) {
  const styles = status === 'failed'
    ? 'border-red-200 bg-red-100 text-red-800'
    : 'border-green-200 bg-green-100 text-green-800';

  return (
    <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ${styles}`}>
      {status === 'failed' ? 'Failed' : 'Sent'}
    </span>
  );
}

export default function Index({ logs, statuses = [], filterValues = {}, retentionDays }) {
  const [search, setSearch] = useState(filterValues.search ?? '');
  const [dateFrom, setDateFrom] = useState(filterValues.date_from ?? '');
  const [dateTo, setDateTo] = useState(filterValues.date_to ?? '');

  const applyFilters = (overrides = {}) => {
    const params = {
      status: filterValues.status ?? '',
      search,
      date_from: dateFrom,
      date_to: dateTo,
      per_page: filterValues.per_page ?? '',
      ...overrides,
    };

    Object.keys(params).forEach((key) => {
      if (params[key] === '' || params[key] === null) delete params[key];
    });

    router.get(route('admin.email-logs.index'), params, { preserveState: true, preserveScroll: true });
  };

  const rows = logs?.data ?? [];

  return (
    <AppLayout title="Email Logs">
      <Head title="Email Logs" />

      <div className="space-y-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Email Logs</h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">
            Every outbound message, with the provider&apos;s own delivery status. Kept for {retentionDays} days.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex gap-1 rounded-md border border-slate-200 p-1">
            <button
              onClick={() => applyFilters({ status: '' })}
              className={`rounded px-3 py-1.5 text-xs font-semibold ${!filterValues.status ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              All
            </button>
            {statuses.map((status) => (
              <button
                key={status}
                onClick={() => applyFilters({ status })}
                className={`rounded px-3 py-1.5 text-xs font-semibold ${filterValues.status === status ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
              >
                {status === 'failed' ? 'Failed' : 'Sent'}
              </button>
            ))}
          </div>

          <label className="flex-1 min-w-[200px]">
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Recipient or subject</span>
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') applyFilters(); }}
              placeholder="juan@example.com"
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
            />
          </label>

          <label>
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">From</span>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
            />
          </label>

          <label>
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">To</span>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
            />
          </label>

          <button
            onClick={() => applyFilters()}
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
                  {['Recipient', 'Subject', 'Status', 'Sent', 'Delivered', ''].map((heading) => (
                    <th key={heading} scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                      {heading}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-6 py-10 text-center text-sm text-slate-400">
                      No emails match these filters.
                    </td>
                  </tr>
                )}
                {rows.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50">
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-900">{log.to_email}</td>
                    <td className="px-6 py-4 text-sm text-slate-600">{log.subject}</td>
                    <td className="whitespace-nowrap px-6 py-4"><StatusBadge status={log.status} /></td>
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{formatDateTime(log.sent_at)}</td>
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{formatDateTime(log.delivered_at)}</td>
                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                      <Link href={route('admin.email-logs.show', log.id)} className="font-semibold text-slate-700 hover:text-slate-900">
                        Details
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {logs?.links?.length > 3 && (
          <nav className="flex flex-wrap gap-1" aria-label="Pagination">
            {logs.links.map((link, index) => (
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
    </AppLayout>
  );
}
