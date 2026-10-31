import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import LogoUpload from '../LogoUpload';
import AgencyMapView from '@/Components/AgencyMapView';
import { parseGoogleMapsUrl } from '@/lib/maps';
import { agencyFormSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

function parseContactInfo(contactInfo) {
  const fields = {
    contact_address: '',
    contact_tel: '',
    contact_email: '',
  };

  const rawContactInfo = String(contactInfo || '');
  const lines = rawContactInfo.split(/\r\n|\r|\n/);
  const labels = /^(Address|Contact|Phone|Tel|Telephone|Email):\s*(.*)$/i;
  const parsed = [];
  const seenFields = new Set();
  let currentField = null;
  let hasUnmatchedContent = false;
  let hasDuplicateField = false;

  lines.forEach((line) => {
    const match = line.match(labels);
    if (match) {
      currentField = {
        address: 'contact_address',
        contact: 'contact_tel',
        phone: 'contact_tel',
        tel: 'contact_tel',
        telephone: 'contact_tel',
        email: 'contact_email',
      }[match[1].toLowerCase()];
      hasDuplicateField = hasDuplicateField || seenFields.has(currentField);
      seenFields.add(currentField);
      parsed.push({ field: currentField, value: match[2] });
    } else if (currentField === 'contact_address') {
      parsed[parsed.length - 1].value += `\n${line}`;
    } else if (currentField && line.trim()) {
      hasUnmatchedContent = true;
    } else if (line.trim()) {
      hasUnmatchedContent = true;
      currentField = null;
    }
  });

  if (parsed.length === 0 || hasUnmatchedContent || hasDuplicateField) {
    fields.contact_address = rawContactInfo;
    return fields;
  }

  parsed.forEach(({ field, value }) => {
    fields[field] = value.trim();
  });

  return fields;
}

export default function AgencyFormModal({ agency, onClose, onBypass }) {
  const isEdit = !!agency;
  const contactFields = parseContactInfo(agency?.contact_info);
  const { data, setData, post, patch, transform, processing, errors, setError, clearErrors } = useForm({
    name: agency?.name ?? '',
    short: agency?.short ?? '',
    description: agency?.description ?? '',
    contact_info: agency?.contact_info ?? '',
    ...contactFields,
    logo_url: agency?.logo_url ?? '',
    location_query: agency?.location_query ?? '',
    latitude: agency?.latitude ?? null,
    longitude: agency?.longitude ?? null,
    map_link: agency?.map_link ?? '',
    is_active: agency?.is_active ?? true,
  });

  const [mapPreview, setMapPreview] = useState(null);

  const { validate } = useClientValidation(agencyFormSchema, data, setError);
  const originalContactInfo = agency?.contact_info ?? '';
  const isContactUnchanged = data.contact_address === contactFields.contact_address
    && data.contact_tel === contactFields.contact_tel
    && data.contact_email === contactFields.contact_email;

  function handleMapLinkChange(value) {
    setData('map_link', value);
    const parsed = parseGoogleMapsUrl(value);
    if (parsed.isParseable && parsed.lat != null && parsed.lng != null) {
      setMapPreview(`📍 ${parsed.lat.toFixed(6)}, ${parsed.lng.toFixed(6)}`);
    } else if (parsed.isParseable && parsed.query) {
      setMapPreview(`📍 ${parsed.query}`);
    } else if (value && value.trim()) {
      setMapPreview('🔗 Short URL — will show as link');
    } else {
      setMapPreview(null);
    }
  }

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();
    clearErrors();
    if (!validate()) return;
    transform((formData) => {
      const payload = { ...formData };
      if (isEdit && isContactUnchanged) {
        delete payload.contact_address;
        delete payload.contact_tel;
        delete payload.contact_email;
        payload.contact_info = originalContactInfo;
      }
      if (isEdit && data.logo_url instanceof File) {
        payload._method = 'patch';
      }
      return payload;
    });
    if (isEdit) {
      if (data.logo_url instanceof File) {
        post(route('admin.agencies.update', agency.id), { onSuccess: onClose, forceFormData: true });
      } else {
        patch(route('admin.agencies.update', agency.id), { onSuccess: onClose });
      }
    } else {
      post(route('admin.agencies.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-surface-bright rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto owb-modal-animate" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit Agency' : 'New Agency'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm" required maxLength={255} />
            <InputError message={errors.name} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Short Name *</label>
            <input type="text" value={data.short} onChange={(e) => setData('short', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm" required maxLength={50} />
            <InputError message={errors.short} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Description</label>
            <textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm" />
            <InputError message={errors.description} className="mt-1" />
          </div>
          <div className="space-y-3">
            <p className="text-sm font-semibold text-slate-700">Contact Information</p>
            <div>
              <label className="block text-sm font-medium text-slate-700">Address</label>
              <textarea rows={2} value={data.contact_address} onChange={(e) => setData('contact_address', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm" />
              <InputError message={errors.contact_address} className="mt-1" />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700">Tel</label>
              <input type="text" value={data.contact_tel} onChange={(e) => setData('contact_tel', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm" />
              <InputError message={errors.contact_tel} className="mt-1" />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700">Email</label>
              <input type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm" />
              <InputError message={errors.contact_email} className="mt-1" />
            </div>
            <InputError message={errors.contact_info} className="mt-1" />
          </div>
          <LogoUpload
            currentLogoUrl={data.logo_url}
            onChange={(file) => setData('logo_url', file)}
          />
          <InputError message={errors.logo_url} className="mt-1" />
          <div>
            <label className="block text-sm font-medium text-slate-700">Google Maps Location Link</label>
            <input
              type="url"
              value={data.map_link}
              onChange={(e) => handleMapLinkChange(e.target.value)}
              placeholder="Paste Google Maps share link..."
              className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
              maxLength={2048}
            />
            {mapPreview && (
              <p className="mt-1 text-xs text-slate-500">{mapPreview}</p>
            )}
            <div className="mt-2">
              <AgencyMapView
                mapLink={data.map_link}
                latitude={data.latitude}
                longitude={data.longitude}
                locationQuery={data.location_query}
                agencyName={data.name || 'Agency'}
                embedHeight="180px"
              />
            </div>
            <InputError message={errors.map_link} className="mt-1" />
          </div>
          {isEdit && !agency.is_default && (
            <div className="flex items-center gap-2">
              <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
              <label htmlFor="is_active" className="text-sm text-slate-700">Active</label>
            </div>
          )}
          {isEdit && agency.is_default && (
            <p className="text-xs font-medium text-on-warning-container bg-warning-container border border-warning/20 rounded-md px-3 py-2">
              Default agencies must stay active.
            </p>
          )}
          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-container disabled:opacity-50">
              {isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
