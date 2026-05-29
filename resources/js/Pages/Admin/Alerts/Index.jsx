import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const labels = {
  low_storage: 'Low Storage',
  backup_failure: 'Backup Failure',
  health_check_failure: 'Health Check Failure',
};

function ConfigCard({ config, onSave }) {
  const [enabled, setEnabled] = useState(!!config.enabled);
  const [thresholdValue, setThresholdValue] = useState(config.threshold_value ?? '');
  const [emailRecipients, setEmailRecipients] = useState((config.email_recipients || []).join(', '));
  const [notifyInApp, setNotifyInApp] = useState(!!config.notify_in_app);

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="text-lg font-semibold text-slate-900">{labels[config.alert_type] || config.alert_type}</h3>
          <p className="text-sm text-slate-500">{config.alert_type}</p>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={enabled}
          onClick={() => setEnabled((v) => !v)}
          className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors ${enabled ? 'bg-indigo-600' : 'bg-slate-300'}`}
        >
          <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${enabled ? 'translate-x-5' : 'translate-x-0'}`} />
        </button>
      </div>

      <div className="mt-4 space-y-4">
        {config.alert_type === 'low_storage' && (
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">Threshold Value (%)</label>
            <input
              type="number"
              min="1"
              max="100"
              value={thresholdValue}
              onChange={(e) => setThresholdValue(e.target.value)}
              className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
        )}

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Email Recipients</label>
          <input
            type="text"
            value={emailRecipients}
            onChange={(e) => setEmailRecipients(e.target.value)}
            placeholder="admin@example.com, ops@example.com"
            className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          />
        </div>

        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" checked={notifyInApp} onChange={(e) => setNotifyInApp(e.target.checked)} className="rounded border-slate-300" />
          In-app notification
        </label>

        <button
          type="button"
          onClick={() => onSave({
            id: config.id,
            enabled,
            threshold_value: config.alert_type === 'low_storage' && thresholdValue !== '' ? Number(thresholdValue) : null,
            email_recipients: emailRecipients.split(',').map((item) => item.trim()).filter(Boolean),
            notify_in_app: notifyInApp,
          })}
          className="rounded-md bg-[#0b5384] px-4 py-2 text-sm font-medium text-white hover:bg-[#09416a]"
        >
          Save
        </button>
      </div>
    </div>
  );
}

export default function AlertsIndex({ configs = [], alertLogs = [] }) {
  const [recipient, setRecipient] = useState('');

  const rows = useMemo(() => alertLogs || [], [alertLogs]);

  const saveConfig = (payload) => {
    router.post(route('admin.alerts.update'), payload, { preserveScroll: true });
  };

  const sendTestEmail = () => {
    router.post(route('admin.alerts.test-email'), { recipient }, { preserveScroll: true, onSuccess: () => setRecipient('') });
  };

  return (
    <AppLayout title="Alert & Notification Config">
      <Head title="Alert & Notification Config" />

      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Alert & Notification Config</h1>
        <p className="mt-1 text-sm text-slate-500">Manage alert thresholds, recipients, and recent system alerts.</p>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section className="space-y-4">
          <h2 className="text-lg font-semibold text-slate-900">Alert Configs</h2>
          <div className="space-y-4">
            {configs.map((config) => (
              <ConfigCard key={config.id} config={config} onSave={saveConfig} />
            ))}
          </div>

          <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h3 className="text-base font-semibold text-slate-900">Test Email</h3>
            <div className="mt-4 flex gap-3">
              <input
                type="email"
                value={recipient}
                onChange={(e) => setRecipient(e.target.value)}
                placeholder="recipient@example.com"
                className="block flex-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <button
                type="button"
                onClick={sendTestEmail}
                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
              >
                Send Test
              </button>
            </div>
          </div>
        </section>

        <section>
          <h2 className="text-lg font-semibold text-slate-900">Alert Logs</h2>
          <div className="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-left text-slate-600">
                <tr>
                  <th className="px-4 py-3">Timestamp</th>
                  <th className="px-4 py-3">Severity</th>
                  <th className="px-4 py-3">Type</th>
                  <th className="px-4 py-3">Message</th>
                  <th className="px-4 py-3">Sent</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 bg-white">
                {rows.length ? rows.map((log) => (
                  <tr key={log.id}>
                    <td className="px-4 py-3 text-slate-600">{log.created_at}</td>
                    <td className="px-4 py-3">{log.severity}</td>
                    <td className="px-4 py-3">{log.alert_type}</td>
                    <td className="px-4 py-3 text-slate-600">{log.message}</td>
                    <td className="px-4 py-3">
                      <span className={`rounded-full px-2 py-1 text-xs font-medium ${log.sent_email ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                        {log.sent_email ? 'Yes' : 'No'}
                      </span>
                    </td>
                  </tr>
                )) : (
                  <tr>
                    <td className="px-4 py-6 text-center text-slate-500" colSpan="5">No alert logs found.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </AppLayout>
  );
}
