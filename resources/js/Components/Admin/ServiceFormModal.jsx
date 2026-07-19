import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import { serviceFormSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

function genTempId() {
  return `req_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
}

export default function ServiceFormModal({ service, allAgencies, onClose, onBypass, selectedAgencyId = null }) {
  const isEdit = !!service?.id;
  const { data, setData, post, patch, processing, errors, transform, clearErrors, setError } = useForm({
    name: service?.name ?? '',
    description: service?.description ?? '',
    agcy_id: selectedAgencyId ?? service?.agcy_id ?? '',
    processing_days: service?.processing_days ?? '',
  });

  const { validate } = useClientValidation(serviceFormSchema, data, setError);

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
    clearErrors();
    if (!validate()) return;

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

  const selectedAgency = allAgencies.find((a) => a.id === data.agcy_id);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="sticky top-0 z-10 bg-white px-6 py-4 border-b border-slate-200 flex items-center justify-between rounded-t-xl">
          <div>
            <h3 className="text-lg font-bold text-slate-900">{isEdit ? 'Edit Service' : 'Create New Service'}</h3>
            <p className="text-xs text-slate-500 mt-0.5">
              {isEdit ? 'Update service details and requirements.' : 'Define a new service offered by an agency.'}
            </p>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-6">
          {/* Section: Service Details */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Service Details</legend>

            {/* Name */}
            <div>
              <label className="block text-sm font-medium text-slate-700">Service Name <span className="text-red-500">*</span></label>
              <input
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                placeholder="e.g. Legal Assistance, Skills Training, Medical Repatriation"
                required
                maxLength={255}
              />
              <InputError message={errors.name} className="mt-1" />
            </div>

            {/* Description */}
            <div>
              <label className="block text-sm font-medium text-slate-700">Description</label>
              <textarea
                rows={3}
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm resize-none"
                placeholder="Brief description of what this service provides to OFWs..."
              />
              <InputError message={errors.description} className="mt-1" />
            </div>
          </fieldset>

          {/* Section: Assignment & Processing */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Assignment & Processing</legend>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Agency */}
              {!selectedAgencyId ? (
                <div>
                  <label className="block text-sm font-medium text-slate-700">Agency <span className="text-red-500">*</span></label>
                  <select
                    value={data.agcy_id}
                    onChange={(e) => setData('agcy_id', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 text-sm"
                    required
                  >
                    <option value="">Select an agency...</option>
                    {allAgencies.map((agency) => (
                      <option key={agency.id} value={agency.id}>{agency.name}{agency.short ? ` (${agency.short})` : ''}</option>
                    ))}
                  </select>
                  <InputError message={errors.agcy_id} className="mt-1" />
                </div>
              ) : (
                <div>
                  <label className="block text-sm font-medium text-slate-700">Agency</label>
                  <div className="mt-1 flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm font-medium text-slate-700">
                    {selectedAgency?.name || 'Selected Agency'}
                  </div>
                </div>
              )}

              {/* Processing Days */}
              <div>
                <label className="block text-sm font-medium text-slate-700">Processing Days</label>
                <input
                  type="number"
                  min="0"
                  max="365"
                  value={data.processing_days}
                  onChange={(e) => setData('processing_days', e.target.value === '' ? '' : Number(e.target.value))}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                  placeholder="e.g. 7"
                />
                <p className="mt-1 text-xs text-slate-500">Estimated number of working days to complete this service.</p>
                <InputError message={errors.processing_days} className="mt-1" />
              </div>
            </div>
          </fieldset>

          {/* Section: Requirements */}
          <fieldset className="space-y-4">
            <div className="flex items-center justify-between">
              <legend className="text-xs font-bold uppercase tracking-widest text-slate-400">Requirements</legend>
              <button
                type="button"
                onClick={addRequirement}
                className="text-xs font-bold text-blue-900 hover:text-blue-700 transition-colors"
              >
                + Add Requirement
              </button>
            </div>

            {requirements.length === 0 ? (
              <div className="rounded-lg border border-dashed border-slate-300 p-6 text-center">
                <p className="text-sm text-slate-400">No requirements added yet.</p>
                <p className="text-xs text-slate-400 mt-1">Add documents or prerequisites that OFWs need to submit for this service.</p>
                <button
                  type="button"
                  onClick={addRequirement}
                  className="mt-3 px-3 py-1.5 text-xs font-bold text-blue-900 border border-blue-200 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                >
                  + Add First Requirement
                </button>
              </div>
            ) : (
              <div className="space-y-3">
                {requirements.map((req, idx) => (
                  <div key={req.tempId} className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2.5">
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2 flex-1">
                        <span className="text-[11px] font-bold text-slate-400 bg-slate-200 rounded-full w-5 h-5 flex items-center justify-center shrink-0">
                          {idx + 1}
                        </span>
                        <input
                          type="text"
                          placeholder="Requirement name (e.g. Valid ID, Contract copy)"
                          value={req.name}
                          onChange={(e) => updateRequirement(req.tempId, 'name', e.target.value)}
                          className="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        />
                      </div>
                      <button
                        type="button"
                        onClick={() => removeRequirement(req.tempId)}
                        className="text-slate-400 hover:text-red-500 shrink-0 text-lg leading-none mt-1 transition-colors"
                        title="Remove requirement"
                      >
                        &times;
                      </button>
                    </div>
                    <textarea
                      rows={2}
                      placeholder="Description or instructions (optional)"
                      value={req.description}
                      onChange={(e) => updateRequirement(req.tempId, 'description', e.target.value)}
                      className="block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm resize-none"
                    />
                    <label className="flex items-center gap-2 text-sm text-slate-600 select-none cursor-pointer">
                      <input
                        type="checkbox"
                        checked={req.is_required}
                        onChange={(e) => updateRequirement(req.tempId, 'is_required', e.target.checked)}
                        className="rounded border-slate-300 text-blue-900 focus:ring-blue-900"
                      />
                      <span className="text-xs font-medium">Mandatory requirement</span>
                    </label>
                  </div>
                ))}
              </div>
            )}
          </fieldset>

          {/* Actions */}
          <div className="flex items-center justify-between pt-3 border-t border-slate-100">
            <p className="text-[11px] text-slate-400">
              <span className="text-red-500">*</span> Required fields
            </p>
            <div className="flex gap-3">
              <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                Cancel
              </button>
              <button type="submit" disabled={processing} className="px-5 py-2 text-sm font-bold text-white bg-blue-900 rounded-lg hover:bg-blue-800 disabled:opacity-50 transition-colors">
                {processing
                  ? (isEdit ? 'Updating...' : 'Creating...')
                  : (isEdit ? 'Update Service' : 'Create Service')
                }
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
