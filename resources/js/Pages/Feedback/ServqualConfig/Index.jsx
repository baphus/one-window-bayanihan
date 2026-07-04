import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDisplayDate } from '@/lib/utils';

export default function ServqualConfigIndex({ configs, hasMultipleConfigs }) {
  const [deleteTarget, setDeleteTarget] = useState(null);

  const handleDelete = () => {
    if (!deleteTarget) return;
    router.delete(route('servqual-configs.destroy', deleteTarget.id), {
      preserveScroll: true,
      onSuccess: () => setDeleteTarget(null),
      onError: () => setDeleteTarget(null),
    });
  };

  const handleMakeActive = (config) => {
    router.patch(route('servqual-configs.activate', config.id), {}, {
      preserveScroll: true,
    });
  };

  const canDelete = (config) => {
    if (!config.is_active) return true;
    if (!hasMultipleConfigs) return true;
    return false;
  };

  return (
    <AppLayout title="SERVQUAL Configurations">
      <Head title="SERVQUAL Configurations" />

      <div className="mb-8 flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">SERVQUAL Configurations</h1>
          <p className="mt-1 text-sm text-slate-500">
            Manage SERVQUAL survey configurations for evaluating agency service quality.
          </p>
        </div>
        <Link
          href={route('servqual-configs.create')}
          className="inline-flex h-10 items-center rounded-md bg-indigo-600 px-4 text-sm font-bold text-white hover:bg-indigo-700"
        >
          + Add New
        </Link>
      </div>

      {configs.length === 0 ? (
        <div className="rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
          <p className="text-sm text-slate-500">No SERVQUAL configurations yet.</p>
          <Link
            href={route('servqual-configs.create')}
            className="mt-3 inline-flex h-9 items-center rounded-md bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700"
          >
            Create Your First Configuration
          </Link>
        </div>
      ) : (
        <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Service Name
                </th>
                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Questions
                </th>
                <th className="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Created
                </th>
                <th className="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {configs.map((config) => (
                <tr key={config.id} className="hover:bg-slate-50">
                  <td className="whitespace-nowrap px-6 py-4">
                    <p className="text-sm font-bold text-slate-800">{config.service_name}</p>
                  </td>
                  <td className="whitespace-nowrap px-6 py-4">
                    {config.is_active ? (
                      <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700">
                        <span className="material-symbols-outlined text-[14px]">check_circle</span>
                        Active Default
                        {config.activated_at && (
                          <span className="ml-1 text-[10px] font-normal text-indigo-500">
                            {formatDisplayDate(config.activated_at)}
                          </span>
                        )}
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                        Inactive
                      </span>
                    )}
                  </td>
                  <td className="whitespace-nowrap px-6 py-4">
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">
                      {config.questions.length} questions
                    </span>
                  </td>
                  <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                    {formatDisplayDate(config.created_at)}
                  </td>
                  <td className="whitespace-nowrap px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {!config.is_active ? (
                        <button
                          type="button"
                          onClick={() => handleMakeActive(config)}
                          className="inline-flex h-8 items-center gap-1 rounded border border-indigo-300 bg-indigo-50 px-3 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100"
                        >
                          <span className="material-symbols-outlined text-[14px]">check_circle</span>
                          Make Active
                        </button>
                      ) : (
                        <span className="inline-flex h-8 items-center gap-1 rounded border border-indigo-200 bg-indigo-50 px-3 text-[11px] font-bold text-indigo-500">
                          <span className="material-symbols-outlined text-[14px]">check_circle</span>
                          Active
                        </span>
                      )}
                      <Link
                        href={route('servqual-configs.edit', config.id)}
                        className="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-3 text-[11px] font-bold text-slate-700 hover:bg-slate-50"
                      >
                        Edit
                      </Link>
                      {config.is_active && hasMultipleConfigs ? (
                        <span
                          className="inline-flex h-8 items-center gap-1 rounded border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-400"
                          title="Deactivate another config first before deleting this one"
                        >
                          <span className="material-symbols-outlined text-[14px]">block</span>
                          Delete
                        </span>
                      ) : (
                        <button
                          type="button"
                          onClick={() => setDeleteTarget(config)}
                          className="inline-flex h-8 items-center rounded border border-red-200 bg-red-50 px-3 text-[11px] font-bold text-red-700 hover:bg-red-100"
                        >
                          Delete
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-xl">
            <div className="border-b border-slate-200 px-6 py-5">
              <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-red-600">
                Delete Configuration
              </p>
              <h2 className="text-lg font-bold text-slate-900">Confirm Deletion</h2>
            </div>
            <div className="px-6 py-5">
              <p className="text-sm text-slate-600">
                You are about to delete{' '}
                <span className="font-bold text-slate-900">{deleteTarget.service_name}</span>{' '}
                and all its {deleteTarget.questions.length} questions. This action cannot be undone.
              </p>
              {deleteTarget.is_active && !hasMultipleConfigs && (
                <div className="mt-3 rounded-md bg-amber-50 border border-amber-200 px-4 py-3">
                  <p className="text-sm text-amber-800">
                    <span className="material-symbols-outlined mr-1 align-middle text-[16px]">warning</span>
                    This is the active configuration. Deleting it will leave no active SERVQUAL configuration. You will need to create or activate another one.
                  </p>
                </div>
              )}
            </div>
            <div className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
              <button
                type="button"
                onClick={() => setDeleteTarget(null)}
                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDelete}
                className="h-9 rounded bg-red-600 px-4 text-xs font-bold text-white hover:bg-red-700"
              >
                Delete Configuration
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
