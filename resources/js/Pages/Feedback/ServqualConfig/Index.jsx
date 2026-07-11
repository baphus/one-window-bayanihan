import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatDisplayDate } from '@/lib/utils';

export default function ServqualConfigIndex({ configs = [], hasMultipleConfigs, services = [] }) {
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [assignTarget, setAssignTarget] = useState(null);
  const [assignServiceId, setAssignServiceId] = useState('');

  const openAssignModal = (config) => {
    setAssignTarget(config);
    setAssignServiceId('');
  };

  const handleDelete = () => {
    if (!deleteTarget) return;
    router.delete(route('servqual-configs.destroy', deleteTarget.id), {
      preserveScroll: true,
      onSuccess: () => setDeleteTarget(null),
      onError: () => setDeleteTarget(null),
    });
  };

  const handleAssign = () => {
    if (!assignTarget || !assignServiceId) return;
    router.post(route('servqual-configs.assign-service', assignTarget.id), { service_id: assignServiceId }, {
      preserveScroll: true,
      onSuccess: () => setAssignTarget(null),
      onError: () => setAssignTarget(null),
    });
  };

  const handleUnassign = (config) => {
    router.post(route('servqual-configs.unassign-service', config.id), {}, { preserveScroll: true });
  };

  const handleMakeActive = (config) => {
    router.patch(route('servqual-configs.activate', config.id), {}, { preserveScroll: true });
  };

  const assignmentLabel = (config) => {
    if (config.service_id) return `Service override: ${config.service?.name || config.service_name}`;
    return config.is_active ? 'Default form — all services' : 'Inactive default form';
  };

  const assignmentTone = (config) => {
    if (config.service_id) return 'bg-violet-50 text-violet-700 border-violet-200';
    if (config.is_active) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
  };

  return (
    <AppLayout title="SERVQUAL Configurations">
      <Head title="SERVQUAL Configurations" />

      <div className="space-y-6">
        <section data-tour="servqual-header" className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-col gap-3 px-5 py-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Feedback</p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">SERVQUAL Configurations</h1>
              <p className="mt-1 text-sm text-slate-500">Manage the default form and any service-specific overrides.</p>
            </div>
            <Link data-tour="servqual-create" href={route('servqual-configs.create')} className="inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800">
              + Add New
            </Link>
          </div>
        </section>

        {configs.length === 0 ? (
          <section data-tour="servqual-list" className="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <p className="text-sm text-slate-500">No SERVQUAL configurations yet.</p>
            <Link href={route('servqual-configs.create')} className="mt-3 inline-flex h-10 items-center rounded-md bg-blue-900 px-4 text-sm font-semibold text-white hover:bg-blue-800">
              Create configuration
            </Link>
          </section>
        ) : (
          <section data-tour="servqual-list" className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-5 py-4">
              <h2 className="text-sm font-bold uppercase tracking-[0.16em] text-slate-900">Configuration list</h2>
            </div>
            <div className="overflow-hidden">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Form</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Assignment</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Questions</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Status</th>
                    <th className="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Created</th>
                    <th className="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {configs.map((config) => (
                      <tr key={config.id} className="hover:bg-slate-50">
                        <td className="px-5 py-4 align-top">
                          <p className="text-sm font-semibold text-slate-900">{config.name || config.service_name}</p>
                          <p className="mt-0.5 text-xs text-slate-500">{config.service_name}</p>
                        </td>
                        <td className="px-5 py-4 align-top">
                          <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${assignmentTone(config)}`}>
                            {assignmentLabel(config)}
                          </span>
                        </td>
                        <td className="px-5 py-4 align-top text-sm text-slate-600">{config.questions.length}</td>
                        <td className="px-5 py-4 align-top">
                          {config.is_active ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">
                              <span className="material-symbols-outlined text-[14px]">check_circle</span>
                              Active
                            </span>
                          ) : (
                            <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Inactive</span>
                          )}
                        </td>
                        <td className="px-5 py-4 align-top text-sm text-slate-600">{formatDisplayDate(config.created_at)}</td>
                        <td className="px-5 py-4 align-top">
                          <div className="flex flex-wrap justify-end gap-2">
                            {!config.is_active ? (
                              <button type="button" onClick={() => handleMakeActive(config)} className="inline-flex h-8 items-center rounded-md bg-blue-900 px-3 text-[11px] font-semibold text-white hover:bg-blue-800">
                                Make Active
                              </button>
                            ) : (
                              <span className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-600">Current</span>
                            )}

                            {config.service_id ? (
                              <button type="button" onClick={() => handleUnassign(config)} className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                Unassign
                              </button>
                            ) : (
                              <button type="button" onClick={() => openAssignModal(config)} className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                Assign
                              </button>
                            )}

                            <Link href={route('servqual-configs.edit', config.id)} className="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                              Edit
                            </Link>

                            {config.is_active && hasMultipleConfigs ? (
                              <span className="inline-flex h-8 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-[11px] font-semibold text-slate-400" title="Deactivate another config first before deleting this one">
                                Delete
                              </span>
                            ) : (
                              <button type="button" onClick={() => setDeleteTarget(config)} className="inline-flex h-8 items-center rounded-md border border-red-200 bg-white px-3 text-[11px] font-semibold text-red-700 hover:bg-red-50">
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
          </section>
        )}

        {assignTarget && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-xl">
              <div className="border-b border-slate-200 px-6 py-5">
                <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Assign service</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">Choose a service override</h2>
              </div>
              <div className="px-6 py-5">
                <p className="text-sm text-slate-600">
                  Assign <span className="font-semibold text-slate-900">{assignTarget.name || assignTarget.service_name}</span> to a service.
                </p>
                <div className="mt-4">
                  <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service</label>
                  <select value={assignServiceId} onChange={(e) => setAssignServiceId(e.target.value)} className="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900">
                    <option value="">Select a service</option>
                    {services.map((service) => (
                      <option key={service.id} value={service.id}>{service.name}</option>
                    ))}
                  </select>
                  <p className="mt-2 text-xs text-slate-500">This form will override the selected service.</p>
                </div>
              </div>
              <div className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                <button type="button" onClick={() => setAssignTarget(null)} className="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" onClick={handleAssign} disabled={!assignServiceId} className="inline-flex h-9 items-center rounded-md bg-blue-900 px-4 text-xs font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">Assign</button>
              </div>
            </div>
          </div>
        )}

        {deleteTarget && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-xl">
              <div className="border-b border-slate-200 px-6 py-5">
                <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-red-600">Delete configuration</p>
                <h2 className="mt-2 text-lg font-bold text-slate-900">Confirm deletion</h2>
              </div>
              <div className="px-6 py-5">
                <p className="text-sm text-slate-600">
                  Delete <span className="font-semibold text-slate-900">{deleteTarget.service_name}</span> and its {deleteTarget.questions.length} questions? This cannot be undone.
                </p>
                {deleteTarget.is_active && !hasMultipleConfigs ? (
                  <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This is the active form. Deleting it will leave no active SERVQUAL configuration.</div>
                ) : null}
              </div>
              <div className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                <button type="button" onClick={() => setDeleteTarget(null)} className="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" onClick={handleDelete} className="inline-flex h-9 items-center rounded-md bg-red-600 px-4 text-xs font-semibold text-white hover:bg-red-700">Delete</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
