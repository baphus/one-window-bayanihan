import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

function parseUserAgent(ua) {
  if (!ua) return { browser: '—', os: '' };

  let browser = 'Unknown';
  if (ua.includes('Edg/')) browser = 'Edge';
  else if (ua.includes('OPR/') || ua.includes('Opera')) browser = 'Opera';
  else if (ua.includes('Chrome') && !ua.includes('Edg')) browser = 'Chrome';
  else if (ua.includes('Firefox')) browser = 'Firefox';
  else if (ua.includes('Safari') && !ua.includes('Chrome')) browser = 'Safari';

  let os = '';
  if (ua.includes('Windows NT 10')) os = 'Windows 10/11';
  else if (ua.includes('Windows NT 6.3')) os = 'Windows 8.1';
  else if (ua.includes('Windows')) os = 'Windows';
  else if (ua.includes('Mac OS X')) os = 'macOS';
  else if (ua.includes('Linux')) os = 'Linux';
  else if (ua.includes('Android')) os = 'Android';
  else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';

  return { browser, os };
}

export default function Index({ sessions = [] }) {
  const [confirmSession, setConfirmSession] = useState(null);

  const handleConfirmTerminate = () => {
    if (!confirmSession) return;
    router.post(route('admin.system.active-sessions.terminate', confirmSession), {}, {
      preserveScroll: true,
    });
    setConfirmSession(null);
  };

  const columns = useMemo(() => [
    {
      key: 'user',
      title: 'User',
      render: (row) => (
        <div className="flex items-center gap-2">
          <div>
            <div className="text-sm font-semibold text-slate-900">{row.user_name}</div>
            <div className="text-xs text-slate-500">{row.user_email}</div>
          </div>
          {row.is_current && (
            <span className="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 border border-emerald-200">
              Current
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'ip_address',
      title: 'IP Address',
      render: (row) => (
        <span className="text-sm text-slate-600 whitespace-nowrap font-mono">{row.ip_address || '—'}</span>
      ),
    },
    {
      key: 'device',
      title: 'Device & Browser',
      render: (row) => {
        const { browser, os } = parseUserAgent(row.user_agent);
        return (
          <div className="max-w-xs" title={row.user_agent || ''}>
            <span className="text-sm font-medium text-slate-700">{browser}</span>
            {os && <span className="text-xs text-slate-500 ml-1.5">{os}</span>}
          </div>
        );
      },
    },
    {
      key: 'last_activity',
      title: 'Last Activity',
      render: (row) => (
        <span className="text-sm text-slate-600 whitespace-nowrap">{row.last_activity}</span>
      ),
    },
    {
      key: 'actions',
      title: 'Actions',
      sortable: false,
      render: (row) => (
        <div className="text-right">
          <button
            type="button"
            disabled={row.is_current}
            onClick={() => setConfirmSession(row.id)}
            className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 disabled:border-slate-200"
          >
            Terminate
          </button>
        </div>
      ),
    },
  ], []);

  return (
    <AppLayout title="Active Sessions">
      <Head title="Active Sessions" />

      <div className="mb-8 flex items-center justify-between gap-4" data-tour="active-sessions-header">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Active Sessions</h1>
          <p className="mt-1 text-sm text-slate-500">Monitor signed-in users and end inactive or suspicious sessions.</p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm" data-tour="active-sessions-stats">
          <div className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Active Sessions</div>
          <div className="mt-1 text-2xl font-bold text-slate-900">{sessions.length}</div>
        </div>
      </div>

      <div data-tour="active-sessions-table">
        <UnifiedTable
          columns={columns}
          data={sessions}
          keyExtractor={(row) => row.id}
          emptyStateMessage="No active sessions found."
        />
      </div>

      <ConfirmDialog
        open={!!confirmSession}
        title="Terminate Session"
        message="Are you sure you want to terminate this session? The user will be signed out immediately."
        confirmLabel="Terminate"
        tone="danger"
        onConfirm={handleConfirmTerminate}
        onCancel={() => setConfirmSession(null)}
      />
    </AppLayout>
  );
}
