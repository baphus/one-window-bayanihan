import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

const STATUS_TABS = [
  { label: 'All', value: '' },
  { label: 'Sent', value: 'sent' },
  { label: 'Failed', value: 'failed' },
];

const STATUS_BADGE = {
  sent: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  failed: 'bg-red-100 text-red-800 border-red-200',
};

export default function Index({ logs, filters }) {
  const { data, current_page, last_page, total } = logs;

  const switchTab = (status) => {
    router.get(route('admin.system.email-logs.index'), { status }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleResend = (id) => {
    if (!confirm('Resend this email?')) return;

    router.post(route('admin.system.email-logs.resend', id), {}, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ['logs'] }),
    });
  };

  const goPage = (page) => {
    router.get(route('admin.system.email-logs.index'), { status: filters.status, page }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const emptyMessage = () => {
    if (filters.status === 'sent') return 'No sent emails logged yet.';
    if (filters.status === 'failed') return 'No failed emails logged yet.';
    return 'No email logs found.';
  };

  return (
    <AppLayout title="Email Logs">
      <Head title="Email Logs" />

      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Email Logs</h1>
        <p className="mt-1 text-sm text-slate-500">Monitor all outbound emails and resend failed ones.</p>
      </div>

      <div className="mb-6 flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm w-fit">
        {STATUS_TABS.map((tab) => {
          const active = (tab.value === '' && !filters.status) || filters.status === tab.value;
          return (
            <button
              key={tab.value}
              onClick={() => switchTab(tab.value)}
              className={`rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-wider transition-colors ${
                active
                  ? 'bg-blue-900 text-white shadow-sm'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
              }`}
            >
              {tab.label}
            </button>
          );
        })}
      </div>

      <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">To</th>
              <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Subject</th>
              <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
              <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sent At</th>
              <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Error</th>
              <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {data.length === 0 && (
              <tr>
                <td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">
                  {emptyMessage()}
                </td>
              </tr>
            )}
            {data.map((log) => (
              <tr key={log.id} className="hover:bg-slate-50 transition-colors">
                <td className="px-5 py-4 text-sm text-slate-900 font-medium">{log.to_email}</td>
                <td className="px-5 py-4 text-sm text-slate-700 max-w-xs truncate" title={log.subject}>
                  {log.subject}
                </td>
                <td className="px-5 py-4">
                  <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_BADGE[log.status] || 'bg-slate-100 text-slate-600 border-slate-200'}`}>
                    {log.status}
                  </span>
                </td>
                <td className="px-5 py-4 text-sm text-slate-600 whitespace-nowrap">
                  {log.sent_at ?? '—'}
                </td>
                <td className="px-5 py-4 text-sm text-slate-600 max-w-xs">
                  {log.error_message ? (
                    <span className="truncate block" title={log.error_message}>
                      {log.error_message}
                    </span>
                  ) : (
                    <span className="text-slate-400">—</span>
                  )}
                </td>
                <td className="px-5 py-4">
                  {log.status === 'failed' ? (
                    <button
                      onClick={() => handleResend(log.id)}
                      className="rounded-md bg-blue-900 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-white hover:bg-blue-800 transition-colors"
                    >
                      Resend
                    </button>
                  ) : (
                    <span className="text-xs text-slate-400">—</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm text-slate-600">
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
    </AppLayout>
  );
}
