import { useForm, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useMemo } from 'react';
import InputError from '@/Components/InputError';
import { userFormSchema, createUserFormSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

const roleOptions = [
  { value: 'CASE_MANAGER', label: 'Case Manager', description: 'Manages OFW cases end-to-end' },
  { value: 'AGENCY', label: 'Agency Focal', description: 'Handles referrals for their agency' },
  { value: 'ADMIN', label: 'System Admin', description: 'Full system access and configuration' },
];

const strengthLevels = [
  { label: 'Very Weak', color: 'bg-red-500', textColor: 'text-red-600' },
  { label: 'Weak', color: 'bg-orange-500', textColor: 'text-orange-600' },
  { label: 'Fair', color: 'bg-amber-500', textColor: 'text-amber-600' },
  { label: 'Strong', color: 'bg-emerald-500', textColor: 'text-emerald-600' },
  { label: 'Very Strong', color: 'bg-green-600', textColor: 'text-green-700' },
];

function getPasswordStrength(password) {
  if (!password) return { score: 0, checks: {} };

  const checks = {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    lowercase: /[a-z]/.test(password),
    number: /\d/.test(password),
    symbol: /[^A-Za-z0-9]/.test(password),
  };

  let score = 0;
  if (checks.length) score++;
  if (checks.uppercase && checks.lowercase) score++;
  if (checks.number) score++;
  if (checks.symbol) score++;
  if (password.length >= 12) score++;

  // Cap at max
  score = Math.min(score, 5);

  return { score, checks };
}

function PasswordStrengthMeter({ password }) {
  const { score, checks } = getPasswordStrength(password);
  const level = strengthLevels[Math.max(score - 1, 0)];
  const percentage = (score / 5) * 100;

  return (
    <div className="mt-2 space-y-1.5">
      {/* Bar */}
      <div className="flex items-center gap-2">
        <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
          <div
            className={`h-full rounded-full transition-all duration-300 ease-out ${level.color}`}
            style={{ width: `${percentage}%` }}
          />
        </div>
        <span className={`text-[11px] font-bold ${level.textColor} min-w-[70px] text-right`}>
          {level.label}
        </span>
      </div>

      {/* Requirement checks */}
      <div className="flex flex-wrap gap-x-3 gap-y-0.5">
        <span className={`text-[11px] ${checks.length ? 'text-emerald-600' : 'text-slate-400'}`}>
          {checks.length ? '✓' : '○'} 8+ chars
        </span>
        <span className={`text-[11px] ${checks.uppercase && checks.lowercase ? 'text-emerald-600' : 'text-slate-400'}`}>
          {checks.uppercase && checks.lowercase ? '✓' : '○'} Mixed case
        </span>
        <span className={`text-[11px] ${checks.number ? 'text-emerald-600' : 'text-slate-400'}`}>
          {checks.number ? '✓' : '○'} Number
        </span>
        <span className={`text-[11px] ${checks.symbol ? 'text-emerald-600' : 'text-slate-400'}`}>
          {checks.symbol ? '✓' : '○'} Symbol
        </span>
      </div>
    </div>
  );
}

export default function UserFormModal({ user, agencies, onClose, onBypass, selectedAgencyId }) {
  const isEdit = !!user;
  const isNewUserViaSelectedAgency = !!selectedAgencyId && !user?.id;
  const originalEmail = user?.email ?? '';

  const { data, setData, post, patch, processing, errors, clearErrors, setError } = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: user?.role ?? (isNewUserViaSelectedAgency ? 'AGENCY' : 'CASE_MANAGER'),
    agcy_id: user?.agcy_id ?? (isNewUserViaSelectedAgency ? selectedAgencyId : ''),
    contact_number: user?.contact_number ?? '',
    position: user?.position ?? '',
    department: user?.department ?? '',
    office_location: user?.office_location ?? '',
    bio: user?.bio ?? '',
    is_active: user?.is_active ?? true,
    admin_password: '',
  });

  const schema = useMemo(() => isEdit ? userFormSchema : createUserFormSchema, [isEdit]);

  const emailChanged = isEdit && data.email !== originalEmail;

  // OTP flow state
  const [otpStep, setOtpStep] = useState(false);
  const [otpVerified, setOtpVerified] = useState(false);
  const [otpDigits, setOtpDigits] = useState(['', '', '', '', '', '']);
  const [otpError, setOtpError] = useState('');
  const [otpSending, setOtpSending] = useState(false);
  const [otpVerifying, setOtpVerifying] = useState(false);
  const [hint, setHint] = useState('');
  const [resendCooldown, setResendCooldown] = useState(0);
  const otpRefs = useRef([]);
  const autoFilled = useRef(false);
  const cooldownInterval = useRef(null);

  const { validate } = useClientValidation(schema, data, setError);

  // Cooldown timer for resend
  useEffect(() => {
    if (resendCooldown > 0) {
      cooldownInterval.current = setInterval(() => {
        setResendCooldown((prev) => prev - 1);
      }, 1000);
    }
    return () => {
      if (cooldownInterval.current) {
        clearInterval(cooldownInterval.current);
        cooldownInterval.current = null;
      }
    };
  }, [resendCooldown]);

  // Reset cooldown when entering OTP step
  useEffect(() => {
    if (otpStep) {
      setResendCooldown(30);
    }
  }, [otpStep]);

  // Reset OTP flow when email field changes
  useEffect(() => {
    if (otpStep || otpVerified) {
      setOtpStep(false);
      setOtpVerified(false);
      setOtpDigits(['', '', '', '', '', '']);
      setOtpError('');
    }
  }, [data.email]);

  function handleSendOtp(e) {
    e.preventDefault();
    if (!data.admin_password) {
      setError('admin_password', 'Your admin password is required.');
      return;
    }
    if (!data.email || data.email === originalEmail) {
      setError('email', 'Enter a new email address.');
      return;
    }

    setOtpSending(true);
    setOtpError('');

    router.post(route('admin.users.email-change.send-otp', user.id), {
      admin_password: data.admin_password,
      new_email: data.email,
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        setOtpSending(false);
        setOtpStep(true);
        const flash = page.props.flash || {};
        setHint(flash.email_change_hint || '');

        const debugOtp = flash.email_change_debug_otp;
        if (debugOtp && !autoFilled.current) {
          autoFilled.current = true;
          const digits = debugOtp.split('').slice(0, 6);
          const filled = ['', '', '', '', '', ''];
          digits.forEach((d, i) => { if (d) filled[i] = d; });
          setOtpDigits(filled);
        }

        setTimeout(() => otpRefs.current[0]?.focus(), 100);
      },
      onError: () => {
        setOtpSending(false);
      },
    });
  }

  function handleResendOtp(e) {
    e.preventDefault();
    if (resendCooldown > 0 || otpSending) return;

    setOtpSending(true);
    setOtpError('');
    autoFilled.current = false;

    router.post(route('admin.users.email-change.send-otp', user.id), {
      admin_password: data.admin_password,
      new_email: data.email,
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        setOtpSending(false);
        setResendCooldown(30);
        setOtpDigits(['', '', '', '', '', '']);
        const flash = page.props.flash || {};
        setHint(flash.email_change_hint || '');
        const debugOtp = flash.email_change_debug_otp;
        if (debugOtp && !autoFilled.current) {
          autoFilled.current = true;
          const digits = debugOtp.split('').slice(0, 6);
          const filled = ['', '', '', '', '', ''];
          digits.forEach((d, i) => { if (d) filled[i] = d; });
          setOtpDigits(filled);
        }
        otpRefs.current[0]?.focus();
      },
      onError: () => {
        setOtpSending(false);
        setOtpError('Failed to resend code. Please try again.');
      },
    });
  }

  function handleVerifyOtp() {
    const code = otpDigits.join('');
    if (code.length !== 6) {
      setOtpError('Please enter the complete 6-digit code.');
      return;
    }

    setOtpVerifying(true);
    setOtpError('');

    router.post(route('admin.users.email-change.verify-otp', user.id), {
      new_email: data.email,
      otp: code,
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        setOtpVerifying(false);
        setOtpVerified(true);
        setOtpStep(false);
        setOtpError('');
      },
      onError: (errs) => {
        setOtpVerifying(false);
        setOtpError(errs.otp || 'Invalid or expired OTP.');
      },
    });
  }

  function handleOtpChange(index, value) {
    if (value.length > 1) return;
    const next = [...otpDigits];
    next[index] = value;
    setOtpDigits(next);
    setOtpError('');

    if (value && index < 5) {
      otpRefs.current[index + 1]?.focus();
    }
  }

  function handleOtpKeyDown(index, e) {
    if (e.key === 'Backspace' && otpDigits[index] === '' && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }
  }

  function handleOtpPaste(e) {
    e.preventDefault();
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
    const next = ['', '', '', '', '', ''];
    pasted.forEach((char, idx) => { next[idx] = char; });
    setOtpDigits(next);
    const lastIdx = Math.min(pasted.length - 1, 5);
    if (lastIdx >= 0) otpRefs.current[lastIdx]?.focus();
  }

  function handleSubmit(e) {
    e.preventDefault();
    onBypass?.();
    clearErrors();
    if (!validate()) return;

    if (isEdit && emailChanged && !otpVerified) {
      setError('email', 'Email change requires OTP verification. Please complete the verification flow above.');
      return;
    }

    if (!isEdit && data.password !== data.password_confirmation) {
      setError('password_confirmation', 'Passwords do not match.');
      return;
    }

    if (data.role === 'AGENCY' && !data.agcy_id) {
      setError('agcy_id', 'Agency is required for Agency Focal users.');
      return;
    }

    if (isEdit) {
      patch(route('admin.users.update', user.id), {
        onSuccess: () => {
          onClose?.();
          router.reload({ only: ['users'] });
        },
      });
    } else {
      post(route('admin.users.store'), {
        onSuccess: () => {
          onClose?.();
          router.reload({ only: ['users'] });
        },
      });
    }
  }

  const selectedRole = roleOptions.find((r) => r.value === data.role);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="sticky top-0 z-10 bg-white px-6 py-4 border-b border-slate-200 flex items-center justify-between rounded-t-xl">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {isEdit ? 'Edit User' : isNewUserViaSelectedAgency ? 'Add Focal Person' : 'Create New User'}
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              {isEdit
                ? 'Update user account details and assignments.'
                : isNewUserViaSelectedAgency
                  ? 'Add a new focal person to this agency.'
                  : 'Set up a new user account with role and profile information.'}
            </p>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-6">
          {/* Section: Account Information */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Account Information</legend>

            {/* Name */}
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

            {/* Email */}
            <div>
              <label className="block text-sm font-medium text-slate-700">Email Address <span className="text-red-500">*</span></label>
              <div className="relative">
                <input
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm disabled:bg-slate-100 disabled:text-slate-500"
                  placeholder="user@example.gov.ph"
                  required
                  disabled={otpVerified}
                />
                {otpVerified && (
                  <span className="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                    ✓ Verified
                  </span>
                )}
              </div>
              <InputError message={errors.email} className="mt-1" />
            </div>

            {/* Email Verified badge (display only) */}
            {isEdit && !otpVerified && (
              <div className="flex items-center gap-2 text-sm">
                <span className="font-medium text-slate-700">Email Verified:</span>
                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${user?.email_verified_at ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'}`}>
                  {user?.email_verified_at ? 'Yes' : 'No'}
                </span>
              </div>
            )}

            {/* OTP verified success message */}
            {otpVerified && (
              <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3">
                <p className="text-sm font-medium text-emerald-800">✓ Email updated successfully. The user will receive a verification link at the new address.</p>
              </div>
            )}

            {/* Email change OTP flow */}
            {isEdit && emailChanged && !otpStep && !otpVerified && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-3">
                <p className="text-sm font-medium text-amber-900">Email Change Requires Verification</p>
                <p className="text-xs text-amber-700">Enter your admin password and send a verification code to the new email.</p>
                <div>
                  <label className="block text-sm font-medium text-amber-900">Your Admin Password</label>
                  <input
                    type="password"
                    value={data.admin_password}
                    onChange={(e) => setData('admin_password', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    autoComplete="current-password"
                  />
                  <InputError message={errors.admin_password} className="mt-1" />
                </div>
                <button
                  type="button"
                  onClick={handleSendOtp}
                  disabled={otpSending}
                  className="w-full px-4 py-2 text-sm font-bold text-white bg-blue-900 rounded-lg hover:bg-blue-800 disabled:opacity-50"
                >
                  {otpSending ? 'Sending...' : 'Send Verification Code'}
                </button>
              </div>
            )}

            {/* OTP input step */}
            {isEdit && otpStep && !otpVerified && (
              <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 space-y-3">
                <p className="text-sm font-medium text-blue-900">Enter Verification Code</p>
                {hint && <p className="text-xs text-blue-700">Code sent to: {hint}</p>}
                {otpError && (
                  <div className="rounded-md bg-red-50 border border-red-200 p-2">
                    <p className="text-sm font-medium text-red-800">{otpError}</p>
                  </div>
                )}
                <div className="flex gap-2 justify-center" onPaste={handleOtpPaste}>
                  {otpDigits.map((digit, idx) => (
                    <input
                      key={idx}
                      ref={(el) => { otpRefs.current[idx] = el; }}
                      type="text"
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      maxLength={1}
                      value={digit}
                      onChange={(e) => handleOtpChange(idx, e.target.value)}
                      onKeyDown={(e) => handleOtpKeyDown(idx, e)}
                      className="h-12 w-10 rounded-lg border border-slate-300 bg-white text-center text-lg font-bold focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900"
                    />
                  ))}
                </div>
                <div className="flex items-center justify-between">
                  <button
                    type="button"
                    onClick={handleVerifyOtp}
                    disabled={otpVerifying}
                    className="px-4 py-2 text-sm font-bold text-white bg-blue-900 rounded-lg hover:bg-blue-800 disabled:opacity-50"
                  >
                    {otpVerifying ? 'Verifying...' : 'Verify Code'}
                  </button>
                  <button
                    type="button"
                    onClick={handleResendOtp}
                    disabled={resendCooldown > 0 || otpSending}
                    className="text-sm font-semibold text-blue-900 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {resendCooldown > 0 ? (
                      <span className="text-slate-500">Resend in {resendCooldown}s</span>
                    ) : (
                      <span className="hover:text-blue-800 underline underline-offset-2">Resend Code</span>
                    )}
                  </button>
                </div>
              </div>
            )}

            {/* Password */}
            {/* Password with strength meter */}
            <div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700">
                    Password {!isEdit && <span className="text-red-500">*</span>}
                  </label>
                  <input
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder={isEdit ? 'Leave blank to keep current' : 'Min 8 chars, mixed case + numbers + symbols'}
                    minLength={8}
                    required={!isEdit}
                  />
                  <InputError message={errors.password} className="mt-1" />
                </div>

                {/* Password Confirmation */}
                {!isEdit && (
                  <div>
                    <label className="block text-sm font-medium text-slate-700">Confirm Password <span className="text-red-500">*</span></label>
                    <input
                      type="password"
                      value={data.password_confirmation}
                      onChange={(e) => setData('password_confirmation', e.target.value)}
                      className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                      placeholder="Re-enter password"
                      minLength={8}
                      required
                    />
                    <InputError message={errors.password_confirmation} className="mt-1" />
                  </div>
                )}
              </div>

              {/* Password Strength Meter */}
              {data.password && <PasswordStrengthMeter password={data.password} />}
            </div>
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
                  <input type="hidden" value="AGENCY" />
                </>
              ) : (
                <>
                  <select
                    value={data.role}
                    onChange={(e) => {
                      setData('role', e.target.value);
                      if (e.target.value !== 'AGENCY') {
                        setData((prev) => ({ ...prev, role: e.target.value, agcy_id: '' }));
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

            {/* Active toggle */}
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

          {/* Section: Profile Details */}
          <fieldset className="space-y-4">
            <legend className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Profile Details</legend>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Position */}
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

              {/* Department */}
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
              {/* Contact Number */}
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

              {/* Office Location */}
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

            {/* Bio */}
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
                  : (isEdit ? 'Update User' : isNewUserViaSelectedAgency ? 'Add Focal Person' : 'Create User')
                }
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
