import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function formatDateTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleString();
}

export default function Index({ logs, moduleLabels = {}, actions = [], filterValues = {} }) {
  const [dateFrom, setDateFrom] = useState(filterValues.date_from ?? '');
  const [dateTo, setDateTo] = useState(filterValues.date_to ?? '');

  const applyFilters = (overrides = {}) => {
    const params = {
      module: filterValues.module ?? '',
      action: filterValues.action ?? '',
      date_from: dateFrom,
      date_to: dateTo,
      per_page: filterValues.per_page ?? '',
      ...overrides,
    };

    Object.keys(params).forEach((key) => {
      if (params[key] === '' || params[key] === null) delete params[key];
    });

    router.get(route('admin.security-log.index'), params, { preserveState: true, preserveScroll: true });
  };

  const rows = logs?.data ?? [];

  return (
    <AppLayout title="Security Log">
      <Head title="Security Log" />

      <div className="space-y-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Security Log</h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">
            Sign-ins, credential changes and MFA events. Read-only: audit rows are hash-chained and never edited.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <label>
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Area</span>
            <select
              value={filterValues.module ?? ''}
              onChange={(e) => applyFilters({ module: e.target.value })}
              className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
            >
              <option value="">All</option>
              {Object.entries(moduleLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          <label>
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Event</span>
            <select
              value={filterValues.action ?? ''}
              onChange={(e) => applyFilters({ action: e.target.value })}
              className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none"
            >
              <option value="">All</option>
              {actions.map((action) => (
                <option key={action} value={action}>{action.replaceAll('_', ' ').toLowerCase()}</option>
              ))}
            </select>
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

          <Link
            href={route('audit-logs.index')}
            className="ml-auto text-sm font-semibold text-slate-600 hover:text-slate-900"
          >
            Open full audit log
          </Link>
        </div>

        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  {['When', 'Who', 'Event', 'What', 'IP'].map((heading) => (
                    <th key={heading} scope="col" className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                      {heading}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-6 py-10 text-center text-sm text-slate-400">
                      No security events match these filters.
                    </td>
                  </tr>
                )}
                {rows.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50">
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{formatDateTime(log.timestamp)}</td>
                    <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{log.actor ?? 'System'}</td>
                    <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                      {(log.action ?? '').replaceAll('_', ' ').toLowerCase()}
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-600">{log.message}</td>
                    <td className="whitespace-nowrap px-6 py-4 font-mono text-xs text-slate-400">{log.ip_address ?? '—'}</td>
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
