import { useState, useCallback, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TurnstileWidget from '@/Components/TurnstileWidget';
import ChatBot from '@/Components/ChatBot';
import AddressDropdowns from '@/Components/AddressDropdowns';
import PhoneInput from '@/Components/PhoneInput';
import CountrySelect from '@/Components/CountrySelect';
import SearchableSelect from '@/Components/SearchableSelect';

const STEPS = [
  { id: 'email', label: 'Email Verification' },
  { id: 'personal', label: 'Personal Information' },
  { id: 'address', label: 'Address' },
  { id: 'employment', label: 'Employment' },
  { id: 'nok', label: 'Next of Kin' },
  { id: 'case', label: 'Case Details' },
  { id: 'consent', label: 'Consent & Password' },
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

export default function IntakeIndex({ categories, caseIssues, positionOptions }) {
  const { turnstile } = usePage().props;

  const [currentStep, setCurrentStep] = useState(0);
  const [emailVerified, setEmailVerified] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState({});
  const [turnstileToken, setTurnstileToken] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [otpHint, setOtpHint] = useState('');
  const [debugOtp, setDebugOtp] = useState(null);
  const [duplicateMessage, setDuplicateMessage] = useState('');
  const [submitSuccess, setSubmitSuccess] = useState(false);

  const [formData, setFormData] = useState(() => {
    const saved = loadFromSession();
    return saved || {
      email: '',
      otp: '',
      client: { first_name: '', last_name: '', middle_initial: '', suffix: '', date_of_birth: '', sex: '', contact_number: '' },
      address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
      employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', is_present: false, last_country: '', last_position: '', date_of_arrival: '' },
      next_of_kin: [{ first_name: '', last_name: '', middle_initial: '', relationship: '', phone_number: '', email: '', is_primary: true, region: '', province: '', city_municipality: '', barangay: '', street: '' }],
      category_ids: [],
      case_issue_id: '',
      vulnerability_indicator: 'None',
      summary: '',
      consent: false,
      password: '',
      password_confirmation: '',
    };
  });

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

  const goNext = () => setCurrentStep(s => Math.min(s + 1, STEPS.length - 1));
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
      const res = await fetch(route('intake.verify-email'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify({ email: formData.email, cf_turnstile_response: turnstileToken }),
      });
      const json = await res.json();
      if (res.ok && json.sent) {
        setOtpSent(true);
        setOtpHint(json.hint);
        setDebugOtp(json.debug_otp);
      } else {
        setErrors({ email: json.message || 'Failed to send verification code.' });
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
      const res = await fetch(route('intake.check-duplicate'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify({ email: formData.email, otp: formData.otp }),
      });
      const json = await res.json();
      if (!res.ok) {
        setErrors({ otp: json.error || 'Invalid or expired OTP.' });
      } else if (json.duplicate) {
        setDuplicateMessage(json.message);
      } else {
        setEmailVerified(true);
        // Pre-fill from existing client if available
        if (json.existing_client) {
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
      const res = await fetch(route('intake.submit'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify(formData),
      });
      const json = await res.json();
      if (res.ok && json.success) {
        clearSession();
        setSubmitSuccess(true);
      } else if (res.status === 422) {
        // Validation errors
        const validationErrors = json.errors || {};
        setErrors(validationErrors);
      } else {
        setErrors({ submit: json.error || 'Submission failed. Please try again.' });
      }
    } catch (e) {
      setErrors({ submit: 'Network error. Please try again.' });
    }
    setProcessing(false);
  };

  // If submitted successfully, redirect to success page
  if (submitSuccess) {
    return <IntakeSuccess />;
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
        <div className="mx-auto max-w-4xl px-4 py-6 md:px-8">
          <div className="flex items-center justify-between gap-1">
            {STEPS.map((step, i) => (
              <div key={step.id} className="flex flex-1 flex-col items-center">
                <div className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold ${
                  i < currentStep ? 'bg-primary text-white' :
                  i === currentStep ? 'bg-primary text-white ring-4 ring-primary/20' :
                  'bg-slate-200 text-slate-500'
                }`}>
                  {i < currentStep ? '✓' : i + 1}
                </div>
                <span className="mt-1 hidden text-[10px] font-medium text-slate-500 sm:block">{step.label}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Form content */}
        <div className="mx-auto max-w-3xl px-4 pb-16 md:px-8">
          <div className="rounded-lg border border-outline-variant bg-white p-6 shadow-sm md:p-8">
            {currentStep === 0 && (
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
              />
            )}
            {currentStep === 1 && (
              <PersonalStep formData={formData} updateField={updateField} errors={errors} onNext={goNext} onBack={goBack} />
            )}
            {currentStep === 2 && (
              <AddressStep formData={formData} updateField={updateField} errors={errors} onNext={goNext} onBack={goBack} />
            )}
            {currentStep === 3 && (
              <EmploymentStep formData={formData} updateField={updateField} errors={errors} positionOptions={positionOptions} onNext={goNext} onBack={goBack} />
            )}
            {currentStep === 4 && (
              <NokStep formData={formData} setFormData={setFormData} errors={errors} onNext={goNext} onBack={goBack} />
            )}
            {currentStep === 5 && (
              <CaseDetailsStep formData={formData} updateField={updateField} setFormData={setFormData} errors={errors} categories={categories} caseIssues={caseIssues} onNext={goNext} onBack={goBack} />
            )}
            {currentStep === 6 && (
              <ConsentStep formData={formData} updateField={updateField} errors={errors} processing={processing} onSubmit={handleSubmit} onBack={goBack} />
            )}
          </div>
        </div>
      </main>

      <AppFooter />
    </div>
  );
}


// --- Step Components ---

function EmailStep({ formData, updateField, errors, processing, otpSent, otpHint, debugOtp, duplicateMessage, turnstile, turnstileToken, setTurnstileToken, onSendOtp, onVerifyOtp }) {
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
        </div>
      )}
    </div>
  );
}

function PersonalStep({ formData, updateField, errors, onNext, onBack }) {
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
      <p className="mb-6 text-sm text-slate-500">Tell us about yourself.</p>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">First Name *</label>
          <input type="text" value={formData.client.first_name} onChange={e => { updateField('client.first_name', e.target.value); clearError('first_name'); }}
            className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${stepErrors.first_name ? 'border-error' : 'border-outline-variant focus:border-primary'}`} />
          {stepErrors.first_name && <p className="mt-1 text-xs text-error">{stepErrors.first_name}</p>}
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Last Name *</label>
          <input type="text" value={formData.client.last_name} onChange={e => { updateField('client.last_name', e.target.value); clearError('last_name'); }}
            className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${stepErrors.last_name ? 'border-error' : 'border-outline-variant focus:border-primary'}`} />
          {stepErrors.last_name && <p className="mt-1 text-xs text-error">{stepErrors.last_name}</p>}
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Middle Initial</label>
          <input type="text" maxLength={1} value={formData.client.middle_initial} onChange={e => updateField('client.middle_initial', e.target.value.toUpperCase())}
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm uppercase focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Suffix</label>
          <select value={formData.client.suffix} onChange={e => updateField('client.suffix', e.target.value)}
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none">
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
          <input type="date" value={formData.client.date_of_birth} onChange={e => updateField('client.date_of_birth', e.target.value)}
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Sex</label>
          <select value={formData.client.sex} onChange={e => updateField('client.sex', e.target.value)}
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none">
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

function EmploymentStep({ formData, updateField, errors, positionOptions, onNext, onBack }) {
  // Merge position options for SearchableSelect
  const mergedPositionOptions = (positionOptions || []).map(p => ({
    value: p.label || p,
    label: p.label || p,
  }));

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
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Last Position/Job</label>
          <SearchableSelect value={formData.employment.last_position} onChange={v => updateField('employment.last_position', v)} options={mergedPositionOptions} placeholder="Select or type position..." allowCustom />
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

function CaseDetailsStep({ formData, updateField, setFormData, errors, categories, caseIssues, onNext, onBack }) {
  const [showAddIssue, setShowAddIssue] = useState(false);
  const [newIssueName, setNewIssueName] = useState('');
  const [addingIssue, setAddingIssue] = useState(false);
  const [localIssues, setLocalIssues] = useState(caseIssues);
  const [stepErrors, setStepErrors] = useState({});

  useEffect(() => { setLocalIssues(caseIssues); }, [caseIssues]);

  const toggleCategory = (id) => {
    setFormData(prev => {
      const ids = prev.category_ids.includes(id)
        ? prev.category_ids.filter(c => c !== id)
        : [...prev.category_ids, id];
      return { ...prev, category_ids: ids };
    });
    setStepErrors(prev => ({ ...prev, category_ids: undefined }));
  };

  const handleQuickAddIssue = async () => {
    const name = newIssueName.trim();
    if (!name || addingIssue) return;
    setAddingIssue(true);
    try {
      const res = await fetch(route('case-issues.quick'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify({ name }),
      });
      const newIssue = await res.json();
      if (!res.ok) {
        alert(newIssue.errors?.name?.[0] || 'Failed to add issue.');
        return;
      }
      setLocalIssues(prev => [...prev, newIssue]);
      updateField('case_issue_id', newIssue.id);
      setNewIssueName('');
      setShowAddIssue(false);
    } catch (e) {
      alert('Failed to add issue. Please try again.');
    } finally {
      setAddingIssue(false);
    }
  };

  const validate = () => {
    const errs = {};
    if (formData.category_ids.length === 0) errs.category_ids = 'Please select at least one category.';
    if (formData.summary.trim().length < 20) errs.summary = 'Please provide at least 20 characters.';
    setStepErrors(errs);
    return Object.keys(errs).length === 0;
  };

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Case Details</h2>
      <p className="mb-6 text-sm text-slate-500">Tell us about your situation and what help you need.</p>

      <div className="space-y-6">
        <div>
          <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-slate-600">What kind of help do you need? *</label>
          <div className="grid gap-2 sm:grid-cols-2">
            {categories.map(cat => (
              <label key={cat.id} className={`flex cursor-pointer items-center gap-2 rounded border p-3 text-sm transition ${
                formData.category_ids.includes(cat.id) ? 'border-primary bg-primary/5 font-medium text-primary' : 'border-outline-variant hover:border-primary/50'
              }`}>
                <input type="checkbox" checked={formData.category_ids.includes(cat.id)} onChange={() => toggleCategory(cat.id)} className="sr-only" />
                <span className={`flex h-4 w-4 items-center justify-center rounded border ${formData.category_ids.includes(cat.id) ? 'border-primary bg-primary text-white' : 'border-slate-300'}`}>
                  {formData.category_ids.includes(cat.id) && <span className="text-[10px]">✓</span>}
                </span>
                {cat.name}
              </label>
            ))}
          </div>
          {(errors['category_ids'] || stepErrors.category_ids) && <p className="mt-1 text-xs text-error">{stepErrors.category_ids || errors['category_ids']}</p>}
        </div>

        {localIssues.length > 0 && (
          <div>
            <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Specific Issue (optional)</label>
            <div className="flex gap-2">
              <select value={formData.case_issue_id} onChange={e => updateField('case_issue_id', e.target.value)}
                className="flex-1 border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none">
                <option value="">Select an issue...</option>
                {localIssues.map(issue => (
                  <option key={issue.id} value={issue.id}>{issue.name}</option>
                ))}
              </select>
              <button
                type="button"
                onClick={() => { setNewIssueName(''); setShowAddIssue(!showAddIssue); }}
                className="inline-flex h-[46px] w-[46px] shrink-0 items-center justify-center border border-dashed border-primary/40 text-primary transition hover:bg-primary/5"
                title="Add new issue"
              >
                <span className="material-symbols-outlined text-[20px]">add</span>
              </button>
            </div>
            {showAddIssue && (
              <div className="mt-3 rounded border border-primary/20 bg-primary/5 p-3">
                <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">New Issue Name</label>
                <input
                  type="text"
                  value={newIssueName}
                  onChange={e => setNewIssueName(e.target.value)}
                  onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); handleQuickAddIssue(); } }}
                  placeholder="Enter new issue name..."
                  className="w-full border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:border-primary focus:outline-none"
                  autoFocus
                />
                <div className="mt-2 flex items-center gap-2">
                  <button
                    type="button"
                    onClick={handleQuickAddIssue}
                    disabled={addingIssue || !newIssueName.trim()}
                    className="bg-primary px-4 py-1.5 text-xs font-bold text-white hover:brightness-110 disabled:opacity-50"
                  >
                    {addingIssue ? 'Adding...' : 'Add'}
                  </button>
                  <button
                    type="button"
                    onClick={() => { setShowAddIssue(false); setNewIssueName(''); }}
                    className="text-xs font-medium text-slate-500 hover:text-slate-700"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Tell us what happened *</label>
          <textarea
            value={formData.summary}
            onChange={e => { updateField('summary', e.target.value); setStepErrors(prev => ({ ...prev, summary: undefined })); }}
            rows={5}
            placeholder="Please describe your situation, what happened, and what kind of assistance you need..."
            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none"
          />
          <p className="mt-1 text-xs text-slate-400">{formData.summary.length}/20 minimum characters</p>
          {(stepErrors.summary || errors.summary) && <p className="mt-1 text-xs text-error">{stepErrors.summary || errors.summary}</p>}
        </div>

        <div>
          <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Vulnerability Indicator (optional)</label>
          <div className="flex flex-wrap gap-3 mt-1">
            {['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'].map((opt) => {
              const checked = formData.vulnerability_indicator && formData.vulnerability_indicator !== 'None' && formData.vulnerability_indicator.split(',').map(s => s.trim()).includes(opt);
              return (
                <label key={opt} className="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => {
                      const current = formData.vulnerability_indicator && formData.vulnerability_indicator !== 'None'
                        ? formData.vulnerability_indicator.split(',').map(s => s.trim()).filter(Boolean)
                        : [];
                      const next = checked ? current.filter(v => v !== opt) : [...current, opt];
                      updateField('vulnerability_indicator', next.length > 0 ? next.join(', ') : 'None');
                    }}
                    className="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary"
                  />
                  <span className="text-sm text-slate-700">{opt}</span>
                </label>
              );
            })}
          </div>
        </div>
      </div>

      <div className="mt-8 flex justify-between">
        <button type="button" onClick={onBack} className="px-6 py-3 text-sm font-medium text-slate-600 hover:text-primary">Back</button>
        <button type="button" onClick={() => validate() && onNext()} className="bg-primary px-8 py-3 text-sm font-bold text-white hover:brightness-110">Continue</button>
      </div>
    </div>
  );
}

function ConsentStep({ formData, updateField, errors, processing, onSubmit, onBack }) {
  const [stepErrors, setStepErrors] = useState({});

  const validate = () => {
    const errs = {};
    if (!formData.consent) errs.consent = 'You must consent to data processing to submit this form.';
    if (!formData.password) errs.password = 'Please create a password for your account.';
    else if (formData.password.length < 8) errs.password = 'Password must be at least 8 characters long.';
    if (!formData.password_confirmation) errs.password_confirmation = 'Please confirm your password.';
    else if (formData.password !== formData.password_confirmation) errs.password_confirmation = 'Password confirmation does not match.';
    setStepErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = () => {
    if (validate()) onSubmit();
  };

  return (
    <div>
      <h2 className="mb-1 text-lg font-bold text-slate-900">Consent & Account</h2>
      <p className="mb-6 text-sm text-slate-500">Review, consent to data processing, and create your account password.</p>

      <div className="space-y-6">
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

        <div>
          <h3 className="mb-3 text-sm font-bold text-slate-700">Create Account Password</h3>
          <p className="mb-4 text-xs text-slate-500">
            This password will let you log in later to track your case status.
          </p>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Password *</label>
              <input type="password" value={formData.password} onChange={e => { updateField('password', e.target.value); setStepErrors(prev => ({ ...prev, password: undefined })); }}
                placeholder="Min. 8 characters"
                className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${stepErrors.password ? 'border-error' : 'border-outline-variant focus:border-primary'}`} />
              {stepErrors.password && <p className="mt-1 text-xs text-error">{stepErrors.password}</p>}
            </div>
            <div>
              <label className="mb-1 block text-xs font-bold uppercase tracking-widest text-slate-600">Confirm Password *</label>
              <input type="password" value={formData.password_confirmation} onChange={e => { updateField('password_confirmation', e.target.value); setStepErrors(prev => ({ ...prev, password_confirmation: undefined })); }}
                className={`w-full border bg-surface-container px-4 py-3 text-sm focus:outline-none ${stepErrors.password_confirmation ? 'border-error' : 'border-outline-variant focus:border-primary'}`} />
              {stepErrors.password_confirmation && <p className="mt-1 text-xs text-error">{stepErrors.password_confirmation}</p>}
            </div>
          </div>
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

function IntakeSuccess() {
  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Submission Received" />
      <AppHeader />

      <main className="flex flex-1 items-center justify-center px-4 pt-20">
        <div className="mx-auto max-w-lg text-center">
          <div className="mb-6 flex justify-center">
            <div className="flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
              <span className="material-symbols-outlined text-4xl text-green-600">check_circle</span>
            </div>
          </div>
          <h1 className="mb-3 font-headline text-2xl font-bold text-slate-900">Request Submitted Successfully</h1>
          <p className="mb-8 text-sm leading-relaxed text-slate-600">
            Your assistance request has been submitted. A Case Manager will review your information and you will be notified once your case is processed.
          </p>
          <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href={route('login')} className="inline-flex items-center justify-center gap-2 bg-primary px-6 py-3 text-sm font-bold text-white hover:brightness-110">
              <span className="material-symbols-outlined text-[18px]">login</span>
              Log In to Track Status
            </a>
            <a href="/" className="inline-flex items-center justify-center gap-2 border border-outline-variant px-6 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50">
              Return to Home
            </a>
          </div>
        </div>
      </main>

      <AppFooter />
    </div>
  );
}
