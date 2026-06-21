import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import LogoUpload from '../LogoUpload';
import MapPicker from '../MapPicker';
import { agencyFormSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

export default function AgencyFormModal({ agency, onClose, onBypass }) {
  const isEdit = !!agency;
  const { data, setData, post, patch, processing, errors, clearErrors } = useForm({
    name: agency?.name ?? '',
    short: agency?.short ?? '',
    description: agency?.description ?? '',
    contact_info: agency?.contact_info ?? '',
    logo_url: agency?.logo_url ?? '',
    location_query: agency?.location_query ?? '',
    latitude: agency?.latitude ?? null,
    longitude: agency?.longitude ?? null,
    is_active: agency?.is_active ?? true,
  });

  const { validate } = useClientValidation(agencyFormSchema, data, setError);

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();
    clearErrors();
    if (!validate()) return;
    if (isEdit) {
      patch(route('admin.agencies.update', agency.id), { onSuccess: onClose });
    } else {
      post(route('admin.agencies.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Agency' : 'New Agency'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required maxLength={255} />
            <InputError message={errors.name} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Short Name *</label>
            <input type="text" value={data.short} onChange={(e) => setData('short', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required maxLength={50} />
            <InputError message={errors.short} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Description</label>
            <textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.description} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Contact Info</label>
            <input type="text" value={data.contact_info} onChange={(e) => setData('contact_info', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.contact_info} className="mt-1" />
          </div>
          <LogoUpload
            currentLogoUrl={data.logo_url}
            onChange={(file) => setData('logo_url', file)}
          />
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Location</label>
            <MapPicker
              latitude={data.latitude}
              longitude={data.longitude}
              onChange={({ latitude, longitude, location_query }) => {
                setData('latitude', latitude);
                setData('longitude', longitude);
                if (location_query) setData('location_query', location_query);
              }}
            />
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
