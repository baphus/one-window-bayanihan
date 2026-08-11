import { useState, useCallback, useEffect, useMemo, Fragment } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TurnstileWidget from '@/Components/TurnstileWidget';
import ChatBot from '@/Components/ChatBot';
import AddressDropdowns from '@/Components/AddressDropdowns';
import PhoneInput from '@/Components/PhoneInput';
import CountrySelect from '@/Components/CountrySelect';
import SearchableSelect from '@/Components/SearchableSelect';
import { DEFAULT_OCCUPATIONS } from '@/data/defaultOccupations';

const STEPS = [
  { id: 'email', label: 'Email Verification' },
  { id: 'personal', label: 'Personal Information' },
  { id: 'address', label: 'Address' },
  { id: 'employment', label: 'Employment' },
  { id: 'nok', label: 'Next of Kin' },
  { id: 'submit', label: 'Submit Request' },
];

const STORAGE_KEY = 'ofw_intake_form_data';

function saveToSession(data) {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch (e) { /* quota exceeded — ignore */ }
}

function loadFromSession() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (e) { return null; }
}

function clearSession() {
  sessionStorage.removeItem(STORAGE_KEY);
}

function emptyForm() {
  return {
    email: '',
    otp: '',
    client: { first_name: '', last_name: '', middle_initial: '', suffix: '', date_of_birth: '', sex: '', contact_number: '' },
    address: { region: '0700000000', province: '', city_municipality: '', barangay: '', street: '' },
    employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', is_present: false, last_country: '', last_position: '', date_of_arrival: '' },
    vulnerability: [],
    next_of_kin: [{ first_name: '', last_name: '', middle_initial: '', relationship: '', phone_number: '', email: '', is_primary: true, region: '', province: '', city_municipality: '', barangay: '', street: '' }],
    summary: '',
    consent: false,
  };
}

// Pre-fill the wizard with the signed-in OFW's linked client profile when the
// backend passes one. The identity fields are locked once pre-filled — the
// profile is authoritative for the filer's identity — while address,
// employment, contact and next-of-kin stay editable so a returning OFW does
// not have to retype everything.
function formWithExistingClient(existingClient) {
  const base = emptyForm();
  if (!existingClient) return base;

  const clientFields = ['first_name', 'last_name', 'middle_initial', 'suffix', 'date_of_birth', 'sex', 'contact_number'];
  const next = {
    ...base,
    client: { ...base.client },
  };

  clientFields.forEach((key) => {
    if (existingClient[key] != null) next.client[key] = existingClient[key];
  });

  if (existingClient.email) next.email = existingClient.email;

  // Merge per-section, keeping base defaults for any key the profile leaves
  // null/empty (e.g. an address level that could not be resolved to a code).
  if (existingClient.address) {
    Object.entries(existingClient.address).forEach(([key, value]) => {
      if (value != null && value !== '') next.address[key] = value;
    });
  }
  if (existingClient.employment) {
    Object.entries(existingClient.employment).forEach(([key, value]) => {
      next.employment[key] = value ?? '';
    });
  }
  if (existingClient.next_of_kin?.length) {
    next.next_of_kin = existingClient.next_of_kin.map((nok) => {
      const merged = { ...base.next_of_kin[0], ...nok };
      Object.keys(merged).forEach((key) => {
        if (merged[key] == null) merged[key] = '';
      });
      return merged;
    });
  }

  return next;
}

/**
 * POST JSON and report the status separately from the body.
 *
 * `Accept: application/json` matters: without it Laravel renders throttle (429),
 * expired-session (419) and unhandled (500) responses as full HTML error pages.
 * The body is then parsed defensively anyway, because a proxy or the dev server
 * can still return HTML. Reading `res.json()` before checking `res.status` — as
 * this file used to — makes any such response throw, so every one of them
 * surfaced as the same "Server error" and the status-specific branches in the
 * callers were unreachable.
 */
async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    },
    body: JSON.stringify(body),
  });

  let json = null;
  try {
    json = await res.json();
  } catch (e) {
    json = null;
  }

  return { res, json };
}

export default function IntakeIndex({ occupationOptions, existingClient, skipVerification = false }) {
  const { turnstile } = usePage().props;

  // Signed-in OFWs skip the email+OTP verification step entirely — their
  // session already proved email ownership at login.
  const steps = useMemo(
    () => (skipVerification ? STEPS.filter((s) => s.id !== 'email') : STEPS),
    [skipVerification],
  );

  const [currentStep, setCurrentStep] = useState(0);
  const [emailVerified, setEmailVerified] = useState(skipVerification);
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState({});
  const [turnstileToken, setTurnstileToken] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [otpHint, setOtpHint] = useState('');
  const [debugOtp, setDebugOtp] = useState(null);
  const [duplicateMessage, setDuplicateMessage] = useState('');
  const [hasExistingAccount, setHasExistingAccount] = useState(false);
  const [submitSuccess, setSubmitSuccess] = useState(false);
  const [submittedCase, setSubmittedCase] = useState(null);

  const [formData, setFormData] = useState(() => {
    const saved = loadFromSession();
    return saved || formWithExistingClient(existingClient);
  });

  // When the wizard is pre-filled from a linked OFW profile, the personal
  // identity fields become read-only to keep the filer's record authoritative.
  const identityLocked = !!existingClient;

  // Save to session on every formData change (after email verified)
  useEffect(() => {
    if (emailVerified) {
      saveToSession(formData);
    }
  }, [formData, emailVerified]);

  const updateField = useCallback((path, value) => {
    setFormData(prev => {
      const keys = path.split('.');
      const next = JSON.parse(JSON.stringify(prev));
      let target = next;
      for (let i = 0; i < keys.length - 1; i++) {
        target = target[keys[i]];
      }
      target[keys[keys.length - 1]] = value;
      return next;
    });
  }, []);

  const goNext = () => {
    window.scrollTo(0, 0);
    setCurrentStep(s => Math.min(s + 1, steps.length - 1));
  };
  const goBack = () => setCurrentStep(s => Math.max(s - 1, 0));

  // --- Email & OTP handlers ---
  const handleSendOtp = async () => {
    if (!formData.email) {
      setErrors({ email: 'Please enter your email address.' });
      return;
    }
    setProcessing(true);
    setErrors({});
    try {
      const { res, json } = await postJson(route('intake.verify-email'), {
        email: formData.email,
        cf_turnstile_response: turnstileToken,
      });
      if (res.ok && json?.sent) {
        setOtpSent(true);
        setOtpHint(json.hint);
        setDebugOtp(json.debug_otp);
      } else if (res.status === 429) {
        setErrors({ email: 'Too many verification codes requested. Please wait a minute and try again.' });
      } else {
        setErrors({ email: json?.error || json?.errors?.email?.[0] || json?.message || 'Failed to send verification code.' });
      }
    } catch (e) {
      setErrors({ email: 'Network error. Please try again.' });
    }
    setProcessing(false);
  };

  const handleVerifyOtp = async () => {
    if (!formData.otp || formData.otp.length !== 6) {
      setErrors({ otp: 'Please enter the 6-digit code.' });
      return;
    }
    setProcessing(true);
    setErrors({});
    try {
      const { res, json } = await postJson(route('intake.check-duplicate'), {
        email: formData.email,
        otp: formData.otp,
      });
      if (res.status === 429) {
        setErrors({ otp: 'Too many attempts. Please wait a minute and try again.' });
      } else if (!res.ok || !json) {
        // Handle 422 from either OTP failure ({ error: '...' }) or Laravel validation ({ errors: { ... } })
        const msg = json?.error
          || (json?.errors?.otp?.[0])
          || (json?.errors?.email?.[0])
          || (json?.message)
          || 'Invalid or expired OTP.';
        setErrors({ otp: msg });
      } else if (json?.duplicate) {
        setDuplicateMessage(json.message);
      } else {
        setEmailVerified(true);
        // Track whether this is a returning OFW (existing account)
        if (json.existing_client) {
          setHasExistingAccount(true);
          setFormData(prev => ({
            ...prev,
            client: { ...prev.client, ...json.existing_client },
            address: json.existing_client.address ? { ...prev.address, ...json.existing_client.address } : prev.address,
            employment: json.existing_client.employment ? { ...prev.employment, ...json.existing_client.employment } : prev.employment,
            next_of_kin: json.existing_client.next_of_kin?.length ? json.existing_client.next_of_kin : prev.next_of_kin,
          }));
        }
        goNext();
      }
    } catch (e) {
      setErrors({ otp: 'Network error. Please try again.' });
    }
    setProcessing(false);
  };

  // --- Final submission ---
  const handleSubmit = async () => {
    setProcessing(true);
    setErrors({});
    try {
      const { res, json } = await postJson(route('intake.submit'), formData);
      if (res.ok && json?.success) {
        clearSession();
        setSubmittedCase({
          caseNumber: json.case_number,
          trackerNumber: json.tracker_number,
          email: json.email,
        });
        setSubmitSuccess(true);
      } else if (res.status === 422) {
        // Validation errors — merge with existing but keep submit-level
        const validationErrors = json?.errors || {};
        setErrors(prev => ({ ...prev, ...validationErrors }));
        if (!validationErrors.submit) {
          // Collect first visible field error as submit-level message
          const firstError = Object.values(validationErrors).flat().find(Boolean);
          setErrors(prev => ({ ...prev, submit: firstError || json?.error || 'Please check the form fields and try again.' }));
        }
      } else if (res.status === 429) {
        setErrors({ submit: 'Too many attempts. Please wait a minute and try again.' });
      } else if (res.status === 419) {
        setErrors({ submit: 'Your session expired. Please refresh the page and verify your email again.' });
      } else {
        setErrors({ submit: json?.error || json?.message || 'Submission failed. Please try again.' });
      }
    } catch (e) {
      // Only a genuine transport failure reaches here now — postJson swallows
      // unparseable bodies so the status-specific branches above still run.
      setErrors({ submit: 'Network error. Please check your connection and try again.' });
    }
    setProcessing(false);
  };

  // If submitted successfully, redirect to success page
  if (submitSuccess) {
    return <IntakeSuccess caseNumber={submittedCase?.caseNumber} trackerNumber={submittedCase?.trackerNumber} email={submittedCase?.email} />;
  }

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="File a Case — OFW Self-Filing" />
      <AppHeader />
      <ChatBot />

      <main className="flex-1 pt-20">
        {/* Hero */}
        <section className="relative flex min-h-[200px] w-full items-center justify-center overflow-hidden bg-primary">
          <div className="absolute inset-0 bg-gradient-to-br from-primary via-primary/90 to-primary-container/30" />
          <div className="relative z-10 mx-auto w-full max-w-4xl px-4 py-12 text-center md:px-8">
            <h1 className="mb-3 font-headline text-2xl font-extrabold text-white sm:text-3xl md:text-4xl">
              File a Case
            </h1>
            <p className="mx-auto max-w-2xl text-sm leading-relaxed text-white/80 md:text-base">
              Submit your assistance request online. A Case Manager will review your information and get back to you.
            </p>
          </div>
        </section>

        {/* Progress indicator */}
<div className="mx-auto max-w-4xl px-4 py-8 md:px-8">
  <div className="relative">
    {(() => {
      const totalSteps = STEPS.length;
      const completedSteps = skipVerification ? currentStep + 1 : currentStep;
      const edgeInset = 50 / totalSteps;
      const trackWidth = 100 - edgeInset * 2;
      const progressWidth = totalSteps > 1
        ? Math.min(completedSteps / (totalSteps - 1), 1) * trackWidth
        : 0;

      return (
        <>
          <div
            className="absolute h-0.5 bg-slate-200"
            style={{ top: '15px', left: `${edgeInset}%`, right: `${edgeInset}%` }}
          />
          <div
            className="absolute h-0.5 bg-primary transition-all duration-300 ease-out"
            style={{ top: '15px', left: `${edgeInset}%`, width: `${progressWidth}%` }}
          />

          <div className="relative flex">
            {STEPS.map((step, i) => {
              const isDone = i < completedSteps;
              const isActive = i === completedSteps;

              return (
                <div key={step.id} className="flex flex-1 flex-col items-center gap-1.5 px-1">
                  <div
                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors ${
                      isDone
                        ? 'bg-primary text-white'
                        : isActive
                        ? 'bg-primary text-white ring-4 ring-primary/20'
                        : 'bg-slate-200 text-slate-500'
                    }`}
                  >
                    {isDone ? (
                      <span className="material-symbols-outlined text-[16px]">check</span>
                    ) : (
                      i + 1
                    )}
                  </div>
                  <span
                    className={`hidden text-center text-[10px] font-medium leading-tight sm:block ${
                      isActive ? 'text-primary' : 'text-slate-500'
                    }`}
                  >
                    {step.label}
                  </span>
                </div>
              );
            })}
          </div>
        </>
      );
    })()}
  </div>
</div>

        {/* Form content */}
        <div className="mx-auto max-w-3xl px-4 pb-16 md:px-8">
          <div className="rounded-lg border border-outline-variant bg-white p-6 shadow-sm md:p-8">
            {{
              email: (
                <EmailStep
                  formData={formData}
                  updateField={updateField}
                  errors={errors}
                  processing={processing}
                  otpSent={otpSent}
                  otpHint={otpHint}
                  debugOtp={debugOtp}
                  duplicateMessage={duplicateMessage}
                  turnstile={turnstile}
                  turnstileToken={turnstileToken}
                  setTurnstileToken={setTurnstileToken}
                  onSendOtp={handleSendOtp}
                  onVerifyOtp={handleVerifyOtp}
                  onBackToEmail={() => setOtpSent(false)}
                />
              ),
              personal: (
                <PersonalStep formData={formData} updateField={updateField} errors={errors} identityLocked={identityLocked} onNext={goNext} onBack={goBack} />
              ),
              address: (
                <AddressStep formData={formData} updateField={updateField} errors={errors} onNext={goNext} onBack={goBack} />
              ),
              employment: (
                <EmploymentStep formData={formData} updateField={updateField} errors={errors} occupationOptions={occupationOptions} onNext={goNext} onBack={goBack} />
              ),
              nok: (
                <NokStep formData={formData} setFormData={setFormData} errors={errors} onNext={goNext} onBack={goBack} />
              ),
              submit: (
                <SubmitReviewStep formData={formData} updateField={updateField} errors={errors} processing={processing} onSubmit={handleSubmit} onBack={goBack} />
              ),
            }[steps[currentStep].id]}
          </div>
        </div>
      </main>

      <AppFooter />
    </div>
  );
}


// --- Step Components ---

function EmailStep({ formData, updateField, errors, processing, otpSent, otpHint, debugOtp, duplicateMessage, turnstile, turnstileToken, setTurnstileToken, onSendOtp, onVerifyOtp, onBackToEmail }) {
  if (duplicateMessage) {
    return (
      <div className="text-center">
        <span className="material-symbols-outlined mb-4 block text-4xl text-amber-500">info</span>
        <h2 className="mb-2 text-lg font-bold text-slate-900">Active Case Found</h2>
        <p className="mb-6 text-sm text-slate-600">{duplicateMessage}</p>
        <a href={route('track.index')} className="inline-flex items-center gap-2 rounded bg-primary px-6 py-3 text-sm font-bold text-white hover:brightness-110">
          <span className="material-symbols-outlined text-[18px]">search</span>
          Go to Tracking Portal
        </a>
      </div>
    );
  }

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Verify Your Email</h2>
      <p className="mb-6 text-sm text-slate-500">
        We'll send a 6-digit verification code to confirm your identity.
      </p>

      {!otpSent ? (
        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Email Address</label>
            <input
              type="email"
              value={formData.email}
              onChange={e => updateField('email', e.target.value)}
              placeholder="your.email@example.com"
              className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none"
            />
            {errors.email && <p className="mt-1 text-xs text-error">{errors.email}</p>}
          </div>

          <TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} />

          <button
            type="button"
            onClick={onSendOtp}
            disabled={processing || (turnstile?.enabled && !turnstileToken)}
            className="w-full bg-primary px-6 py-3 text-sm font-bold text-white hover:brightness-110 disabled:opacity-50"
          >
            {processing ? 'Sending...' : 'Send Verification Code'}
          </button>
        </div>
      ) : (
        <div className="space-y-4">
          <p className="text-sm text-slate-600">
            A verification code has been sent to <strong>{otpHint}</strong>
          </p>
          {debugOtp && (
            <div className="rounded bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
              Debug OTP: <strong>{debugOtp}</strong>
            </div>
          )}
          <div>
            <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Verification Code</label>
            <input
              type="text"
              value={formData.otp}
              onChange={e => updateField('otp', e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="000000"
              maxLength={6}
              className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-center text-lg font-mono tracking-[0.5em] focus:border-primary focus:outline-none"
            />
            {errors.otp && <p className="mt-1 text-xs text-error">{errors.otp}</p>}
          </div>

          <button
            type="button"
            onClick={onVerifyOtp}
            disabled={processing}
            className="w-full bg-primary px-6 py-3 text-sm font-bold text-white hover:brightness-110 disabled:opacity-50"
          >
            {processing ? 'Verifying...' : 'Verify & Continue'}
          </button>

          <div className="flex items-center justify-center gap-4 text-xs text-slate-500">
            <button type="button" onClick={onSendOtp} disabled={processing} className="text-primary hover:underline disabled:opacity-50">
              Resend code
            </button>
            <span aria-hidden>&middot;</span>
            <button type="button" onClick={() => { updateField('otp', ''); setTurnstileToken(''); onBackToEmail?.(); }} className="text-slate-500 hover:text-primary hover:underline">
              Use a different email
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function PersonalStep({ formData, updateField, errors, identityLocked = false, onNext, onBack }) {
  const [stepErrors, setStepErrors] = useState({});

  const validate = () => {
    const errs = {};
    if (!formData.client.first_name.trim()) errs.first_name = 'Please provide your first name.';
    if (!formData.client.last_name.trim()) errs.last_name = 'Please provide your last name.';
    setStepErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const clearError = (key) => setStepErrors(prev => ({ ...prev, [key]: undefined }));

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Personal Information</h2>
      <p className="mb-6 text-sm text-slate-500">
        {identityLocked ? 'Some details came from your profile and cannot be changed here.' : 'Tell us about yourself.'}
      </p>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">First Name *</label>
          <input type="text" value={formData.client.first_name} disabled={identityLocked} title={identityLocked ? 'Locked — from your profile' : undefined} onChange={e => { updateField('client.first_name', e.target.value); clearError('first_name'); }}
            className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${stepErrors.first_name ? 'border-error' : 'border-outline-variant focus:border-primary'} ${identityLocked ? 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed' : ''}`} />
          {stepErrors.first_name && <p className="mt-1 text-xs text-error">{stepErrors.first_name}</p>}
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Last Name *</label>
          <input type="text" value={formData.client.last_name} disabled={identityLocked} title={identityLocked ? 'Locked — from your profile' : undefined} onChange={e => { updateField('client.last_name', e.target.value); clearError('last_name'); }}
            className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${stepErrors.last_name ? 'border-error' : 'border-outline-variant focus:border-primary'} ${identityLocked ? 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed' : ''}`} />
          {stepErrors.last_name && <p className="mt-1 text-xs text-error">{stepErrors.last_name}</p>}
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Middle Initial</label>
          <input type="text" maxLength={1} value={formData.client.middle_initial} disabled={identityLocked} title={identityLocked ? 'Locked — from your profile' : undefined} onChange={e => updateField('client.middle_initial', e.target.value.toUpperCase())}
            className={`w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm uppercase focus:border-primary focus:outline-none ${identityLocked ? 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed' : ''}`} />
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Suffix</label>
          <select value={formData.client.suffix} disabled={identityLocked} onChange={e => updateField('client.suffix', e.target.value)}
            className={`w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none ${identityLocked ? 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed' : ''}`}>
            <option value="">None</option>
            <option value="Jr">Jr</option>
            <option value="Sr">Sr</option>
            <option value="II">II</option>
            <option value="III">III</option>
            <option value="IV">IV</option>
            <option value="V">V</option>
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Date of Birth</label>
          <input type="date" value={formData.client.date_of_birth} disabled={identityLocked} title={identityLocked ? 'Locked — from your profile' : undefined} onChange={e => updateField('client.date_of_birth', e.target.value)}
            className={`w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none ${identityLocked ? 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed' : ''}`} />
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Sex *</label>
          {/* Without an empty first option the browser renders "Male" as
              selected while state is still empty, so the filer sees an
              answered control and submits nothing. Case managers cannot
              publish a case with no sex, so the submission dead-ends. */}
          <select value={formData.client.sex ?? ''} disabled={identityLocked} onChange={e => updateField('client.sex', e.target.value)}
            className={`w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none ${identityLocked ? 'disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed' : ''}`}>
            <option value="">Select…</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div className="sm:col-span-2">
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Contact Number</label>
          <PhoneInput value={formData.client.contact_number} onChange={val => updateField('client.contact_number', val)} />
        </div>
      </div>

      <div className="mt-8 flex justify-between">
        <button type="button" onClick={onBack} className="px-6 py-3 text-sm font-medium text-slate-600 hover:text-primary">Back</button>
        <button type="button" onClick={() => validate() && onNext()} className="bg-primary px-8 py-3 text-sm font-bold text-white hover:brightness-110">Continue</button>
      </div>
    </div>
  );
}

function AddressStep({ formData, updateField, errors, onNext, onBack }) {
  const handleAddressChange = (field, value) => {
    if (typeof field === 'object') {
      // Batch update from AddressDropdowns cascade
      Object.entries(field).forEach(([key, val]) => updateField('address.' + key, val));
    } else {
      updateField('address.' + field, value);
    }
  };

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Home Address</h2>
      <p className="mb-6 text-sm text-slate-500">Your current address in the Philippines.</p>

      <AddressDropdowns
        values={formData.address}
        onChange={handleAddressChange}
        errors={{
          region: errors['address.region'],
          province: errors['address.province'],
          city_municipality: errors['address.city_municipality'],
          barangay: errors['address.barangay'],
        }}
      />

      <div className="mt-8 flex justify-between">
        <button type="button" onClick={onBack} className="px-6 py-3 text-sm font-medium text-slate-600 hover:text-primary">Back</button>
        <button type="button" onClick={onNext} className="bg-primary px-8 py-3 text-sm font-bold text-white hover:brightness-110">Continue</button>
      </div>
    </div>
  );
}

function EmploymentStep({ formData, updateField, errors, occupationOptions, onNext, onBack }) {
  // Merge curated defaults with previously entered occupations from the backend,
  // de-duplicate, sort, and shape as [value, label] pairs for SearchableSelect.
  const mergedOccupationOptions = useMemo(() => {
    const labels = [...DEFAULT_OCCUPATIONS, ...(occupationOptions || []).map(o => o.label ?? o)]
      .map(p => String(p).trim())
      .filter(Boolean);
    const unique = [...new Set(labels)].sort((a, b) => a.localeCompare(b));
    return unique.map(p => ({ value: p, label: p }));
  }, [occupationOptions]);

  const handleEmploymentPresentChange = (checked) => {
    updateField('employment.is_present', checked);
    if (checked) {
      updateField('employment.end_date', '');
    }
  };

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Employment Details</h2>
      <p className="mb-6 text-sm text-slate-500">Information about your overseas work.</p>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Employer Name</label>
          <input type="text" value={formData.employment.employer_name} onChange={e => updateField('employment.employer_name', e.target.value)}
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Last Country of Work</label>
          <CountrySelect value={formData.employment.last_country} onChange={v => updateField('employment.last_country', v)} placeholder="Select country..." />
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Last Occupation</label>
          <SearchableSelect value={formData.employment.last_position} onChange={v => updateField('employment.last_position', v)} options={mergedOccupationOptions} placeholder="Select or type occupation..." allowCustom />
        </div>
        <div className="sm:col-span-2">
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Employment Period</label>
          <div className="flex items-center gap-2">
            <input
              type="date"
              value={formData.employment.start_date}
              onChange={e => updateField('employment.start_date', e.target.value)}
              className="h-10 flex-1 min-w-0 rounded border border-outline-variant bg-surface-container px-3 text-sm text-on-surface outline-none focus:border-primary focus:ring-1 focus:ring-primary"
            />
            <span className="text-xs font-bold text-slate-400 shrink-0">to</span>
            {formData.employment.is_present ? (
              <span className="h-10 flex-1 min-w-0 rounded border border-outline-variant bg-surface-container/50 px-3 flex items-center text-sm font-medium text-emerald-700">
                Present
              </span>
            ) : (
              <input
                type="date"
                value={formData.employment.end_date}
                onChange={e => updateField('employment.end_date', e.target.value)}
                min={formData.employment.start_date || undefined}
                className="h-10 flex-1 min-w-0 rounded border border-outline-variant bg-surface-container px-3 text-sm text-on-surface outline-none focus:border-primary focus:ring-1 focus:ring-primary"
              />
            )}
          </div>
          <label className="mt-2 inline-flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={!!formData.employment.is_present}
              onChange={e => handleEmploymentPresentChange(e.target.checked)}
              className="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary"
            />
            <span className="text-xs text-slate-600">Presently employed</span>
          </label>
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Date of Arrival in PH</label>
          <input type="date" value={formData.employment.date_of_arrival} onChange={e => updateField('employment.date_of_arrival', e.target.value)}
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none" />
        </div>
      </div>

      {/* ── Vulnerability Indicators ───────────────────────────── */}
      <div className="mt-6 border-t border-outline-variant pt-6">
        <h3 className="mb-1 text-sm font-bold text-slate-800">Vulnerability Indicators</h3>
        <p className="mb-3 text-xs text-slate-500">Select any that apply to you.</p>
        <div className="flex flex-wrap gap-4">
          {['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'].map((v) => {
            const checked = (formData.vulnerability || []).includes(v);
            return (
              <label key={v} className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={() => {
                    const current = formData.vulnerability || [];
                    updateField('vulnerability',
                      checked ? current.filter((x) => x !== v) : [...current, v]
                    );
                  }}
                  className="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary"
                />
                <span className="text-sm text-slate-700">{v}</span>
              </label>
            );
          })}
        </div>
      </div>

      <div className="mt-8 flex justify-between">
        <button type="button" onClick={onBack} className="px-6 py-3 text-sm font-medium text-slate-600 hover:text-primary">Back</button>
        <button type="button" onClick={onNext} className="bg-primary px-8 py-3 text-sm font-bold text-white hover:brightness-110">Continue</button>
      </div>
    </div>
  );
}


function NokStep({ formData, setFormData, errors, onNext, onBack }) {
  const [stepErrors, setStepErrors] = useState({});

  const addNok = () => {
    setFormData(prev => ({
      ...prev,
      next_of_kin: [...prev.next_of_kin, { first_name: '', last_name: '', middle_initial: '', relationship: '', phone_number: '', email: '', is_primary: false, region: '', province: '', city_municipality: '', barangay: '', street: '' }],
    }));
  };

  const updateNok = (index, field, value) => {
    setFormData(prev => {
      const nok = [...prev.next_of_kin];
      nok[index] = { ...nok[index], [field]: value };
      return { ...prev, next_of_kin: nok };
    });
    if (index === 0 && field === 'first_name') {
      setStepErrors(prev => ({ ...prev, nok0_first_name: undefined }));
    }
  };

  const setPrimaryNok = (idx) => {
    setFormData(prev => {
      const nok = prev.next_of_kin.map((n, i) => ({ ...n, is_primary: i === idx }));
      return { ...prev, next_of_kin: nok };
    });
  };

  const handleNokAddressChange = (idx, fieldOrObject, value) => {
    if (typeof fieldOrObject === 'object') {
      // Batch update from AddressDropdowns cascade
      setFormData(prev => {
        const nok = [...prev.next_of_kin];
        nok[idx] = { ...nok[idx], ...fieldOrObject };
        return { ...prev, next_of_kin: nok };
      });
    } else {
      updateNok(idx, fieldOrObject, value);
    }
  };

  const removeNok = (index) => {
    if (formData.next_of_kin.length <= 1) return;
    setFormData(prev => ({
      ...prev,
      next_of_kin: prev.next_of_kin.filter((_, i) => i !== index),
    }));
  };

  const validate = () => {
    const errs = {};
    if (!formData.next_of_kin[0]?.first_name?.trim()) errs.nok0_first_name = 'Emergency contact name is required.';
    setStepErrors(errs);
    return Object.keys(errs).length === 0;
  };

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Emergency Contact (Next of Kin)</h2>
      <p className="mb-6 text-sm text-slate-500">Provide at least one emergency contact person.</p>

      {formData.next_of_kin.map((nok, i) => (
        <div key={i} className="mb-6 rounded border border-outline-variant p-4">
          <div className="mb-3 flex items-center justify-between">
            <span className="text-xs font-bold uppercase text-slate-500">Contact #{i + 1}</span>
            <div className="flex items-center gap-3">
              <label className="flex cursor-pointer items-center gap-1.5 text-xs font-bold text-slate-600">
                <input
                  type="radio"
                  name="primary-nok"
                  checked={nok.is_primary}
                  onChange={() => setPrimaryNok(i)}
                  className="h-3.5 w-3.5 border-outline-variant text-primary focus:ring-primary"
                />
                Primary
              </label>
              {formData.next_of_kin.length > 1 && (
                <button type="button" onClick={() => removeNok(i)} className="text-xs text-error hover:underline">Remove</button>
              )}
            </div>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">First Name *</label>
              <input type="text" value={nok.first_name} onChange={e => updateNok(i, 'first_name', e.target.value)}
                className={`w-full border bg-surface-container px-3 py-2 text-sm focus:outline-none ${i === 0 && stepErrors.nok0_first_name ? 'border-error' : 'border-outline-variant focus:border-primary'}`} />
              {i === 0 && stepErrors.nok0_first_name && <p className="mt-1 text-xs text-error">{stepErrors.nok0_first_name}</p>}
            </div>
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Middle Initial</label>
              <input type="text" value={nok.middle_initial} onChange={e => updateNok(i, 'middle_initial', e.target.value.toUpperCase())} maxLength={1}
                className="w-full border border-outline-variant bg-surface-container px-3 py-2 text-sm uppercase focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Last Name</label>
              <input type="text" value={nok.last_name} onChange={e => updateNok(i, 'last_name', e.target.value)}
                className="w-full border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Relationship</label>
              <select value={nok.relationship} onChange={e => updateNok(i, 'relationship', e.target.value)}
                className="w-full border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="">Select relationship...</option>
                <option value="Mother">Mother</option>
                <option value="Father">Father</option>
                <option value="Spouse">Spouse</option>
                <option value="Sibling">Sibling</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Phone Number</label>
              <PhoneInput value={nok.phone_number} onChange={val => updateNok(i, 'phone_number', val)} placeholder="Phone number" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Email</label>
              <input type="email" value={nok.email} onChange={e => updateNok(i, 'email', e.target.value)}
                className="w-full border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div className="sm:col-span-2">
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Address</label>
              <button
                type="button"
                onClick={() => {
                  const addr = formData.address;
                  updateNok(i, 'region', addr.region);
                  updateNok(i, 'province', addr.province);
                  updateNok(i, 'city_municipality', addr.city_municipality);
                  updateNok(i, 'barangay', addr.barangay);
                  updateNok(i, 'street', addr.street);
                }}
                className="mb-2 inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
              >
                <span className="material-symbols-outlined text-[14px]">content_copy</span>
                Same as client address
              </button>
              <AddressDropdowns
                values={{ region: nok.region, province: nok.province, city_municipality: nok.city_municipality, barangay: nok.barangay, street: nok.street }}
                onChange={(field, value) => handleNokAddressChange(i, field, value)}
              />
            </div>
          </div>
        </div>
      ))}

      <button type="button" onClick={addNok} className="mb-6 text-sm font-medium text-primary hover:underline">+ Add another contact</button>

      <div className="mt-4 flex justify-between">
        <button type="button" onClick={onBack} className="px-6 py-3 text-sm font-medium text-slate-600 hover:text-primary">Back</button>
        <button type="button" onClick={() => validate() && onNext()} className="bg-primary px-8 py-3 text-sm font-bold text-white hover:brightness-110">Continue</button>
      </div>
    </div>
  );
}

function SubmitReviewStep({ formData, updateField, errors, processing, onSubmit, onBack }) {
  const [stepErrors, setStepErrors] = useState({});

  const validate = () => {
    const errs = {};
    if (formData.summary && formData.summary.trim().length > 0 && formData.summary.trim().length < 20) {
      errs.summary = 'If provided, summary must be at least 20 characters.';
    }
    if (!formData.consent) errs.consent = 'You must consent to data processing to submit this form.';
    setStepErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = () => {
    if (validate()) onSubmit();
  };

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Review & Submit</h2>
      <p className="mb-6 text-sm text-slate-500">Provide a brief description of your situation, then give consent to submit.</p>

      <div className="space-y-6">
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Tell us what happened (optional)</label>
          <p className="mb-2 text-xs text-slate-500">
            Briefly describe your situation. A Case Manager will follow up for more details.
          </p>
          <textarea
            value={formData.summary}
            onChange={e => { updateField('summary', e.target.value); setStepErrors(prev => ({ ...prev, summary: undefined })); }}
            rows={4}
            placeholder="Describe your situation briefly, or leave blank to discuss with a Case Manager..."
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none"
          />
          {formData.summary && formData.summary.length > 0 && formData.summary.length < 20 && (
            <p className="mt-1 text-xs text-slate-400">Minimum 20 characters if providing a summary.</p>
          )}
          {(stepErrors.summary || errors.summary) && <p className="mt-1 text-xs text-error">{stepErrors.summary || errors.summary}</p>}
        </div>

        <div className="rounded border border-outline-variant bg-slate-50 p-4">
          <h3 className="mb-2 text-sm font-bold text-slate-700">Data Processing Consent</h3>
          <p className="mb-4 text-xs leading-relaxed text-slate-600">
            I consent to the collection, processing, and storage of my personal information by the Department of Migrant Workers (DMW) Region VII
            for the purpose of case management and inter-agency referral coordination. I understand that my data will be shared with relevant
            government partner agencies as necessary for my case resolution, in compliance with the Data Privacy Act of 2012 (RA 10173).
          </p>
          <label className="flex cursor-pointer items-center gap-3">
            <input type="checkbox" checked={formData.consent} onChange={e => { updateField('consent', e.target.checked); setStepErrors(prev => ({ ...prev, consent: undefined })); }}
              className="h-4 w-4 border-outline-variant text-primary focus:ring-primary" />
            <span className="text-sm font-medium text-slate-700">I agree to the data processing terms above *</span>
          </label>
          {stepErrors.consent && <p className="mt-1 text-xs text-error">{stepErrors.consent}</p>}
        </div>

        {errors.submit && (
          <div className="rounded bg-error-container p-3 text-xs font-medium text-on-error-container">{errors.submit}</div>
        )}
      </div>

      <div className="mt-8 flex justify-between">
        <button type="button" onClick={onBack} className="px-6 py-3 text-sm font-medium text-slate-600 hover:text-primary">Back</button>
        <button
          type="button"
          onClick={handleSubmit}
          disabled={processing}
          className="bg-primary px-8 py-3 text-sm font-bold text-white hover:brightness-110 disabled:opacity-50"
        >
          {processing ? 'Submitting...' : 'Submit Request'}
        </button>
      </div>
    </div>
  );
}

function IntakeSuccess({ caseNumber, trackerNumber, email }) {
  const [showRegistration, setShowRegistration] = useState(false);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [registerErrors, setRegisterErrors] = useState({});
  const [registerProcessing, setRegisterProcessing] = useState(false);
  const [registered, setRegistered] = useState(false);
  const [registerGeneralError, setRegisterGeneralError] = useState('');

  const handleCreateAccount = async () => {
    setRegisterErrors({});
    setRegisterGeneralError('');
    setRegisterProcessing(true);

    try {
      const { res, json } = await postJson(route('intake.register'), {
        password,
        password_confirmation: passwordConfirmation,
      });

      if (res.ok && json?.success) {
        // Account created — redirect to OFW portal
        window.location.href = json.redirect || route('ofw.dashboard');
      } else if (res.status === 422) {
        if (json?.errors) {
          setRegisterErrors(json.errors);
        } else if (json?.error) {
          setRegisterGeneralError(json.error);
        }
      } else {
        setRegisterGeneralError(json?.error || json?.message || 'Registration failed. Please try again.');
      }
    } catch (e) {
      setRegisterGeneralError('Network error. Please check your connection and try again.');
    }

    setRegisterProcessing(false);
  };

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Submission Received" />
      <AppHeader />

      <main className="flex flex-1 flex-col items-center justify-center px-6 pb-16 pt-28 md:px-8 md:pb-20 md:pt-32">
        <div className="mx-auto w-full max-w-lg text-center">
          <div className="mb-8 flex justify-center">
            <div className="flex h-24 w-24 items-center justify-center rounded-full bg-green-100">
              <span className="material-symbols-outlined text-5xl text-green-600">check_circle</span>
            </div>
          </div>
          <h1 className="mb-4 font-headline text-2xl font-bold text-slate-900 md:text-3xl">Request Submitted Successfully</h1>
          <p className="mx-auto mb-6 max-w-md text-sm leading-relaxed text-slate-600 md:text-base">
            Your assistance request has been submitted. A Case Manager will review your information and you will be notified once your case is processed.
          </p>

          {/* Case details */}
          <div className="mx-auto mb-8 max-w-sm rounded border border-outline-variant bg-slate-50 p-4 text-left">
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-slate-500">Case Number</span>
                <span className="font-mono font-bold text-slate-900">{caseNumber}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Tracker Number</span>
                <span className="font-mono font-bold text-slate-900">{trackerNumber}</span>
              </div>
            </div>
          </div>

          {/* Account creation upsell */}
          {!registered && (
            <div className="mb-8">
              {!showRegistration ? (
                <div className="rounded-lg border border-primary/20 bg-primary/5 p-6">
                  <h2 className="mb-2 text-base font-bold text-slate-900">Create an account for easier tracking</h2>
                  <p className="mb-4 text-sm text-slate-600">
                    Set a password so you can log in anytime to check your case status — no need to enter verification codes each time.
                  </p>
                  <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <button
                      type="button"
                      onClick={() => setShowRegistration(true)}
                      className="bg-primary px-6 py-3 text-sm font-bold text-white hover:brightness-110"
                    >
                      Create Account
                    </button>
                    <button
                      type="button"
                      onClick={() => setRegistered(true)}
                      className="border border-outline-variant px-6 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50"
                    >
                      Maybe later
                    </button>
                  </div>
                </div>
              ) : (
                <div className="rounded-lg border border-outline-variant bg-white p-6 text-left shadow-sm">
                  <h2 className="mb-1 text-base font-bold text-slate-900">Create Your Account</h2>
                  <p className="mb-4 text-sm text-slate-500">
                    Set a password to access your case anytime.
                  </p>

                  {registerGeneralError && (
                    <div className="mb-4 rounded bg-error-container p-3 text-xs font-medium text-on-error-container">
                      {registerGeneralError}
                    </div>
                  )}

                  <div className="space-y-4">
                    <div>
                      <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Email</label>
                      <div className="relative">
                        <input
                          type="email"
                          value={email}
                          readOnly
                          title="Verified email — you'll log in with this address"
                          className="w-full border border-outline-variant bg-surface-container px-4 py-3 pr-12 text-sm font-medium text-slate-900 focus:outline-none"
                        />
                        <span
                          className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-primary"
                          aria-hidden="true"
                        >
                          <span className="material-symbols-outlined text-[18px]">verified</span>
                        </span>
                      </div>
                      <p className="mt-1.5 text-xs text-slate-500">You'll use this email to log in.</p>
                    </div>
                    <div>
                      <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Password</label>
                      <input
                        type="password"
                        value={password}
                        onChange={e => { setPassword(e.target.value); setRegisterErrors(prev => ({ ...prev, password: undefined })); }}
                        placeholder="At least 8 characters"
                        className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${registerErrors.password ? 'border-error' : 'border-outline-variant focus:border-primary'}`}
                      />
                      {registerErrors.password && <p className="mt-1 text-xs text-error">{registerErrors.password[0]}</p>}
                    </div>
                    <div>
                      <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Confirm Password</label>
                      <input
                        type="password"
                        value={passwordConfirmation}
                        onChange={e => setPasswordConfirmation(e.target.value)}
                        placeholder="Re-enter your password"
                        className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none"
                      />
                    </div>
                    <button
                      type="button"
                      onClick={handleCreateAccount}
                      disabled={registerProcessing || !password || !passwordConfirmation}
                      className="w-full bg-primary px-6 py-3 text-sm font-bold text-white hover:brightness-110 disabled:opacity-50"
                    >
                      {registerProcessing ? 'Creating account...' : 'Create Account & Go to Dashboard'}
                    </button>
                    <button
                      type="button"
                      onClick={() => { setShowRegistration(false); setRegisterErrors({}); setRegisterGeneralError(''); }}
                      className="w-full text-center text-xs text-slate-500 hover:text-primary"
                    >
                      Skip for now
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href={route('track.index')} className="inline-flex items-center justify-center gap-2 bg-primary px-6 py-3.5 text-sm font-bold text-white hover:brightness-110">
              <span className="material-symbols-outlined text-[18px]">search</span>
              Track Your Case
            </a>
            <a href="/" className="inline-flex items-center justify-center gap-2 border border-outline-variant px-6 py-3.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
              Return to Home
            </a>
          </div>
        </div>
      </main>

      <AppFooter />
    </div>
  );
}
