import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { formatDisplayDateTime } from '@/lib/utils';

function ProgressCard({ title, stat, value, percent, helper }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-500">{title}</h3>
          <p className="mt-2 text-3xl font-bold text-slate-900">{stat}</p>
          <p className="mt-1 text-sm text-slate-500">{helper}</p>
        </div>
        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{percent}%</span>
      </div>
      <div className="mt-5 h-2 overflow-hidden rounded-full bg-slate-100">
        <div className="h-full rounded-full bg-[#0b5384]" style={{ width: `${Math.min(Math.max(percent || 0, 0), 100)}%` }} />
      </div>
      <div className="mt-3 flex items-center justify-between text-sm text-slate-500">
        <span>{value}</span>
        <span>{stat}</span>
      </div>
    </div>
  );
}

export default function Index({ usage, recentMedia }) {
  const hasError = usage?.error || recentMedia?.error;

  const refresh = () => {
    router.post(route('admin.system.cloudinary.refresh'), {}, { preserveScroll: true });
  };

  return (
    <AppLayout title="Cloudinary Storage">
      <Head title="Cloudinary Storage" />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Cloudinary Storage</h1>
          <p className="mt-1 text-sm text-slate-500">Monitor storage and recent uploads.</p>
        </div>
        <button
          type="button"
          onClick={refresh}
          className="rounded-md bg-[#0b5384] px-4 py-2 text-sm font-medium text-white hover:bg-[#09416a]"
        >
          Refresh
        </button>
      </div>

      {hasError ? (
        <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {usage?.error || recentMedia?.error}
        </div>
      ) : null}

      <div className="grid gap-6 md:grid-cols-2">
        <ProgressCard
          title="Storage Usage"
          stat={`${usage?.storage?.usage_percent ?? 0}% used`}
          value={`${usage?.storage?.used_mb ?? 0} MB / ${usage?.storage?.limit_mb ?? 0} MB`}
          percent={usage?.storage?.usage_percent ?? 0}
          helper="Current media storage consumption"
        />
        <ProgressCard
          title="Bandwidth Usage"
          stat={`${usage?.bandwidth?.usage_percent ?? 0}% used`}
          value={`${usage?.bandwidth?.used_mb ?? 0} MB / ${usage?.bandwidth?.limit_mb ?? 0} MB`}
          percent={usage?.bandwidth?.usage_percent ?? 0}
          helper="Monthly delivery bandwidth consumption"
        />
      </div>

      <div className="mt-8 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-slate-900">Recent Media</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Public ID</th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Format</th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Size</th>
                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Created At</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {(Array.isArray(recentMedia) ? recentMedia : []).map((item) => (
                <tr key={item.public_id}>
                  <td className="px-6 py-4 text-sm font-medium text-slate-900">{item.public_id}</td>
                  <td className="px-6 py-4 text-sm text-slate-600">{item.format}</td>
                  <td className="px-6 py-4 text-sm text-slate-600">{item.size_mb} MB</td>
                  <td className="px-6 py-4 text-sm text-slate-600">{formatDisplayDateTime(item.created_at)}</td>
                </tr>
              ))}
              {!hasError && (Array.isArray(recentMedia) ? recentMedia.length : 0) === 0 ? (
                <tr>
                  <td colSpan={4} className="px-6 py-10 text-center text-sm text-slate-500">
                    No recent media found.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
