import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const levelOptions = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

const levelClasses = {
  emergency: 'bg-red-100 text-red-800',
  alert: 'bg-red-100 text-red-800',
  critical: 'bg-red-100 text-red-800',
  error: 'bg-red-100 text-red-800',
  warning: 'bg-amber-100 text-amber-800',
  notice: 'bg-blue-100 text-blue-800',
  info: 'bg-emerald-100 text-emerald-800',
  debug: 'bg-slate-100 text-slate-700',
};

export default function Index({ dates = [] }) {
  const [entries, setEntries] = useState([]);
  const [filters, setFilters] = useState({ level: '', search: '', date_from: '', date_to: '', per_page: 50, page: 1 });
  const [pagination, setPagination] = useState({ total: 0, per_page: 50, current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(false);

  const queryString = useMemo(() => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) params.set(key, value);
    });
    return params.toString();
  }, [filters]);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);

    fetch(`${route('admin.system.logs.entries')}?${queryString}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller.signal,
    })
      .then((res) => res.json())
      .then((data) => {
        setEntries(data.entries || []);
        setPagination({
          total: data.total || 0,
          per_page: data.per_page || 50,
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
        });
      })
      .finally(() => setLoading(false));

    return () => controller.abort();
  }, [queryString]);

  const updateFilter = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: 1 }));
  };

  const goPage = (page) => setFilters((prev) => ({ ...prev, page }));

  const downloadHref = `${route('admin.system.logs.download')}?${queryString}`;

  return (
    <AppLayout title="System Logs">
      <Head title="System Logs" />

      <div data-tour="logs-header" className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">System Logs</h1>
          <p className="mt-1 text-sm text-slate-500">Browse and export filtered application logs.</p>
        </div>

        <a data-tour="logs-download" href={downloadHref} className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
          Download Filtered
        </a>
      </div>

      <div data-tour="logs-filters" className="mb-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        <select value={filters.date_from} onChange={(e) => updateFilter('date_from', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option value="">From date</option>
          {dates.map((date) => <option key={date} value={date}>{date}</option>)}
        </select>

        <select value={filters.date_to} onChange={(e) => updateFilter('date_to', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option value="">To date</option>
          {dates.map((date) => <option key={date} value={date}>{date}</option>)}
        </select>

        <select value={filters.level} onChange={(e) => updateFilter('level', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option value="">All levels</option>
          {levelOptions.map((level) => <option key={level} value={level}>{level}</option>)}
        </select>

        <input value={filters.search} onChange={(e) => updateFilter('search', e.target.value)} placeholder="Search messages..." className="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
      </div>

      <div data-tour="logs-table" className="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Timestamp</th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Level</th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Message</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 bg-white">
            {!loading && entries.length === 0 && (
              <tr><td colSpan="3" className="px-4 py-8 text-center text-sm text-slate-500">No logs found.</td></tr>
            )}
            {entries.map((entry, idx) => (
              <tr key={`${entry.timestamp}-${idx}`}>
                <td className="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{entry.timestamp}</td>
                <td className="px-4 py-3">
                  <span className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold uppercase ${levelClasses[entry.level] || 'bg-slate-100 text-slate-700'}`}>
                    {entry.level}
                  </span>
                </td>
                <td className="px-4 py-3 text-sm text-slate-700">
                  <div className="max-w-4xl truncate" title={entry.message}>{entry.message}</div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
        <div>
          Showing {entries.length} of {pagination.total}
        </div>
        <div className="flex items-center gap-2">
          <button disabled={pagination.current_page <= 1} onClick={() => goPage(pagination.current_page - 1)} className="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-40">Prev</button>
          <span>Page {pagination.current_page} of {pagination.last_page}</span>
          <button disabled={pagination.current_page >= pagination.last_page} onClick={() => goPage(pagination.current_page + 1)} className="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-40">Next</button>
        </div>
      </div>
    </AppLayout>
  );
}
