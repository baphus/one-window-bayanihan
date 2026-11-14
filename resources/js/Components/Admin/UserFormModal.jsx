import Select from '@/Components/Select';
import { useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/Components/InputError';
import MfaConfirmDialog from '@/Components/MfaConfirmDialog';
import useMfaStatus from '@/Hooks/useMfaStatus';
import IdentityFields, { emptyIdentity } from '@/Components/IdentityFields';

export default function UserFormModal({ user, agencies, onClose, onBypass, selectedAgencyId, initialMode = 'invite', mfaPolicy }) {
  const isEdit = !!user;
  const isNewUserViaSelectedAgency = !!selectedAgencyId && !user?.id;
  const policy = mfaPolicy ?? user?.mfaPolicy ?? null;

  const [mode, setMode] = useState(isEdit ? 'invite' : initialMode);
  const isCreateMode = !isEdit && !isNewUserViaSelectedAgency && mode === 'create';

  const roles = usePage().props.roles;
  const roleOptions = [
    { value: roles.CASE_MANAGER, label: 'Case Manager', description: 'Manages OFW cases end-to-end' },
    { value: roles.AGENCY, label: 'Agency Focal', description: 'Handles referrals for their agency' },
    { value: roles.ADMIN, label: 'System Admin', description: 'Full system access and configuration' },
  ];

  const { data, setData, post, patch, processing, errors, clearErrors, setError, transform } = useForm({
    ...emptyIdentity(user),
    email: user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: user?.role ?? (isNewUserViaSelectedAgency ? roles.AGENCY : roles.CASE_MANAGER),
    agcy_id: user?.agcy_id ?? (isNewUserViaSelectedAgency ? selectedAgencyId : ''),
    contact_number: user?.contact_number ?? '',
    position: user?.position ?? '',
    department: user?.department ?? '',
    office_location: user?.office_location ?? '',
    bio: user?.bio ?? '',
    is_active: user?.is_active ?? true,
    mfa_requirement_override: policy?.override ?? 'inherit',
    mfa_change_reason: '',
  });

  const [mfaConfirmOpen, setMfaConfirmOpen] = useState(false);
  const [forceMfaProof, setForceMfaProof] = useState(false);
  const [mfaProofError, setMfaProofError] = useState('');
  const { status: mfaStatus } = useMfaStatus();

  const selectedRole = roleOptions.find((r) => r.value === data.role);
  const policyChanged = isEdit && !!policy && data.mfa_requirement_override !== policy.override;
  const effectiveMfa = data.role === roles.ADMIN
    ? 'required'
    : data.mfa_requirement_override === 'inherit'
      ? policy?.role_default ?? null
      : data.mfa_requirement_override;
  const policyLabel = (value) => (
    value === 'required' ? 'Required'
      : value === 'optional' ? 'Optional'
        : value === 'inherit' ? 'Inherit role default'
          : null
  );

  const stripMfaFields = (payload) => {
    const { mfa_requirement_override, mfa_change_reason, ...rest } = payload;
    return rest;
  };

  const saveEdit = (proof = {}) => {
    onBypass?.();
    const submitData = policyChanged
      ? { ...data, mfa_requirement_override: data.mfa_requirement_override, mfa_change_reason: data.mfa_change_reason.trim(), ...proof }
      : stripMfaFields(data);
    transform(() => submitData);
    patch(route('admin.users.update', user.id), {
      onSuccess: () => {
        onClose?.();
        router.reload({ only: ['users'] });
      },
      onError: (errs) => {
        transform(stripMfaFields);
        if (errs.mfa_confirmation) {
          setForceMfaProof(true);
          setMfaProofError(errs.mfa_confirmation?.[0] ?? errs.mfa_confirmation);
          setMfaConfirmOpen(true);
        }
      },
    });
  };

  function handleSubmit(e) {
    e.preventDefault();
    clearErrors();

    if (!isEdit) {
      onBypass?.();

      if (data.role === roles.AGENCY && !data.agcy_id) {
        setError('agcy_id', 'Agency is required for Agency Focal users.');
        return;
      }

      transform(stripMfaFields);

      if (isCreateMode) {
        post(route('admin.users.store'), {
          onSuccess: () => {
            onClose?.();
            router.reload({ only: ['users'] });
          },
        });
      } else {
        post(route('admin.users.invite'), {
          onSuccess: () => {
            onClose?.();
          },
        });
      }
      return;
    }

    if (policyChanged && !data.mfa_change_reason.trim()) {
      setError('mfa_change_reason', 'A reason is required when changing the MFA requirement.');
      return;
    }

    if (policyChanged) {
      setForceMfaProof(false);
      setMfaProofError('');
      setMfaConfirmOpen(true);
      return;
    }

    saveEdit();
  }

  return (
    <>
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-surface-bright rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto owb-modal-animate" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="sticky top-0 z-10 bg-white px-6 py-4 border-b border-slate-200 flex items-center justify-between rounded-t-xl">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {isEdit ? 'Edit User' : isNewUserViaSelectedAgency ? 'Add Focal Person' : isCreateMode ? 'Create User' : 'Invite User'}
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              {isEdit
                ? 'Update user account details and assignments.'
                : isNewUserViaSelectedAgency
                  ? 'Send an invitation to a new focal person for this agency.'
                  : isCreateMode
                    ? 'Create a new user account directly with name and password.'
                    : 'Send an invitation email so the user can set up their own account.'}
            </p>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-6">
          {/* Section: Invite / Account Information */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">
              {isEdit ? 'Account Information' : isCreateMode ? 'User Details' : 'Invite Details'}
            </legend>

            {/* Mode toggle (new user, not via agency) */}
            {!isEdit && !isNewUserViaSelectedAgency && (
              <div className="flex rounded-lg border border-slate-200 overflow-hidden text-sm font-medium">
                <button
                  type="button"
                  onClick={() => { setMode('invite'); clearErrors(); setData('password', ''); setData('password_confirmation', ''); }}
                  className={`flex-1 py-2 transition-colors ${mode === 'invite' ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:bg-slate-50'}`}
                >
                  Invite User
                </button>
                <button
                  type="button"
                  onClick={() => { setMode('create'); clearErrors(); }}
                  className={`flex-1 py-2 transition-colors ${mode === 'create' ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:bg-slate-50'}`}
                >
                  Create User
                </button>
              </div>
            )}

            {/* Identity (edit or create mode) */}
            {(isEdit || isCreateMode) && (
              <IdentityFields data={data} setData={setData} errors={errors} />
            )}

            {/* Email */}
            <div>
              <label className="block text-sm font-medium text-slate-700">Email Address <span className="text-red-500">*</span></label>
              <input
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                placeholder="user@example.gov.ph"
                required
              />
              <InputError message={errors.email} className="mt-1" />
            </div>

            {/* Password fields (create mode only) */}
            {isCreateMode && (
              <>
                <div>
                  <label className="block text-sm font-medium text-slate-700">Password <span className="text-red-500">*</span></label>
                  <input
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                    placeholder="Minimum 8 characters"
                    required
                    minLength={8}
                    autoComplete="new-password"
                  />
                  <InputError message={errors.password} className="mt-1" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700">Confirm Password <span className="text-red-500">*</span></label>
                  <input
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                    placeholder="Re-enter password"
                    required
                    minLength={8}
                    autoComplete="new-password"
                  />
                  <InputError message={errors.password_confirmation} className="mt-1" />
                </div>
              </>
            )}

            {/* Email Verified badge (edit only — display only) */}
            {isEdit && (
              <div className="flex items-center gap-2 text-sm">
                <span className="font-medium text-slate-700">Email Verified:</span>
                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${user?.email_verified_at ? 'bg-success-container text-on-success-container border-success/20' : 'bg-error-container text-on-error-container border-error/20'}`}>
                  {user?.email_verified_at ? 'Yes' : 'No'}
                </span>
                {user?.email_verified_at && (
                  <span className="text-xs text-slate-400">— Changing email will reset verification</span>
                )}
              </div>
            )}

            {!isEdit && (
              <p className="text-xs text-slate-500 mt-1">
                New accounts inherit the MFA requirement of their role. Individual overrides can be set after the account exists.
              </p>
            )}

            {!isEdit && !isCreateMode && (
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
                    <span className="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border bg-warning-container text-on-warning-container border-warning/20">
                      Agency Focal
                    </span>
                    <span className="text-xs text-slate-500">— assigned automatically for this agency</span>
                  </div>
                  {!isEdit && <input type="hidden" value={roles.AGENCY} />}
                </>
              ) : (
                <>
                  <Select
                    value={data.role}
                    onChange={(e) => {
                      setData('role', e.target.value);
                      if (e.target.value !== roles.AGENCY) {
                        setData('agcy_id', '');
                      }
                    }}
                    className="mt-1 w-full py-2 text-sm"
                    required
                  >
                    {roleOptions.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </Select>
                  {selectedRole && (
                    <p className="mt-1 text-xs text-slate-500">{selectedRole.description}</p>
                  )}
                </>
              )}
              <InputError message={errors.role} className="mt-1" />
            </div>

            {/* Agency — shown and required only for AGENCY role */}
            {data.role === roles.AGENCY && !isNewUserViaSelectedAgency && (
              <div>
                <label className="block text-sm font-medium text-slate-700">Agency <span className="text-red-500">*</span></label>
                <Select
                  value={data.agcy_id}
                  onChange={(e) => setData('agcy_id', e.target.value)}
                  className="mt-1 w-full py-2 text-sm"
                  required
                >
                  <option value="">Select agency...</option>
                  {agencies.map((a) => (
                    <option key={a.id} value={a.id}>{a.name} ({a.short})</option>
                  ))}
                </Select>
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
                  className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 ${data.is_active ? 'bg-success' : 'bg-slate-300'}`}
                >
                  <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition ${data.is_active ? 'translate-x-4' : 'translate-x-0'}`} />
                </button>
                <label className="text-sm text-slate-700 font-medium cursor-pointer" onClick={() => setData('is_active', !data.is_active)}>
                  {data.is_active ? 'Active' : 'Inactive'}
                </label>
              </div>
            )}
          </fieldset>

          {/* Section: MFA requirement (edit only) */}
          {isEdit && (
            <fieldset className="space-y-4">
              <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">MFA Requirement</legend>

              {policy ? (
                <>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Role default</p>
                      <p className="mt-1 text-sm font-semibold text-slate-900">{policyLabel(policy.role_default) ?? '—'}</p>
                    </div>
                    <div>
                      <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Effective requirement</p>
                      <p className={`mt-1 text-sm font-semibold ${effectiveMfa === 'required' ? 'text-amber-700' : 'text-slate-900'}`}>
                        {policyLabel(effectiveMfa) ?? '—'}
                      </p>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-slate-700">User override</label>
                    <Select
                      value={data.mfa_requirement_override}
                      onChange={(e) => {
                        setData('mfa_requirement_override', e.target.value);
                        clearErrors('mfa_requirement_override', 'mfa_change_reason');
                      }}
                      className="mt-1 w-full py-2 text-sm"
                    >
                      <option value="inherit">Inherit role default</option>
                      <option value="required">Required</option>
                      <option value="optional">Optional</option>
                    </Select>
                    <p className="mt-1 text-xs text-slate-500">
                      Administrator accounts are always required, even when an override says optional.
                      Optional never deletes existing authenticator or recovery codes.
                    </p>
                    <InputError message={errors.mfa_requirement_override} className="mt-1" />
                  </div>

                  {policyChanged && (
                    <div>
                      <label className="block text-sm font-medium text-slate-700">
                        Reason for MFA policy change <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={data.mfa_change_reason}
                        onChange={(e) => setData('mfa_change_reason', e.target.value)}
                        className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                        placeholder="e.g. Requested by the division head"
                        maxLength={500}
                      />
                      <InputError message={errors.mfa_change_reason} className="mt-1" />
                    </div>
                  )}
                </>
              ) : (
                <p className="text-xs text-slate-500">
                  MFA policy details are unavailable. Reload the page before changing this user’s requirement.
                </p>
              )}
            </fieldset>
          )}

          {/* Section: Profile Details (edit only) */}
          {isEdit && (
            <fieldset className="space-y-4">
              <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Profile Details</legend>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700">Occupation / Designation</label>
                  <input
                    type="text"
                    value={data.position}
                    onChange={(e) => setData('position', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                    placeholder={data.role === roles.AGENCY ? 'e.g. Focal Person' : data.role === roles.CASE_MANAGER ? 'e.g. Case Manager II' : 'e.g. IT Administrator'}
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
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                    placeholder={data.role === roles.AGENCY ? 'e.g. OFW Assistance Division' : 'e.g. Case Management Unit'}
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
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
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
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
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
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm resize-none"
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
              <button type="submit" disabled={processing} className="px-5 py-2 text-sm font-bold text-white bg-primary rounded-lg hover:bg-primary-container disabled:opacity-50 transition-colors">
                {processing
                  ? (isEdit ? 'Saving...' : isCreateMode ? 'Creating...' : 'Sending Invite...')
                  : (isEdit ? 'Save Changes' : isCreateMode ? 'Create User' : 'Send Invite')
                }
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <MfaConfirmDialog
      open={mfaConfirmOpen}
      title="Confirm MFA policy change"
      description={`Changing ${user?.name ?? 'this user'}’s MFA requirement needs your own MFA confirmation.`}
      confirmLabel="Save policy"
      confirmedUntil={forceMfaProof ? null : mfaStatus?.confirmed_until}
      forceProof={forceMfaProof}
      proofOnly
      reasonRequired
      reason={data.mfa_change_reason}
      error={mfaProofError}
      onCancel={() => {
        setMfaConfirmOpen(false);
        setForceMfaProof(false);
        setMfaProofError('');
      }}
      onConfirmed={({ proof, reason }) => {
        setData('mfa_change_reason', reason);
        setMfaConfirmOpen(false);
        setForceMfaProof(false);
        setMfaProofError('');
        saveEdit({ ...(proof || {}), mfa_change_reason: reason });
      }}
    />
    </>
  );
}
