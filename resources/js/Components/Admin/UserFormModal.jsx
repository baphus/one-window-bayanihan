import Select from '@/Components/Select';
import { useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/Components/InputError';
import MfaConfirmDialog from '@/Components/MfaConfirmDialog';
import useMfaStatus from '@/Hooks/useMfaStatus';
import IdentityFields, { emptyIdentity } from '@/Components/IdentityFields';
import AvatarPicker from '@/Components/AvatarPicker';

function maskEmail(email) {
  const [local, domain] = String(email).split('@');
  if (!domain) return email;
  return local.length <= 2 ? email : `${local.slice(0, 2)}***@${domain}`;
}

export default function UserFormModal({ user, agencies, onClose, onBypass, selectedAgencyId, initialMode = 'invite', mfaPolicy }) {
  const isEdit = !!user;
  const isNewUserViaSelectedAgency = !!selectedAgencyId && !user?.id;
  const policy = mfaPolicy ?? user?.mfaPolicy ?? null;

  const [mode, setMode] = useState(isEdit ? 'invite' : initialMode);

  const roles = usePage().props.roles;
  // Citizen is only offered when creating. Editing keeps the account's role
  // fixed so a save can never turn staff into OFW or the reverse.
  const roleOptions = [
    { value: roles.CASE_MANAGER, label: 'Case Manager', description: 'Manages OFW cases end-to-end' },
    { value: roles.AGENCY, label: 'Agency Focal', description: 'Handles referrals for their agency' },
    { value: roles.ADMIN, label: 'System Admin', description: 'Full system access and configuration' },
    ...(isEdit ? [] : [{ value: roles.OFW, label: 'Citizen (OFW)', description: 'Created with a temporary password for manual handover outside the app' }]),
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
    profile_picture: null,
    remove_profile_picture: false,
    mfa_requirement_override: policy?.override ?? 'inherit',
    mfa_change_reason: '',
  });

  const isOfw = data.role === roles.OFW;
  const isCreateMode = !isEdit && !isNewUserViaSelectedAgency && (mode === 'create' || isOfw);

  // Credentials stay out of the main profile payload until the admin submits
  // them, so an admin password never lingers in the edit form's state.
  const emailForm = useForm({ admin_password: '', new_email: '' });
  const otpForm = useForm({ otp: '' });
  const passwordForm = useForm({ password: '', password_confirmation: '', current_password: '' });
  const [emailStep, setEmailStep] = useState('start');

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

  // Photo fields belong only to the citizen form; staff payloads stay as they were.
  const stripPhotoFields = ({ profile_picture, remove_profile_picture, ...rest }) => rest;
  const stripMfaFields = ({ mfa_requirement_override, mfa_change_reason, ...rest }) => rest;
  const stripStaffFields = ({ position, department, office_location, bio, ...rest }) => rest;
  const stripPasswords = ({ password, password_confirmation, ...rest }) => rest;
  const cleanPayload = (payload) => {
    let next = stripMfaFields(payload);
    if (isOfw) {
      next = stripStaffFields(next);
      if (isEdit) next = stripPasswords(next);
    } else {
      next = stripPhotoFields(next);
    }
    return next;
  };

  const updateUrl = isEdit ? route('admin.users.update', user.id) : null;

  // PHP cannot parse multipart bodies on PATCH, so citizen edits post the
  // multipart payload with a method spoof. Staff edits keep their original PATCH.
  const submitUpdate = ({ extra = {}, onSuccess, onError } = {}) => {
    const build = (payload) => {
      const next = { ...cleanPayload(payload), ...extra };
      return isOfw ? { ...next, _method: 'patch' } : next;
    };
    transform(build);
    const options = {
      forceFormData: isOfw,
      onSuccess,
      onError: (errs) => {
        transform(build);
        onError?.(errs);
      },
    };
    if (isOfw) post(updateUrl, options);
    else patch(updateUrl, options);
  };

  const saveEdit = (proof = {}) => {
    onBypass?.();
    submitUpdate({
      extra: policyChanged
        ? { mfa_requirement_override: data.mfa_requirement_override, mfa_change_reason: data.mfa_change_reason.trim(), ...proof }
        : {},
      onSuccess: () => {
        onClose?.();
        router.reload({ only: ['users'] });
      },
      onError: (errs) => {
        if (errs.mfa_confirmation) {
          setForceMfaProof(true);
          setMfaProofError(errs.mfa_confirmation?.[0] ?? errs.mfa_confirmation);
          setMfaConfirmOpen(true);
        }
      },
    });
  };

  const sendEmailOtp = () => {
    emailForm.clearErrors();
    emailForm.post(route('admin.users.email-change.send-otp', user.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        otpForm.reset();
        otpForm.clearErrors();
        setEmailStep('otp');
      },
    });
  };

  const verifyEmailOtp = () => {
    otpForm.clearErrors();
    otpForm.post(route('admin.users.email-change.verify-otp', user.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setData('email', emailForm.data.new_email);
        emailForm.reset('admin_password');
        emailForm.clearErrors();
        setEmailStep('verified');
      },
    });
  };

  const resetPassword = (event) => {
    event.preventDefault();
    passwordForm.clearErrors();
    if (!passwordForm.data.password) {
      passwordForm.setError('password', 'Enter a new temporary password.');
      return;
    }
    if (!passwordForm.data.current_password) {
      passwordForm.setError('current_password', 'Enter your current admin password.');
      return;
    }
    if (passwordForm.data.password !== passwordForm.data.password_confirmation) {
      passwordForm.setError('password_confirmation', 'Passwords do not match.');
      return;
    }
    submitUpdate({
      extra: {
        password: passwordForm.data.password,
        password_confirmation: passwordForm.data.password_confirmation,
        current_password: passwordForm.data.current_password,
      },
      onSuccess: () => {
        passwordForm.reset();
        onClose?.();
        router.reload({ only: ['users'] });
      },
      onError: (errs) => {
        ['password', 'password_confirmation', 'current_password'].forEach((key) => {
          if (errs[key]) passwordForm.setError(key, errs[key]);
        });
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

      if (isOfw && data.password !== data.password_confirmation) {
        setError('password_confirmation', 'Passwords do not match.');
        return;
      }

      transform(cleanPayload);

      if (isCreateMode) {
        post(route('admin.users.store'), {
          forceFormData: isOfw,
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
              {isEdit ? (isOfw ? 'Edit Citizen' : 'Edit User') : isNewUserViaSelectedAgency ? 'Add Focal Person' : isOfw ? 'Create Citizen' : isCreateMode ? 'Create User' : 'Invite User'}
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              {isEdit
                ? isOfw
                  ? 'Update the citizen’s identity, contact details, photo, email, password, and account status.'
                  : 'Update user account details and assignments.'
                : isNewUserViaSelectedAgency
                  ? 'Send an invitation to a new focal person for this agency.'
                  : isOfw
                    ? 'Create the citizen account with a temporary password for manual handover outside the app.'
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
            {!isEdit && !isNewUserViaSelectedAgency && !isOfw && (
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

            {!isEdit && isOfw && (
              <p className="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs text-teal-800">
                Citizen accounts are created directly with a temporary password. There is no invitation email.
              </p>
            )}

            {/* Identity (edit or create mode) */}
            {(isEdit || isCreateMode) && (
              <IdentityFields data={data} setData={setData} errors={errors} requireDemographics={isOfw} />
            )}

            {/* Email — changing a citizen's address needs the admin OTP flow */}
            {isEdit && isOfw ? (
              <div className="space-y-3 rounded-lg border border-slate-200 p-3">
                <div>
                  <label htmlFor="user-email" className="block text-sm font-medium text-slate-700">Email Address</label>
                  <div className="mt-1 flex items-center gap-2">
                    <input
                      id="user-email"
                      type="email"
                      value={data.email}
                      readOnly
                      className="block w-full rounded-lg border-slate-200 bg-slate-50 text-slate-600 shadow-sm text-sm"
                    />
                    {emailStep === 'start' && (
                      <button
                        type="button"
                        onClick={() => { emailForm.reset(); emailForm.clearErrors(); setEmailStep('request'); }}
                        className="shrink-0 text-xs font-bold text-primary hover:underline"
                      >
                        Change email
                      </button>
                    )}
                  </div>
                  <InputError message={errors.email} className="mt-1" />
                  <p className="mt-1 text-xs text-slate-500">Changing this email requires a code sent to the new address and your current admin password.</p>
                </div>

                {emailStep === 'request' && (
                  <div className="space-y-3 border-t border-slate-100 pt-3">
                    <div>
                      <label htmlFor="ofw-new-email" className="block text-sm font-medium text-slate-700">New email address</label>
                      <input
                        id="ofw-new-email"
                        type="email"
                        value={emailForm.data.new_email}
                        onChange={(e) => { emailForm.setData('new_email', e.target.value); emailForm.clearErrors('new_email'); }}
                        className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                        autoComplete="email"
                        required
                      />
                      <InputError message={emailForm.errors.new_email} className="mt-1" />
                    </div>
                    <div>
                      <label htmlFor="ofw-email-admin-password" className="block text-sm font-medium text-slate-700">Your admin password</label>
                      <input
                        id="ofw-email-admin-password"
                        type="password"
                        value={emailForm.data.admin_password}
                        onChange={(e) => { emailForm.setData('admin_password', e.target.value); emailForm.clearErrors('admin_password'); }}
                        className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                        autoComplete="current-password"
                        required
                      />
                      <InputError message={emailForm.errors.admin_password} className="mt-1" />
                    </div>
                    <div className="flex justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => { emailForm.reset(); emailForm.clearErrors(); setEmailStep('start'); }}
                        className="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded-md hover:bg-slate-50"
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={sendEmailOtp}
                        disabled={emailForm.processing}
                        className="px-3 py-1.5 text-xs font-bold text-white bg-primary rounded-md hover:bg-primary-container disabled:opacity-50"
                      >
                        {emailForm.processing ? 'Sending...' : 'Send verification code'}
                      </button>
                    </div>
                  </div>
                )}

                {emailStep === 'otp' && (
                  <div className="space-y-3 border-t border-slate-100 pt-3">
                    <p className="text-xs text-slate-600">
                      Enter the 6-digit code sent to <span className="font-semibold">{maskEmail(emailForm.data.new_email)}</span>.
                    </p>
                    <div>
                      <label htmlFor="ofw-email-otp" className="block text-sm font-medium text-slate-700">Verification code</label>
                      <input
                        id="ofw-email-otp"
                        type="text"
                        inputMode="numeric"
                        maxLength={6}
                        value={otpForm.data.otp}
                        onChange={(e) => { otpForm.setData('otp', e.target.value.replace(/\D/g, '').slice(0, 6)); otpForm.clearErrors('otp'); }}
                        className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm tracking-[0.3em] font-mono"
                        autoComplete="one-time-code"
                        required
                      />
                      <InputError message={otpForm.errors.otp} className="mt-1" />
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <button
                        type="button"
                        onClick={sendEmailOtp}
                        disabled={emailForm.processing}
                        className="text-xs font-bold text-primary hover:underline disabled:opacity-50"
                      >
                        {emailForm.processing ? 'Sending...' : 'Resend code'}
                      </button>
                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => { otpForm.reset(); otpForm.clearErrors(); setEmailStep('request'); }}
                          className="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded-md hover:bg-slate-50"
                        >
                          Back
                        </button>
                        <button
                          type="button"
                          onClick={verifyEmailOtp}
                          disabled={otpForm.processing || otpForm.data.otp.length !== 6}
                          className="px-3 py-1.5 text-xs font-bold text-white bg-primary rounded-md hover:bg-primary-container disabled:opacity-50"
                        >
                          {otpForm.processing ? 'Verifying...' : 'Verify code'}
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {emailStep === 'verified' && (
                  <p role="status" className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    New email verified. Save your changes to apply it.
                  </p>
                )}
              </div>
            ) : (
              <div>
                <label htmlFor="user-email" className="block text-sm font-medium text-slate-700">Email Address <span className="text-red-500">*</span></label>
                <input
                  id="user-email"
                  type="email"
                  value={data.email}
                  onChange={(e) => { setData('email', e.target.value); clearErrors('email'); }}
                  onInvalid={(e) => { e.preventDefault(); setError('email', e.currentTarget.validity.valueMissing ? 'Email is required.' : 'Please provide a valid email address.'); e.currentTarget.focus(); }}
                  aria-invalid={Boolean(errors.email)}
                  aria-describedby={errors.email ? 'user-email-error' : undefined}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                  placeholder="user@example.gov.ph"
                  required
                />
                <InputError id="user-email-error" message={errors.email} className="mt-1" />
              </div>
            )}

            {/* Password fields (create mode only) */}
            {isCreateMode && (
              <>
                <div>
                  <label className="block text-sm font-medium text-slate-700">{isOfw ? 'Temporary Password' : 'Password'} <span className="text-red-500">*</span></label>
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
                  {isOfw && (
                    <p className="mt-1 text-xs text-slate-500">Hand this password over manually outside the app. The citizen must replace it before using the app.</p>
                  )}
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
                {isOfw ? (
                  <span className="text-xs text-slate-400">— Citizen accounts created by an admin start unverified</span>
                ) : user?.email_verified_at ? (
                  <span className="text-xs text-slate-400">— Changing email will reset verification</span>
                ) : null}
              </div>
            )}

            {isEdit && isOfw && user?.must_change_password && (
              <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                This citizen still uses a temporary password. Replacement is required before they can use the app.
              </p>
            )}

            {!isEdit && !isOfw && (
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
              ) : isEdit && isOfw ? (
                <div className="mt-1 flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-sm font-medium text-slate-700">
                  <span className="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border bg-teal-100 text-teal-800 border-teal-300">
                    Citizen (OFW)
                  </span>
                  <span className="text-xs text-slate-500">— the role cannot be changed after creation</span>
                </div>
              ) : (
                <>
                  <Select
                    value={data.role}
                    onChange={(e) => {
                      setData('role', e.target.value);
                      if (e.target.value !== roles.AGENCY) {
                        setData('agcy_id', '');
                      }
                      if (e.target.value === roles.OFW) {
                        setMode('create');
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

            {/* Active toggle (editing) */}
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

            {isCreateMode && isOfw && (
              <div className="flex items-center gap-2 text-sm">
                <span className="font-medium text-slate-700">Account status:</span>
                <span className="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                <span className="text-xs text-slate-500">New citizen accounts start active.</span>
              </div>
            )}
          </fieldset>

          {/* Section: MFA requirement (staff edit only) */}
          {isEdit && !isOfw && (
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

          {/* Section: Citizen profile (create or edit OFW) */}
          {isOfw && (
            <fieldset className="space-y-4">
              <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Citizen Details</legend>

              <AvatarPicker
                key={data.remove_profile_picture ? 'removed' : 'current'}
                file={data.profile_picture}
                onFileChange={(file) => {
                  setData('profile_picture', file);
                  setData('remove_profile_picture', !file && !!user?.avatar_url);
                  clearErrors('profile_picture');
                }}
                name={`${data.first_name ?? ''} ${data.last_name ?? ''}`.trim()}
                initialPreview={data.remove_profile_picture ? null : user?.avatar_url}
                error={errors.profile_picture}
                disabled={processing}
                label="Profile photo"
              />
              {data.remove_profile_picture && (
                <div className="flex items-center gap-2 text-xs text-slate-500">
                  <span>The current photo will be removed when you save.</span>
                  <button type="button" onClick={() => setData('remove_profile_picture', false)} className="font-bold text-primary hover:underline">
                    Keep photo
                  </button>
                </div>
              )}

              <div>
                <label htmlFor="citizen-contact" className="block text-sm font-medium text-slate-700">Contact Number</label>
                <input
                  id="citizen-contact"
                  type="text"
                  value={data.contact_number}
                  onChange={(e) => setData('contact_number', e.target.value)}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                  placeholder="e.g. 09171234567"
                  autoComplete="tel"
                />
                <InputError message={errors.contact_number} className="mt-1" />
              </div>
            </fieldset>
          )}

          {/* Section: Citizen password reset */}
          {isEdit && isOfw && (
            <fieldset className="space-y-4">
              <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Reset Password</legend>
              <p className="text-xs text-slate-500">
                Set a temporary password for manual handover outside the app. The citizen must replace it before using the app.
              </p>

              <div>
                <label htmlFor="ofw-password" className="block text-sm font-medium text-slate-700">New temporary password</label>
                <input
                  id="ofw-password"
                  type="password"
                  value={passwordForm.data.password}
                  onChange={(e) => { passwordForm.setData('password', e.target.value); passwordForm.clearErrors('password'); }}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                  autoComplete="new-password"
                  required
                />
                <InputError message={passwordForm.errors.password} className="mt-1" />
              </div>

              <div>
                <label htmlFor="ofw-password-confirmation" className="block text-sm font-medium text-slate-700">Confirm temporary password</label>
                <input
                  id="ofw-password-confirmation"
                  type="password"
                  value={passwordForm.data.password_confirmation}
                  onChange={(e) => { passwordForm.setData('password_confirmation', e.target.value); passwordForm.clearErrors('password_confirmation'); }}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                  autoComplete="new-password"
                  required
                />
                <InputError message={passwordForm.errors.password_confirmation} className="mt-1" />
              </div>

              <div>
                <label htmlFor="ofw-admin-password" className="block text-sm font-medium text-slate-700">Your current admin password</label>
                <input
                  id="ofw-admin-password"
                  type="password"
                  value={passwordForm.data.current_password}
                  onChange={(e) => { passwordForm.setData('current_password', e.target.value); passwordForm.clearErrors('current_password'); }}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                  autoComplete="current-password"
                  required
                />
                <InputError message={passwordForm.errors.current_password} className="mt-1" />
              </div>

              <div className="flex justify-end">
                <button
                  type="button"
                  onClick={resetPassword}
                  disabled={processing}
                  className="px-3.5 py-2 text-xs font-bold text-white bg-primary rounded-lg hover:bg-primary-container disabled:opacity-50 transition-colors"
                >
                  {processing ? 'Resetting...' : 'Reset Password'}
                </button>
              </div>
            </fieldset>
          )}

          {/* Section: Profile Details (staff edit only) */}
          {isEdit && !isOfw && (
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
                  : (isEdit ? 'Save Changes' : isOfw ? 'Create Citizen' : isCreateMode ? 'Create User' : 'Send Invite')
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
