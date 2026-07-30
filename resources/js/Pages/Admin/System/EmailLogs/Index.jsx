import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import { useState } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

// "sent" is deliberately not green: it means the provider accepted the message,
// not that it arrived. Only "delivered" is confirmed by the provider.
const STATUS_BADGE = {
  sent: 'bg-sky-100 text-sky-800 border-sky-200',
  delivered: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  delayed: 'bg-amber-100 text-amber-800 border-amber-200',
  suppressed: 'bg-orange-100 text-orange-800 border-orange-200',
  failed: 'bg-red-100 text-red-800 border-red-200',
  bounced: 'bg-red-100 text-red-800 border-red-200',
  complained: 'bg-purple-100 text-purple-800 border-purple-200',
};

const STATUS_HINT = {
  sent: 'Accepted by the email provider. Delivery not yet confirmed.',
  delivered: 'Confirmed delivered to the recipient’s mail server.',
  delayed: 'Temporary delivery problem. The provider is still retrying.',
  suppressed: 'Blocked by the provider’s suppression list.',
  failed: 'Could not be sent.',
  bounced: 'Permanently rejected by the recipient’s mail server.',
  complained: 'Delivered, then marked as spam by the recipient.',
};

export default function Index({ logs, filters, statuses = [] }) {
  const [confirmResendId, setConfirmResendId] = useState(null);
  const { data, current_page, last_page, total } = logs;
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  // Driven by the server's status list so the tabs cannot drift from what the
  // controller actually accepts as a filter.
  const statusTabs = [{ label: 'All', value: '' }, ...statuses.map((value) => ({
    label: value.charAt(0).toUpperCase() + value.slice(1),
    value,
  }))];

  const switchTab = (status) => {
    router.get(route('admin.system.email-logs.index'), { status }, withLoading({
      preserveState: true,
      preserveScroll: true,
    }));
  };

  const handleResend = (id) => {
    setConfirmResendId(id);
  };

  const goPage = (page) => {
    router.get(route('admin.system.email-logs.index'), { status: filters.status, page }, withLoading({
      preserveState: true,
      preserveScroll: true,
    }));
  };

  const emptyMessage = () => {
    if (filters.status) return `No ${filters.status} emails logged yet.`;
    return 'No email logs found.';
  };

  return (
    <AppLayout title="Email Logs">
      <Head title="Email Logs" />

      <div data-tour="email-logs-header" className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Email Logs</h1>
        <p className="mt-1 text-sm text-slate-500">
          Monitor all outbound emails and resend failed ones. <span className="font-medium text-slate-600">Sent</span> means
          the provider accepted the message; only <span className="font-medium text-slate-600">Delivered</span> confirms it
          reached the recipient’s mail server.
        </p>
      </div>

      <div data-tour="email-logs-tabs" className="mb-6 flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm w-fit">
        {statusTabs.map((tab) => {
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

      <div className="relative pb-24" aria-busy={tableLoading}>
        <div data-tour="email-logs-table" className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
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
                <td className="px-5 py-4 text-sm text-slate-700 max-w-xs">
                  <span className="block truncate" title={log.subject}>{log.subject}</span>
                  {log.provider_message_id && (
                    <span
                      className="mt-0.5 block truncate font-mono text-[11px] text-slate-400"
                      title={`Provider message ID: ${log.provider_message_id}`}
                    >
                      {log.provider_message_id}
                    </span>
                  )}
                </td>
                <td className="px-5 py-4">
                  <span
                    className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_BADGE[log.status] || 'bg-slate-100 text-slate-600 border-slate-200'}`}
                    title={STATUS_HINT[log.status] ?? undefined}
                  >
                    {log.status}
                  </span>
                </td>
                <td className="px-5 py-4 text-sm text-slate-600 whitespace-nowrap">
                  {log.sent_at ?? '—'}
                  {log.delivered_at && (
                    <span className="mt-0.5 block text-xs text-emerald-700">
                      Delivered {log.delivered_at}
                    </span>
                  )}
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
        {tableLoading && <TableLoadingOverlay variant="table" />}
      </div>
      <ConfirmDialog
        open={!!confirmResendId}
        title="Resend Email"
        message="Are you sure you want to resend this email?"
        confirmLabel="Resend"
        onConfirm={() => {
          router.post(route('admin.system.email-logs.resend', confirmResendId), {}, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['logs'] }),
          });
          setConfirmResendId(null);
        }}
        onCancel={() => setConfirmResendId(null)}
      />
    </AppLayout>
  );
}
