import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import { caseCategorySchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

export default function CaseCategoryFormModal({ category, onClose, onBypass }) {
  const isEdit = !!category;
  const { data, setData, post, patch, processing, errors, clearErrors, setError } = useForm({
    name: category?.name ?? '',
    description: category?.description ?? '',
    color: category?.color ?? '',
    sort_order: category?.sort_order ?? 0,
    is_active: category?.is_active ?? true,
  });
  const { validate } = useClientValidation(caseCategorySchema, data, setError);

  function handleSubmit(e) {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;
    onBypass?.();
    if (isEdit) {
      patch(route('admin.case-categories.update', category.id), { onSuccess: onClose });
    } else {
      post(route('admin.case-categories.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Category' : 'New Category'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required maxLength={255} />
            <InputError message={errors.name} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Description</label>
            <textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.description} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Color</label>
            <div className="mt-1 flex items-center gap-3">
              <input type="color" value={data.color || '#0b5384'} onChange={(e) => setData('color', e.target.value)} className="w-10 h-10 rounded border border-slate-300 cursor-pointer" />
              <input type="text" value={data.color} onChange={(e) => setData('color', e.target.value)} placeholder="#HEX" className="block w-32 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </div>
            <InputError message={errors.color} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Sort Order</label>
            <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value, 10) || 0)} className="mt-1 block w-24 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
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
