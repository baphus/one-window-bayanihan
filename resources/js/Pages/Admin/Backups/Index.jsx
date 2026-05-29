import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

function Badge({ status }) {
  const styles = {
    completed: 'bg-green-100 text-green-800',
    running: 'bg-blue-100 text-blue-800',
    failed: 'bg-red-100 text-red-800',
    unknown: 'bg-slate-100 text-slate-700',
  };

  return (
    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles[status] || styles.unknown}`}>
      {status}
    </span>
  );
}

export default function Index({ status }) {
  const backups = status?.backups ?? [];

  const refresh = () => {
    router.post(route('admin.system.backups.refresh'), {}, { preserveScroll: true });
  };

  return (
    <AppLayout title="Backup Status">
      <Head title="Backup Status" />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Backup Status</h1>
          <p className="mt-1 text-sm text-slate-500">Read-only Supabase database backup overview.</p>
        </div>
        <button onClick={refresh} className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">
          Refresh
        </button>
      </div>

      {status?.error && (
        <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {status.error}
        </div>
      )}

      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <div className="rounded-lg bg-white p-6 shadow-sm border border-slate-200">
          <div className="text-xs font-semibold uppercase tracking-widest text-slate-400">Last backup</div>
          <div className="mt-2 text-lg font-bold text-slate-900">{status?.last_backup || 'Unavailable'}</div>
        </div>
        <div className="rounded-lg bg-white p-6 shadow-sm border border-slate-200">
          <div className="text-xs font-semibold uppercase tracking-widest text-slate-400">Total backups</div>
          <div className="mt-2 text-3xl font-bold text-slate-900">{status?.backup_count ?? 0}</div>
        </div>
        <div className="rounded-lg bg-white p-6 shadow-sm border border-slate-200">
          <div className="text-xs font-semibold uppercase tracking-widest text-slate-400">Total size</div>
          <div className="mt-2 text-3xl font-bold text-slate-900">{status?.total_size_mb ?? 0} MB</div>
        </div>
      </div>

      <div className="rounded-lg bg-white shadow-sm border border-slate-200 overflow-hidden">
        <div className="border-b border-slate-200 px-6 py-4">
          <h2 className="text-base font-semibold text-slate-900">Backup History</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
              <tr>
                <th className="px-6 py-3">Date</th>
                <th className="px-6 py-3">Type</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3">Size</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 bg-white">
              {backups.length > 0 ? backups.map((backup) => (
                <tr key={backup.id || backup.created_at}>
                  <td className="px-6 py-4 text-slate-700">{backup.created_at || '-'}</td>
                  <td className="px-6 py-4 text-slate-700">{backup.type || '-'}</td>
                  <td className="px-6 py-4"><Badge status={backup.status} /></td>
                  <td className="px-6 py-4 text-slate-700">{backup.size_mb ?? 0} MB</td>
                </tr>
              )) : (
                <tr>
                  <td colSpan="4" className="px-6 py-10 text-center text-slate-500">No backup records available.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
