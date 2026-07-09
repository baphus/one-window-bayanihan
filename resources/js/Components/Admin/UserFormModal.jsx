import { useForm, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useMemo } from 'react';
import InputError from '@/Components/InputError';
import { userFormSchema, createUserFormSchema } from '@/Schemas/adminSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

const roleOptions = [
  { value: 'CASE_MANAGER', label: 'Case Manager' },
  { value: 'AGENCY', label: 'Agency Focal' },
  { value: 'ADMIN', label: 'System Admin' },
];

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
    is_active: user?.is_active ?? true,
    admin_password: '',
  });

  // Use a stricter schema for new users (password required) vs edit (optional)
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

        // Debug OTP auto-fill
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

    // Client-side password confirmation check for new users
    if (!isEdit && data.password !== data.password_confirmation) {
      setError('password_confirmation', 'Passwords do not match.');
      return;
    }

    if (isEdit) {
      patch(route('admin.users.update', user.id), {
        onSuccess: () => {
          onClose?.();
          router.reload({ only: ['users'] });
        },
        onError: () => {
          // Server errors will be shown inline — keep modal open
        },
      });
    } else {
      post(route('admin.users.store'), {
        onSuccess: () => {
          onClose?.();
          router.reload({ only: ['users'] });
        },
        onError: () => {
          // Server errors will be shown inline — keep modal open
        },
      });
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
          {/* Name */}
          <div>
            <label className="block text-sm font-medium text-slate-700">Name *</label>
            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required maxLength={255} />
            <InputError message={errors.name} className="mt-1" />
          </div>

          {/* Email */}
          <div>
            <label className="block text-sm font-medium text-slate-700">Email *</label>
            <div className="relative">
              <input
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-slate-100 disabled:text-slate-500"
                required
                disabled={otpVerified}
              />
              {otpVerified && (
                <span className="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                  Verified
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
              <p className="text-sm font-medium text-emerald-800">&#10003; Email updated successfully. The user will receive a verification link at the new address.</p>
            </div>
          )}

          {/* Email change OTP flow */}
          {isEdit && emailChanged && !otpStep && !otpVerified && (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-3 space-y-3">
              <p className="text-sm font-medium text-amber-900">Email Change Requires Verification</p>
              <p className="text-xs text-amber-700">Enter your admin password and send a verification code to the new email.</p>
              <div>
                <label className="block text-sm font-medium text-amber-900">Your Admin Password</label>
                <input
                  type="password"
                  value={data.admin_password}
                  onChange={(e) => setData('admin_password', e.target.value)}
                  className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                  autoComplete="current-password"
                />
                <InputError message={errors.admin_password} className="mt-1" />
              </div>
              <button
                type="button"
                onClick={handleSendOtp}
                disabled={otpSending}
                className="w-full px-4 py-2 text-sm font-bold text-white bg-blue-900 rounded-md hover:bg-blue-800 disabled:opacity-50"
              >
                {otpSending ? 'Sending...' : 'Send Verification Code'}
              </button>
            </div>
          )}

          {/* OTP input step */}
          {isEdit && otpStep && !otpVerified && (
            <div className="rounded-md border border-blue-200 bg-blue-50 p-3 space-y-3">
              <p className="text-sm font-medium text-blue-900">Enter Verification Code</p>
              {hint && (
                <p className="text-xs text-blue-700">Code sent to: {hint}</p>
              )}

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
                    className="h-12 w-10 rounded-md border border-slate-300 bg-white text-center text-lg font-bold focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900"
                  />
                ))}
              </div>

              <div className="flex items-center justify-between">
                <button
                  type="button"
                  onClick={handleVerifyOtp}
                  disabled={otpVerifying}
                  className="px-4 py-2 text-sm font-bold text-white bg-blue-900 rounded-md hover:bg-blue-800 disabled:opacity-50"
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
          <div>
            <label className="block text-sm font-medium text-slate-700">Password {isEdit ? '(leave blank to keep current)' : '*'}</label>
            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" minLength={8} required={!isEdit} />
            <InputError message={errors.password} className="mt-1" />
          </div>

          {/* Password Confirmation (only for new users) */}
          {!isEdit && (
            <div>
              <label className="block text-sm font-medium text-slate-700">Confirm Password *</label>
              <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" minLength={8} required />
              <InputError message={errors.password_confirmation} className="mt-1" />
            </div>
          )}

          {/* Role */}
          <div>
            <label className="block text-sm font-medium text-slate-700">Role *</label>
            <select value={data.role} onChange={(e) => setData('role', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
              {roleOptions.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
            <InputError message={errors.role} className="mt-1" />
          </div>

          {/* Agency */}
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

          {/* Contact Number */}
          <div>
            <label className="block text-sm font-medium text-slate-700">Contact Number</label>
            <input type="text" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <InputError message={errors.contact_number} className="mt-1" />
          </div>

          {/* Active toggle */}
          {isEdit && (
            <div className="flex items-center gap-2">
              <input type="checkbox" id="is_active" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-slate-300" />
              <label htmlFor="is_active" className="text-sm text-slate-700">Active</label>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 disabled:opacity-50">
              {isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
