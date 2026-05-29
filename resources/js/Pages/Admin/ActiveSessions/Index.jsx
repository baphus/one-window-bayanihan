import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

function truncateUserAgent(userAgent) {
  if (!userAgent) return '—';

  return userAgent.length > 80 ? `${userAgent.slice(0, 80)}…` : userAgent;
}

export default function Index({ sessions = [] }) {
  const terminate = (sessionId, isCurrent) => {
    if (isCurrent) return;

    if (!window.confirm('Terminate this session?')) return;

    router.post(route('admin.system.active-sessions.terminate', sessionId), {}, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Active Sessions">
      <Head title="Active Sessions" />

      <div className="mb-8 flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Active Sessions</h1>
          <p className="mt-1 text-sm text-slate-500">Monitor signed-in users and end inactive or suspicious sessions.</p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <div className="text-xs font-semibold uppercase tracking-widest text-slate-500">Active Sessions</div>
          <div className="mt-1 text-2xl font-bold text-slate-900">{sessions.length}</div>
        </div>
      </div>

      <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP Address</th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User Agent</th>
              <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last Activity</th>
              <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 bg-white">
            {sessions.length === 0 ? (
              <tr>
                <td colSpan="5" className="px-4 py-8 text-center text-sm text-slate-500">No active sessions found.</td>
              </tr>
            ) : sessions.map((session) => (
              <tr key={session.id}>
                <td className="px-4 py-4">
                  <div className="flex items-center gap-2">
                    <div>
                      <div className="text-sm font-semibold text-slate-900">{session.user_name}</div>
                      <div className="text-xs text-slate-500">{session.user_email}</div>
                    </div>
                    {session.is_current && (
                      <span className="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Current</span>
                    )}
                  </div>
                </td>
                <td className="px-4 py-4 text-sm text-slate-600 whitespace-nowrap">{session.ip_address || '—'}</td>
                <td className="px-4 py-4 text-sm text-slate-600">
                  <div className="max-w-2xl truncate" title={session.user_agent || ''}>
                    {truncateUserAgent(session.user_agent)}
                  </div>
                </td>
                <td className="px-4 py-4 text-sm text-slate-600 whitespace-nowrap">{session.last_activity}</td>
                <td className="px-4 py-4 text-right">
                  <button
                    type="button"
                    disabled={session.is_current}
                    onClick={() => terminate(session.id, session.is_current)}
                    className="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                  >
                    Terminate
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
