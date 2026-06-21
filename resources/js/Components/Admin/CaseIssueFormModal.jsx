import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import { caseIssueSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

export default function CaseIssueFormModal({ issue, onClose, onBypass }) {
  const isEdit = !!issue;
  const { data, setData, post, patch, processing, errors, clearErrors, setError } = useForm({
    name: issue?.name ?? '',
    sort_order: issue?.sort_order ?? 0,
    is_active: issue?.is_active ?? true,
  });
  const { validate } = useClientValidation(caseIssueSchema, data, setError);

  function handleSubmit(e) {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;
    onBypass?.();
    if (isEdit) {
      patch(route('admin.case-issues.update', issue.id), { onSuccess: onClose });
    } else {
      post(route('admin.case-issues.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Issue' : 'New Issue'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required maxLength={255} />
            <InputError message={errors.name} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Sort Order</label>
            <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value, 10) || 0)} className="mt-1 block w-24 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.sort_order} className="mt-1" />
          </div>
          {isEdit && (
            <div className="flex items-center gap-2">
              <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
              <label htmlFor="is_active" className="text-sm text-slate-700">Active</label>
            </div>
          )}
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
