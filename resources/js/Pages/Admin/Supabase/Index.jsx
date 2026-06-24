import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { formatDisplayDateTime } from '@/lib/utils';

function StatusBadge({ status }) {
  const colors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    error: 'bg-red-100 text-red-800',
    unreachable: 'bg-yellow-100 text-yellow-800',
  };

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
        colors[status] || 'bg-gray-100 text-gray-800'
      }`}
    >
      <span
        className={`w-1.5 h-1.5 mr-1.5 rounded-full ${
          status === 'active' ? 'bg-green-500' : 'bg-red-500'
        }`}
      />
      {status}
    </span>
  );
}

function BucketBadge({ visibility }) {
  const colors = {
    public: 'bg-blue-100 text-blue-800',
    private: 'bg-purple-100 text-purple-800',
  };

  return (
    <span
      className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
        colors[visibility] || 'bg-gray-100 text-gray-800'
      }`}
    >
      {visibility}
    </span>
  );
}

export default function Index({ project, storage, backups }) {
  return (
    <AppLayout title="Supabase Dashboard">
      <Head title="Supabase Dashboard" />

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Supabase Dashboard</h1>
        <p className="mt-1 text-sm text-slate-500">
          Data auto-refreshes every 5 minutes.
        </p>
      </div>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        {/* Project Status */}
        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
            Project Status
          </h3>

          {project?.error ? (
            <p className="mt-4 text-sm text-red-600">{project.error}</p>
          ) : project ? (
            <div className="mt-4 space-y-3">
              <div>
                <span className="text-xs text-slate-400">Name</span>
                <p className="text-sm font-medium text-slate-900">
                  {project.name || 'Unavailable'}
                </p>
              </div>
              <div>
                <span className="text-xs text-slate-400">Region</span>
                <p className="text-sm font-medium text-slate-900">
                  {project.region || 'Unavailable'}
                </p>
              </div>
              <div>
                <span className="text-xs text-slate-400">Status</span>
                <div className="mt-1">
                  <StatusBadge status={project.status || 'unreachable'} />
                </div>
              </div>
            </div>
          ) : (
            <p className="mt-4 text-sm text-slate-500">Unavailable</p>
          )}
        </div>

        {/* Storage */}
        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
            Storage
          </h3>

          {storage?.error ? (
            <p className="mt-4 text-sm text-red-600">{storage.error}</p>
          ) : storage?.buckets ? (
            <div className="mt-4 space-y-3">
              {storage.buckets.map((bucket) => (
                <div
                  key={bucket.name}
                  className="flex items-center justify-between"
                >
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="text-sm text-slate-900 truncate">
                      {bucket.name}
                    </span>
                    <BucketBadge visibility={bucket.visibility} />
                  </div>
                  {bucket.file_count !== undefined && (
                    <span className="text-xs text-slate-500 whitespace-nowrap ml-2">
                      {bucket.file_count} files
                    </span>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="mt-4 text-sm text-slate-500">Unavailable</p>
          )}
        </div>

        {/* Backup Summary */}
        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
            Backup Summary
          </h3>

          {backups?.error ? (
            <p className="mt-4 text-sm text-red-600">{backups.error}</p>
          ) : backups ? (
            <div className="mt-4 space-y-3">
              <div>
                <span className="text-xs text-slate-400">Last Backup</span>
                <p className="text-sm font-medium text-slate-900">
                  {backups.last_backup
                    ? formatDisplayDateTime(backups.last_backup)
                    : 'Never'}
                </p>
              </div>
              <div>
                <span className="text-xs text-slate-400">Completed / Total</span>
                <p className="text-sm font-medium text-slate-900">
                  {backups.completed_backups ?? 0} / {backups.total_backups ?? 0}
                </p>
              </div>
              <div>
                <span className="text-xs text-slate-400">Total Size</span>
                <p className="text-sm font-medium text-slate-900">
                  {backups.total_size_mb ?? 0} MB
                </p>
              </div>
              <div className="pt-2">
                <Link
                  href="/admin/system/backups"
                  className="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                >
                  View Full Backups &rarr;
                </Link>
              </div>
            </div>
          ) : (
            <p className="mt-4 text-sm text-slate-500">Unavailable</p>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
