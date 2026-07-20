import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/Components/InputError';

const roleOptions = [
  { value: 'CASE_MANAGER', label: 'Case Manager', description: 'Manages OFW cases end-to-end' },
  { value: 'AGENCY', label: 'Agency Focal', description: 'Handles referrals for their agency' },
  { value: 'ADMIN', label: 'System Admin', description: 'Full system access and configuration' },
];

export default function UserFormModal({ user, agencies, onClose, onBypass, selectedAgencyId }) {
  const isEdit = !!user;
  const isNewUserViaSelectedAgency = !!selectedAgencyId && !user?.id;

  const { data, setData, post, patch, processing, errors, clearErrors, setError } = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    role: user?.role ?? (isNewUserViaSelectedAgency ? 'AGENCY' : 'CASE_MANAGER'),
    agcy_id: user?.agcy_id ?? (isNewUserViaSelectedAgency ? selectedAgencyId : ''),
    contact_number: user?.contact_number ?? '',
    position: user?.position ?? '',
    department: user?.department ?? '',
    office_location: user?.office_location ?? '',
    bio: user?.bio ?? '',
    is_active: user?.is_active ?? true,
  });

  const selectedRole = roleOptions.find((r) => r.value === data.role);

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();
    clearErrors();

    if (!isEdit) {
      if (data.role === 'AGENCY' && !data.agcy_id) {
        setError('agcy_id', 'Agency is required for Agency Focal users.');
        return;
      }
      post(route('admin.users.invite'), {
        onSuccess: () => {
          onClose?.();
          router.reload({ only: ['users', 'pendingInvites'] });
        },
      });
    } else {
      patch(route('admin.users.update', user.id), {
        onSuccess: () => {
          onClose?.();
          router.reload({ only: ['users'] });
        },
      });
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="sticky top-0 z-10 bg-white px-6 py-4 border-b border-slate-200 flex items-center justify-between rounded-t-xl">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {isEdit ? 'Edit User' : isNewUserViaSelectedAgency ? 'Add Focal Person' : 'Invite User'}
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              {isEdit
                ? 'Update user account details and assignments.'
                : isNewUserViaSelectedAgency
                  ? 'Send an invitation to a new focal person for this agency.'
                  : 'Send an invitation email so the user can set up their own account.'}
            </p>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-6">
          {/* Section: Invite / Account Information */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">
              {isEdit ? 'Account Information' : 'Invite Details'}
            </legend>

            {/* Name (edit only) */}
            {isEdit && (
              <div>
                <label className="block text-sm font-medium text-slate-700">Full Name <span className="text-red-500">*</span></label>
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                  placeholder="e.g. Juan Dela Cruz"
                  required
                  maxLength={255}
                />
                <InputError message={errors.name} className="mt-1" />
              </div>
            )}

            {/* Email */}
            <div>
              <label className="block text-sm font-medium text-slate-700">Email Address <span className="text-red-500">*</span></label>
              <input
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                placeholder="user@example.gov.ph"
                required
              />
              <InputError message={errors.email} className="mt-1" />
            </div>

            {/* Email Verified badge (edit only — display only) */}
            {isEdit && (
              <div className="flex items-center gap-2 text-sm">
                <span className="font-medium text-slate-700">Email Verified:</span>
                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${user?.email_verified_at ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'}`}>
                  {user?.email_verified_at ? 'Yes' : 'No'}
                </span>
                {user?.email_verified_at && (
                  <span className="text-xs text-slate-400">— Changing email will reset verification</span>
                )}
              </div>
            )}

            {!isEdit && (
              <p className="text-xs text-slate-500 mt-1">
                An invitation email will be sent to this address. The user will set their own name, password, and profile details.
              </p>
            )}
          </fieldset>

          {/* Section: Role & Assignment */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Role & Assignment</legend>

            {/* Role */}
            <div>
              <label className="block text-sm font-medium text-slate-700">Role <span className="text-red-500">*</span></label>
              {isNewUserViaSelectedAgency ? (
                <>
                  <div className="mt-1 flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm font-medium text-slate-700">
                    <span className="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border bg-amber-100 text-amber-800 border-amber-300">
                      Agency Focal
                    </span>
                    <span className="text-xs text-slate-500">— assigned automatically for this agency</span>
                  </div>
                  {!isEdit && <input type="hidden" value="AGENCY" />}
                </>
              ) : (
                <>
                  <select
                    value={data.role}
                    onChange={(e) => {
                      setData('role', e.target.value);
                      if (e.target.value !== 'AGENCY') {
                        setData('agcy_id', '');
                      }
                    }}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 text-sm"
                    required
                  >
                    {roleOptions.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                  {selectedRole && (
                    <p className="mt-1 text-xs text-slate-500">{selectedRole.description}</p>
                  )}
                </>
              )}
              <InputError message={errors.role} className="mt-1" />
            </div>

            {/* Agency — shown and required only for AGENCY role */}
            {data.role === 'AGENCY' && !isNewUserViaSelectedAgency && (
              <div>
                <label className="block text-sm font-medium text-slate-700">Agency <span className="text-red-500">*</span></label>
                <select
                  value={data.agcy_id}
                  onChange={(e) => setData('agcy_id', e.target.value)}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2 text-sm"
                  required
                >
                  <option value="">Select agency...</option>
                  {agencies.map((a) => (
                    <option key={a.id} value={a.id}>{a.name} ({a.short})</option>
                  ))}
                </select>
                <InputError message={errors.agcy_id} className="mt-1" />
              </div>
            )}

            {/* Active toggle (edit only) */}
            {isEdit && (
              <div className="flex items-center gap-3 pt-1">
                <button
                  type="button"
                  role="switch"
                  aria-checked={data.is_active}
                  onClick={() => setData('is_active', !data.is_active)}
                  className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-1 ${data.is_active ? 'bg-emerald-500' : 'bg-slate-300'}`}
                >
                  <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition ${data.is_active ? 'translate-x-4' : 'translate-x-0'}`} />
                </button>
                <label className="text-sm text-slate-700 font-medium cursor-pointer" onClick={() => setData('is_active', !data.is_active)}>
                  {data.is_active ? 'Active' : 'Inactive'}
                </label>
              </div>
            )}
          </fieldset>

          {/* Section: Profile Details (edit only) */}
          {isEdit && (
            <fieldset className="space-y-4">
              <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Profile Details</legend>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700">Position / Designation</label>
                  <input
                    type="text"
                    value={data.position}
                    onChange={(e) => setData('position', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder={data.role === 'AGENCY' ? 'e.g. Focal Person' : data.role === 'CASE_MANAGER' ? 'e.g. Case Manager II' : 'e.g. IT Administrator'}
                    maxLength={255}
                  />
                  <InputError message={errors.position} className="mt-1" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700">Department / Division</label>
                  <input
                    type="text"
                    value={data.department}
                    onChange={(e) => setData('department', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder={data.role === 'AGENCY' ? 'e.g. OFW Assistance Division' : 'e.g. Case Management Unit'}
                    maxLength={255}
                  />
                  <InputError message={errors.department} className="mt-1" />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700">Contact Number</label>
                  <input
                    type="text"
                    value={data.contact_number}
                    onChange={(e) => setData('contact_number', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="e.g. 09171234567"
                  />
                  <InputError message={errors.contact_number} className="mt-1" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700">Office Location</label>
                  <input
                    type="text"
                    value={data.office_location}
                    onChange={(e) => setData('office_location', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="e.g. 3rd Floor, DMW Bldg, Cebu City"
                    maxLength={500}
                  />
                  <InputError message={errors.office_location} className="mt-1" />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700">Bio / Notes</label>
                <textarea
                  value={data.bio}
                  onChange={(e) => setData('bio', e.target.value)}
                  rows={2}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm resize-none"
                  placeholder="Short description or notes about this user (optional)"
                  maxLength={2000}
                />
                <div className="flex justify-between mt-1">
                  <InputError message={errors.bio} />
                  <span className="text-[11px] text-slate-400">{data.bio.length}/2000</span>
                </div>
              </div>
            </fieldset>
          )}

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
                  ? (isEdit ? 'Saving...' : 'Sending Invite...')
                  : (isEdit ? 'Save Changes' : isNewUserViaSelectedAgency ? 'Send Invite' : 'Send Invite')
                }
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
