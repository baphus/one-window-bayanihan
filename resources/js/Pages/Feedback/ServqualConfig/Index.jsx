import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDisplayDate } from '@/lib/utils';

export default function ServqualConfigIndex({ configs }) {
  const [deleteTarget, setDeleteTarget] = useState(null);

  const handleDelete = () => {
    if (!deleteTarget) return;
    router.delete(route('servqual-configs.destroy', deleteTarget.id), {
      preserveScroll: true,
      onSuccess: () => setDeleteTarget(null),
      onError: () => setDeleteTarget(null),
    });
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

      {configs.data.length === 0 ? (
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
              {configs.data.map((config) => (
                <tr key={config.id} className="hover:bg-slate-50">
                  <td className="whitespace-nowrap px-6 py-4">
                    <p className="text-sm font-bold text-slate-800">{config.service_name}</p>
                  </td>
                  <td className="whitespace-nowrap px-6 py-4">
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">
                      {config.questions_count} questions
                    </span>
                  </td>
                  <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                    {formatDisplayDate(config.created_at)}
                  </td>
                  <td className="whitespace-nowrap px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Link
                        href={route('servqual-configs.edit', config.id)}
                        className="inline-flex h-8 items-center rounded border border-slate-300 bg-white px-3 text-[11px] font-bold text-slate-700 hover:bg-slate-50"
                      >
                        Edit
                      </Link>
                      <button
                        type="button"
                        onClick={() => setDeleteTarget(config)}
                        className="inline-flex h-8 items-center rounded border border-red-200 bg-red-50 px-3 text-[11px] font-bold text-red-700 hover:bg-red-100"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {configs.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-slate-200 px-6 py-4">
              <p className="text-sm text-slate-600">
                Showing {configs.from} to {configs.to} of {configs.total}
              </p>
              <div className="flex gap-2">
                {configs.links.map((link, i) => (
                  <Link
                    key={i}
                    href={link.url || '#'}
                    className={`inline-flex items-center rounded-md px-3 py-1 text-sm ${
                      link.active
                        ? 'bg-indigo-600 text-white'
                        : 'bg-white text-slate-700 hover:bg-slate-50'
                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          )}
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
                and all its {deleteTarget.questions_count} questions. This action cannot be undone.
              </p>
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
