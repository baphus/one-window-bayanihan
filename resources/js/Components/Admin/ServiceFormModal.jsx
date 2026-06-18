import { useState } from 'react';
import { useForm } from '@inertiajs/react';

function genTempId() {
  return `req_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
}

export default function ServiceFormModal({ service, allAgencies, onClose, onBypass, selectedAgencyId = null }) {
  const isEdit = !!service?.id;
  const { data, setData, post, patch, processing, errors, transform } = useForm({
    name: service?.name ?? '',
    description: service?.description ?? '',
    agcy_id: selectedAgencyId ?? service?.agcy_id ?? '',
    processing_days: service?.processing_days ?? '',
  });

  const [requirements, setRequirements] = useState(
    () => (service?.requirements ?? []).map((r) => ({
      tempId: genTempId(),
      id: r.id,
      name: r.name,
      description: r.description,
      is_required: r.is_required,
    }))
  );

  function addRequirement() {
    setRequirements((prev) => [
      ...prev,
      { tempId: genTempId(), id: null, name: '', description: '', is_required: false },
    ]);
  }

  function removeRequirement(tempId) {
    setRequirements((prev) => prev.filter((r) => r.tempId !== tempId));
  }

  function updateRequirement(tempId, field, value) {
    setRequirements((prev) =>
      prev.map((r) => (r.tempId === tempId ? { ...r, [field]: value } : r))
    );
  }

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();

    transform((current) => ({
      ...current,
      agcy_id: selectedAgencyId ?? current.agcy_id,
      requirements: requirements.map((r) => ({
        id: r.id || null,
        name: r.name,
        description: r.description,
        is_required: r.is_required,
      })),
    }));

    if (isEdit) {
      patch(route('admin.services.update', service.id), { onSuccess: onClose });
    } else {
      post(route('admin.services.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Service' : 'New Service'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Description</label>
            <textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          </div>
          {!selectedAgencyId && (
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">Agency *</label>
              <select value={data.agcy_id} onChange={(e) => setData('agcy_id', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">Select an agency...</option>
                {allAgencies.map((agency) => (
                  <option key={agency.id} value={agency.id}>{agency.name}</option>
                ))}
              </select>
              {errors.agcy_id && <p className="mt-1 text-sm text-red-600">{errors.agcy_id}</p>}
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-slate-700">Processing Days</label>
            <input type="number" min="0" max="365" value={data.processing_days} onChange={(e) => setData('processing_days', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          </div>

          {/* Requirements Section */}
          <div className="border-t border-slate-200 pt-4">
            <div className="flex items-center justify-between mb-3">
              <label className="block text-sm font-medium text-slate-700">Requirements</label>
              <button type="button" onClick={addRequirement} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                + Add Requirement
              </button>
            </div>
            {requirements.length === 0 && (
              <p className="text-xs text-slate-400 italic">No requirements added yet.</p>
            )}
            {requirements.map((req) => (
              <div key={req.tempId} className="border border-slate-200 rounded-md p-3 mb-2 space-y-2">
                <div className="flex items-start justify-between gap-2">
                  <input
                    type="text"
                    placeholder="Requirement name"
                    value={req.name}
                    onChange={(e) => updateRequirement(req.tempId, 'name', e.target.value)}
                    className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                  />
                  <button
                    type="button"
                    onClick={() => removeRequirement(req.tempId)}
                    className="text-slate-400 hover:text-red-500 shrink-0 text-lg leading-none"
                    title="Remove requirement"
                  >
                    &times;
                  </button>
                </div>
                <textarea
                  rows={2}
                  placeholder="Description (optional)"
                  value={req.description}
                  onChange={(e) => updateRequirement(req.tempId, 'description', e.target.value)}
                  className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                />
                <label className="flex items-center gap-2 text-sm text-slate-600 select-none">
                  <input
                    type="checkbox"
                    checked={req.is_required}
                    onChange={(e) => updateRequirement(req.tempId, 'is_required', e.target.checked)}
                    className="rounded border-slate-300"
                  />
                  Required
                </label>
              </div>
            ))}
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] disabled:opacity-50">
              {isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
