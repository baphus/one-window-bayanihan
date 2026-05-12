import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

const actionStyles = {
  CREATE: 'bg-green-100 text-green-800',
  UPDATE: 'bg-blue-100 text-blue-800',
  DELETE: 'bg-red-100 text-red-800',
  VIEW: 'bg-purple-100 text-purple-800',
  LOGIN: 'bg-slate-100 text-slate-800',
  LOGOUT: 'bg-slate-100 text-slate-800',
};

export default function AuditLogIndex({ logs }) {
  return (
    <AppLayout title="Audit Logs">
      <Head title="Audit Logs" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Audit Logs</h1>
        <p className="text-sm text-slate-500 mt-1">Track all system activities and changes.</p>
      </div>

      <div className="rounded-lg bg-white shadow-sm border border-slate-200">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Timestamp</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">User</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Action</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Module</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {logs.data.length === 0 ? (
                <tr><td colSpan={4} className="px-6 py-4 text-center text-sm text-slate-500">No audit logs found.</td></tr>
              ) : (
                logs.data.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50">
                    <td className="px-6 py-4 text-sm text-slate-500 whitespace-nowrap">
                      {new Date(log.timestamp).toLocaleString()}
                    </td>
                    <td className="px-6 py-4 text-sm font-medium text-slate-900">
                      {log.user?.name ?? 'System'}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${actionStyles[log.action] || 'bg-slate-100 text-slate-800'}`}>
                        {log.action}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-500">{log.module}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {logs.last_page > 1 && (
          <div className="border-t border-slate-200 px-6 py-4 flex items-center justify-between">
            <div className="text-sm text-slate-700">
              Showing {logs.from} to {logs.to} of {logs.total}
            </div>
            <div className="flex gap-2">
              {logs.links.map((link, i) => (
                <Link
                  key={i}
                  href={link.url || '#'}
                  className={`inline-flex items-center rounded-md px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
