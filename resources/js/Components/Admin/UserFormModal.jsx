import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import { userFormSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

const roleOptions = [
  { value: 'CASE_MANAGER', label: 'Case Manager' },
  { value: 'AGENCY', label: 'Agency Focal' },
  { value: 'ADMIN', label: 'System Admin' },
];

export default function UserFormModal({ user, agencies, onClose, onBypass, selectedAgencyId }) {
  const isEdit = !!user;
  const isNewUserViaSelectedAgency = !!selectedAgencyId && !user?.id;

  const { data, setData, post, patch, processing, errors, clearErrors } = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: user?.role ?? (isNewUserViaSelectedAgency ? 'AGENCY' : 'CASE_MANAGER'),
    agcy_id: user?.agcy_id ?? (isNewUserViaSelectedAgency ? selectedAgencyId : ''),
    contact_number: user?.contact_number ?? '',
    is_active: user?.is_active ?? true,
  });

  const { validate } = useClientValidation(userFormSchema, data, setError);

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();
    clearErrors();
    if (!validate()) return;
    if (isEdit) {
      patch(route('admin.users.update', user.id), { onSuccess: onClose });
    } else {
      post(route('admin.users.store'), { onSuccess: onClose });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">{isEdit ? 'Edit User' : 'New User'}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required maxLength={255} />
            <InputError message={errors.name} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Email *</label>
            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required />
            <InputError message={errors.email} className="mt-1" />
          </div>
          {isEdit && (
            <div className="flex items-center gap-2 text-sm">
              <span className="font-medium text-slate-700">Email Verified:</span>
              <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${user?.email_verified_at ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'}`}>
                {user?.email_verified_at ? 'Yes' : 'No'}
              </span>
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-slate-700">Password {isEdit ? '(leave blank to keep current)' : '*'}</label>
            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" minLength={8} />
            <InputError message={errors.password} className="mt-1" />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Role *</label>
            <select value={data.role} onChange={(e) => setData('role', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
              {roleOptions.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
            <InputError message={errors.role} className="mt-1" />
          </div>
          {!isNewUserViaSelectedAgency && data.role === 'AGENCY' && (
            <div>
              <label className="block text-sm font-medium text-slate-700">Agency</label>
              <select value={data.agcy_id} onChange={(e) => setData('agcy_id', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                <option value="">Select agency...</option>
                {agencies.map((a) => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
              <InputError message={errors.agcy_id} className="mt-1" />
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-slate-700">Contact Number</label>
            <input type="text" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.contact_number} className="mt-1" />
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
