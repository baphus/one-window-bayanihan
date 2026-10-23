import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import AddressDropdowns from '@/Components/AddressDropdowns';
import CountrySelect from '@/Components/CountrySelect';
import SearchableSelect from '@/Components/SearchableSelect';
import { DEFAULT_OCCUPATIONS } from '@/data/defaultOccupations';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { useToast } from '@/Hooks/useToast';
import { getProvincesByRegion } from '@/data/philippine-addresses';
import { formatResolvedAddress } from '@/lib/addressResolver';

const ROLE_CARDS = [
    {
        value: 'OFW',
        icon: 'flight',
        title: 'I am an OFW',
        description: 'File and track your own case request.',
    },
    {
        value: 'NEXT_OF_KIN',
        icon: 'family_restroom',
        title: 'Filing for a family member',
        description: 'You are filing on behalf of an OFW.',
    },
];

// Mirrors Case/Create.jsx STEPS — compact horizontal stepper fits the narrow auth card.
const STEPS = [
    { id: 1, title: 'Identity', description: 'Birth date, sex, and suffix' },
    { id: 2, title: 'Contact & Address', description: 'Contact number, address, and email code' },
    { id: 3, title: 'Employment & Review', description: 'Work history and final review' },
];

// Same 15–100 bounds as Case/Create.jsx and the backend date_of_birth rule.
const DOB_BOUNDS = (() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return {
        min: `${now.getFullYear() - 100}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
        max: `${now.getFullYear() - 15}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
    };
})();

const TODAY = (() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
})();

const WORKING_AGE_FLOOR_YEARS = 15;
const SUFFIX_OPTIONS = ['', 'Jr', 'Sr', 'II', 'III', 'IV', 'V'];

function employmentStartFloor(dob) {
    if (!dob) return '';
    const [y, m, d] = dob.split('-').map(Number);
    if (!y || !m || !d) return '';
    const floor = new Date(y + WORKING_AGE_FLOOR_YEARS, m - 1, d);
    const pad = (n) => String(n).padStart(2, '0');
    return `${floor.getFullYear()}-${pad(floor.getMonth() + 1)}-${pad(floor.getDate())}`;
}

function regionRequiresProvince(regionCode) {
    if (!regionCode) return false;
    return getProvincesByRegion(regionCode).length > 0;
}

function FieldError({ message }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

// Confirmation modal for the Step 3 review summary — mirrors the repo's
// modal patterns (UnsavedChangesModal focus/Escape/backdrop handling,
// Case/Create.jsx CaseSummaryModal header + scrollable body + footer).
function ReviewConfirmModal({ show, rows, onConfirm, onCancel, processing }) {
    const cancelBtnRef = useRef(null);

    useEffect(() => {
        if (show) cancelBtnRef.current?.focus();
    }, [show]);

    useEffect(() => {
        if (!show) return;
        const handler = (e) => {
            if (e.key === 'Escape') onCancel();
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [show, onCancel]);

    if (!show) return null;

    return (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="review-confirm-title" aria-describedby="review-confirm-desc">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onCancel} />
            <div className="relative z-10 w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl owb-modal-animate">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 id="review-confirm-title" className="text-lg font-bold text-slate-900">Confirm your details</h3>
                        <p id="review-confirm-desc" className="text-[13px] text-slate-500 mt-0.5">Review the details below before finishing registration.</p>
                    </div>
                    <button type="button" onClick={onCancel} aria-label="Close" className="rounded-md p-1.5 text-slate-400 hover:text-slate-600 transition-colors">
                        <span className="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                    </button>
                </div>

                <div className="max-h-[60vh] overflow-y-auto px-6 py-5 owb-scroll-wide">
                    <dl className="space-y-2 text-[13px]">
                        {rows.map((row) => (
                            <div key={row.label} className="flex justify-between gap-4">
                                <dt className="text-slate-500">{row.label}</dt>
                                <dd className={`font-semibold text-slate-800 text-right ${row.wide ? 'break-words max-w-[60%]' : ''}`}>{row.value}</dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="flex items-center justify-between border-t border-slate-200 px-6 py-4">
                    <button
                        ref={cancelBtnRef}
                        type="button"
                        onClick={onCancel}
                        className="h-9 px-2 text-[12px] font-bold text-slate-500 hover:text-slate-700 transition-colors"
                    >
                        Back
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="inline-flex items-center gap-2 h-9 rounded-md bg-primary px-5 text-[12px] font-bold text-white hover:brightness-110 transition-all disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span className="material-symbols-outlined text-[16px]" aria-hidden="true">check_circle</span>
                        {processing ? 'Finishing…' : 'Confirm & Finish'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function RegisterProfile({ pendingName = {} }) {
    const { data, setData, post, processing, errors, setError, clearErrors, isDirty } = useForm({
        type: 'OFW',
        otp: '',
        suffix: '',
        date_of_birth: '',
        sex: '',
        contact_number: '',
        address: {
            region: '',
            province: '',
            city_municipality: '',
            barangay: '',
            street: '',
        },
        employment: {
            employer_name: '',
            position: '',
            country: '',
            start_date: '',
            end_date: '',
            is_present: false,
            last_country: '',
            last_position: '',
            date_of_arrival: '',
        },
    });

    const toast = useToast();
    const { UnsavedModal } = useUnsavedChanges(isDirty);

    const [currentStep, setCurrentStep] = useState(1);
    const [showReviewModal, setShowReviewModal] = useState(false);

    // Occupation dropdown options — same curated DMW/PSA-based OFW list as
    // Case/Create.jsx (DEFAULT_OCCUPATIONS), shaped for SearchableSelect.
    // This page gets no backend occupationOptions prop, so defaults only.
    const mergedOccupationOptions = useMemo(() => {
        const labels = [...DEFAULT_OCCUPATIONS]
            .map((p) => String(p).trim())
            .filter(Boolean);
        const unique = [...new Set(labels)].sort((a, b) => a.localeCompare(b));
        return unique.map((p) => ({ value: p, label: p }));
    }, []);

    const inputClass =
        'w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-md';

    // AddressDropdowns emits both batch objects ({ region, ... }) and
    // (field, value) pairs — handle both into data.address (Case/Create.jsx pattern).
    function handleAddressChange(field, value) {
        if (typeof field === 'object') {
            setData('address', { ...data.address, ...field });
        } else {
            setData('address', { ...data.address, [field]: value });
            if (errors[`address.${field}`]) {
                setError(`address.${field}`, '');
            }
        }
    }

    function handleEmploymentChange(field, value) {
        setData('employment', { ...data.employment, [field]: value });
        if (errors[`employment.${field}`]) {
            setError(`employment.${field}`, '');
        }
    }

    function notifyMissing(missing) {
        if (missing.length === 1) {
            toast.error(`${missing[0]} is required.`);
        } else {
            toast.error(`Complete ${missing.length} required fields: ${missing.join(', ')}`);
        }
    }

    function validateStep(step, { clear = true } = {}) {
        if (clear) clearErrors();
        let isValid = true;
        const missing = [];

        if (step === 1) {
            if (!data.type) {
                setError('type', 'Please choose who this account is for.');
                isValid = false;
                missing.push('Account type');
            }
            if (!data.date_of_birth) {
                setError('date_of_birth', 'Date of birth is required.');
                isValid = false;
                missing.push('Date of Birth');
            } else if (data.date_of_birth > DOB_BOUNDS.max) {
                setError('date_of_birth', 'Applicant must be at least 15 years old.');
                isValid = false;
                missing.push('Date of Birth');
            } else if (data.date_of_birth < DOB_BOUNDS.min) {
                setError('date_of_birth', 'Applicant cannot be over 100 years old.');
                isValid = false;
                missing.push('Date of Birth');
            }
            if (!data.sex) {
                setError('sex', 'Sex is required.');
                isValid = false;
                missing.push('Sex');
            }
            if (!isValid) notifyMissing(missing);
        }

        if (step === 2) {
            if (!data.contact_number.trim()) {
                setError('contact_number', 'Contact number is required.');
                isValid = false;
                missing.push('Contact Number');
            }
            if (!data.address.region) {
                setError('address.region', 'Region is required.');
                isValid = false;
                missing.push('Region');
            }
            if (regionRequiresProvince(data.address.region) && !data.address.province) {
                setError('address.province', 'Province is required.');
                isValid = false;
                missing.push('Province');
            }
            if (!data.address.city_municipality) {
                setError('address.city_municipality', 'City/Municipality is required.');
                isValid = false;
                missing.push('City/Municipality');
            }
            if (!data.address.barangay) {
                setError('address.barangay', 'Barangay is required.');
                isValid = false;
                missing.push('Barangay');
            }
            if (!/^\d{6}$/.test(data.otp)) {
                setError('otp', 'Enter the 6-digit code sent to your email.');
                isValid = false;
                missing.push('Email Verification Code');
            }
            if (!isValid) notifyMissing(missing);
        }

        if (step === 3) {
            if (!data.employment.start_date) {
                setError('employment.start_date', 'Employment start date is required.');
                isValid = false;
                missing.push('Employment Start Date');
            } else if (data.employment.start_date > TODAY) {
                setError('employment.start_date', 'Employment start date cannot be in the future.');
                isValid = false;
                missing.push('Employment Start Date (future)');
            }
            const minStartDate = employmentStartFloor(data.date_of_birth);
            if (data.employment.start_date && minStartDate && data.employment.start_date < minStartDate) {
                setError('employment.start_date', 'Employment start is inconsistent with the date of birth.');
                isValid = false;
                missing.push('Employment Start Date (before working age)');
            }
            if (!data.employment.end_date && !data.employment.is_present) {
                setError('employment.end_date', 'Employment end date is required.');
                isValid = false;
                missing.push('Employment End Date');
            }
            if (data.employment.start_date && data.employment.end_date && !data.employment.is_present && data.employment.end_date < data.employment.start_date) {
                setError('employment.end_date', 'End date must be on or after the start date.');
                isValid = false;
                missing.push('Employment End Date (invalid range)');
            }
            if (data.employment.end_date && !data.employment.is_present && data.employment.end_date > TODAY) {
                setError('employment.end_date', 'Employment end date cannot be in the future.');
                isValid = false;
                missing.push('Employment End Date (future)');
            }
            if (!data.employment.last_country) {
                setError('employment.last_country', 'Last country of employment is required.');
                isValid = false;
                missing.push('Last Country of Employment');
            }
            if (!data.employment.last_position.trim()) {
                setError('employment.last_position', 'Last occupation is required.');
                isValid = false;
                missing.push('Last Occupation');
            }
            if (!data.employment.date_of_arrival) {
                setError('employment.date_of_arrival', 'Date of arrival is required.');
                isValid = false;
                missing.push('Date of Arrival');
            }
            if (!isValid) notifyMissing(missing);
        }

        return isValid;
    }

    // Lightweight gate for disabling Next/Submit (no error writes) — Case/Create.jsx canProceed pattern.
    function canProceedStep(step) {
        if (step === 1) {
            return Boolean(data.type)
                && data.date_of_birth.length > 0
                && data.date_of_birth <= DOB_BOUNDS.max
                && data.date_of_birth >= DOB_BOUNDS.min
                && data.sex.length > 0;
        }
        if (step === 2) {
            return data.contact_number.trim().length > 0
                && data.address.region.length > 0
                && (!regionRequiresProvince(data.address.region) || data.address.province.length > 0)
                && data.address.city_municipality.length > 0
                && data.address.barangay.length > 0
                && /^\d{6}$/.test(data.otp);
        }
        return data.employment.start_date.length > 0
            && (data.employment.is_present || data.employment.end_date.length > 0)
            && data.employment.last_country.length > 0
            && data.employment.last_position.trim().length > 0
            && data.employment.date_of_arrival.length > 0;
    }

    function getMissingFields(step) {
        const missing = [];
        if (step === 1) {
            if (!data.date_of_birth) missing.push('Date of Birth');
            if (!data.sex) missing.push('Sex');
        }
        if (step === 2) {
            if (!data.contact_number.trim()) missing.push('Contact Number');
            if (!data.address.region) missing.push('Region');
            if (regionRequiresProvince(data.address.region) && !data.address.province) missing.push('Province');
            if (!data.address.city_municipality) missing.push('City/Municipality');
            if (!data.address.barangay) missing.push('Barangay');
            if (!/^\d{6}$/.test(data.otp)) missing.push('Email Code');
        }
        if (step === 3) {
            if (!data.employment.start_date) missing.push('Start Date');
            if (!data.employment.is_present && !data.employment.end_date) missing.push('End Date');
            if (!data.employment.last_country) missing.push('Last Country');
            if (!data.employment.last_position.trim()) missing.push('Last Occupation');
            if (!data.employment.date_of_arrival) missing.push('Arrival Date');
        }
        return missing;
    }

    function canSubmit() {
        return [1, 2, 3].every(canProceedStep);
    }

    function handleNext() {
        if (currentStep < STEPS.length) {
            if (!validateStep(currentStep)) return;
            clearErrors();
            setCurrentStep((prev) => prev + 1);
        }
    }

    function handleBack() {
        if (currentStep > 1) {
            clearErrors();
            setCurrentStep((prev) => prev - 1);
        }
    }

    const submit = (e) => {
        e.preventDefault();
        if (processing) return;
        if (currentStep !== STEPS.length) return;
        // Final gate across all steps — jump back to the first failing step.
        // When everything validates, open the review confirmation modal
        // instead of posting; Confirm posts via handleConfirmSubmit.
        clearErrors();
        for (const step of [1, 2, 3]) {
            if (!validateStep(step, { clear: step === 1 })) {
                setCurrentStep(step);
                return;
            }
        }
        setShowReviewModal(true);
    };

    function handleConfirmSubmit() {
        if (processing) return;
        post(route('register.profile.store'), {
            onSuccess: () => setShowReviewModal(false),
            onError: (errs) => {
                const formErrors = errs || {};
                setShowReviewModal(false);
                // Surface the failure (modal is closed, so toast + inline
                // FieldError carry it) — mirrors the file's toast pattern.
                const first = Object.values(formErrors)[0];
                const message = Array.isArray(first) ? first[0] : first;
                toast.error(message || 'Validation failed. Please review your details.');
                // OTP errors render on Step 2 — jump there so the user sees them.
                if (formErrors.otp) {
                    setCurrentStep(2);
                }
            },
        });
    }

    const selectedRole = ROLE_CARDS.find((role) => role.value === data.type);
    // Display-only: resolve PSGC codes to names (same resolver as
    // Case/Create.jsx's confirm modal; falls back to the raw value when
    // unmapped). Submitted keys/values are untouched.
    const reviewAddress = formatResolvedAddress(data.address, '');
    const reviewRows = [
        { label: 'Account for', value: selectedRole?.title ?? '—' },
        { label: 'Name', value: [pendingName.first_name, pendingName.middle_name, pendingName.last_name, data.suffix].filter(Boolean).join(' ') || '—' },
        { label: 'Birth / Sex', value: [data.date_of_birth, data.sex].filter(Boolean).join(' · ') || '—' },
        { label: 'Contact', value: data.contact_number || '—' },
        { label: 'Address', value: reviewAddress || '—', wide: true },
        { label: 'Email code', value: /^\d{6}$/.test(data.otp) ? 'Provided' : 'Missing' },
        { label: 'Employer', value: [data.employment.employer_name, data.employment.country].filter(Boolean).join(' · ') || '—', wide: true },
    ];

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Complete your profile" />
            {UnsavedModal}

            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex flex-col lg:flex-row">

                {/* Left: Branding */}
                <div className="lg:w-[60%] relative min-h-[320px] lg:min-h-full lg:sticky lg:top-[72px] flex flex-col justify-center text-white overflow-hidden bg-[#01010d]">
                    <div className="absolute inset-0 z-0">
                        <img
                            src="/images/auth/login-bg.jpg"
                            alt=""
                            className="h-full w-full object-cover"
                        />
                        <div className="absolute inset-0 bg-primary/85 mix-blend-multiply" />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-primary/40 to-transparent" />
                    </div>

                    <div className="relative z-10 p-10 lg:p-14">
                        <div className="mb-8">
                            <img src="/logo.png" alt="One Window Bayanihan Logo" className="h-14 w-14 object-contain" />
                        </div>

                        <h1 className="mb-2 font-serif text-3xl lg:text-4xl font-bold leading-tight tracking-tight">
                            One Window Bayanihan
                        </h1>
                        <p className="mb-6 font-serif text-xl lg:text-2xl uppercase tracking-widest text-white/70">
                            Assistance Program
                        </p>

                        <div className="h-1 w-16 bg-secondary-container mb-8" />

                        <p className="max-w-xs text-base text-white/80 leading-relaxed font-medium">
                            Connecting government agencies for seamless migrant worker assistance across Region VII.
                        </p>
                    </div>
                </div>

                {/* Right: Form */}
                <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 lg:min-h-[calc(100dvh-72px)] bg-surface">
                    <div className="w-full max-w-md">
                        <div className="mb-8">
                            <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                badge
                            </span>

                            <h2 className="font-headline text-2xl font-bold text-slate-900">
                                Complete your profile
                            </h2>

                            <p className="mt-2 text-sm text-slate-500">
                                Step 2 of 2. Almost done! Fill in your profile to continue.
                            </p>
                        </div>

                        {/* Stepper — circles only */}
                        <div className="mb-5 rounded-lg border border-outline-variant bg-surface-container p-3">
                            <ol aria-label="Registration steps" className="flex items-start">
                                {STEPS.map((step, index) => {
                                    const isCompleted = currentStep > step.id;
                                    const isCurrent = currentStep === step.id;
                                    return (
                                        <li
                                            key={step.id}
                                            aria-current={isCurrent ? 'step' : undefined}
                                            className={`flex items-start ${index < STEPS.length - 1 ? 'flex-1' : ''}`}
                                        >
                                            <div className="flex flex-col items-center gap-0.5">
                                                <div className={`flex h-6 w-6 items-center justify-center rounded-full border text-[11px] font-extrabold transition-colors ${isCompleted ? 'border-primary bg-primary text-white' : isCurrent ? 'border-primary bg-white text-primary' : 'border-outline-variant bg-white text-slate-400'}`}>
                                                    {isCompleted ? (
                                                        <svg className="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                            <polyline points="20 6 9 17 4 12" />
                                                        </svg>
                                                    ) : step.id}
                                                </div>
                                                <span aria-hidden="true" className={`text-[9px] font-bold text-center leading-tight ${isCurrent || isCompleted ? 'text-primary' : 'text-slate-400'}`}>
                                                    {step.title}
                                                </span>
                                            </div>
                                            <span className="sr-only">
                                                {step.title}{isCurrent ? ' (current step)' : isCompleted ? ' (completed)' : ''}
                                            </span>
                                            {index < STEPS.length - 1 && (
                                                <div aria-hidden="true" className={`mx-1.5 mt-3 h-0.5 flex-1 rounded ${currentStep > step.id ? 'bg-primary' : 'bg-slate-200'}`} />
                                            )}
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>

                        <form
                            onSubmit={submit}
                            onKeyDown={(e) => {
                                // Prevent Enter from submitting on steps 1–2 (Case/Create.jsx pattern).
                                if (e.key === 'Enter' && e.target.tagName === 'INPUT' && currentStep < STEPS.length) {
                                    e.preventDefault();
                                }
                            }}
                            className="space-y-5"
                        >
                            {currentStep === 1 && (
                                <div className="space-y-5">
                                    {/* Who is this account for */}
                                    <div>
                                        <span className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Who is this account for?
                                        </span>
                                        <div className="grid grid-cols-2 gap-3">
                                            {ROLE_CARDS.map((role) => {
                                                const selected = data.type === role.value;
                                                return (
                                                    <button
                                                        key={role.value}
                                                        type="button"
                                                        onClick={() => setData('type', role.value)}
                                                        aria-pressed={selected}
                                                        className={`rounded-md border p-4 text-left transition-all ${
                                                            selected
                                                                ? 'border-primary bg-primary-fixed/40 ring-1 ring-primary'
                                                                : 'border-outline-variant bg-surface-container hover:border-primary/50'
                                                        }`}
                                                    >
                                                        <span className={`material-symbols-outlined block text-[24px] ${selected ? 'text-primary' : 'text-slate-400'}`}>
                                                            {role.icon}
                                                        </span>
                                                        <span className="mt-2 block text-sm font-bold text-slate-900">
                                                            {role.title}
                                                        </span>
                                                        <span className="mt-0.5 block text-xs text-slate-500">
                                                            {role.description}
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <FieldError message={errors.type} />
                                    </div>

                                    {/* Suffix — name itself was collected in step 1 */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Suffix
                                            </label>
                                            <select
                                                value={data.suffix}
                                                onChange={(e) => setData('suffix', e.target.value)}
                                                className={inputClass}
                                            >
                                                <option value="">None</option>
                                                {SUFFIX_OPTIONS.filter(Boolean).map((suffix) => (
                                                    <option key={suffix} value={suffix}>{suffix}</option>
                                                ))}
                                            </select>
                                            <FieldError message={errors.suffix} />
                                        </div>
                                    </div>

                                    {/* Birth + sex */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Date of Birth
                                            </label>
                                            <input
                                                type="date"
                                                value={data.date_of_birth}
                                                onChange={(e) => setData('date_of_birth', e.target.value)}
                                                min={DOB_BOUNDS.min}
                                                max={DOB_BOUNDS.max}
                                                className={inputClass}
                                                required
                                            />
                                            <FieldError message={errors.date_of_birth} />
                                        </div>
                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Sex
                                            </label>
                                            <select
                                                value={data.sex}
                                                onChange={(e) => setData('sex', e.target.value)}
                                                className={inputClass}
                                                required
                                            >
                                                <option value="">Select…</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                            <FieldError message={errors.sex} />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {currentStep === 2 && (
                                <div className="space-y-5">
                                    {/* Contact */}
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Contact Number
                                        </label>
                                        <input
                                            type="tel"
                                            value={data.contact_number}
                                            onChange={(e) => setData('contact_number', e.target.value)}
                                            placeholder="09xx xxx xxxx"
                                            className={inputClass}
                                            required
                                            autoComplete="tel"
                                            maxLength={50}
                                        />
                                        <FieldError message={errors.contact_number} />
                                    </div>

                                    {/* Address */}
                                    <AddressDropdowns
                                        values={data.address}
                                        onChange={handleAddressChange}
                                        errors={{
                                            region: errors['address.region'],
                                            province: errors['address.province'],
                                            city_municipality: errors['address.city_municipality'],
                                            barangay: errors['address.barangay'],
                                        }}
                                    />

                                    {/* Email verification code */}
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Email Verification Code
                                        </label>
                                        <input
                                            type="text"
                                            value={data.otp}
                                            onChange={(e) => setData('otp', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                            placeholder="6-digit code sent to your email"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            className={`${inputClass} tracking-[0.3em] text-center font-bold`}
                                            required
                                        />
                                        <div className="mt-1 flex items-center justify-between gap-3">
                                            <p className="text-xs text-slate-500">
                                                Enter the 6-digit code we sent to your email address.
                                            </p>
                                            <button
                                                type="button"
                                                disabled={processing}
                                                onClick={() => post(route('register.profile.resend-otp'), { preserveScroll: true })}
                                                className="shrink-0 text-xs font-semibold text-primary underline disabled:opacity-50"
                                            >
                                                Resend code
                                            </button>
                                        </div>
                                        <FieldError message={errors.otp} />
                                    </div>
                                </div>
                            )}

                            {currentStep === 3 && (
                                <div className="space-y-5">
                                    {/* Employment */}
                                    <div className="space-y-4">
                                        <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">
                                            Employment
                                        </h4>

                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Position
                                            </label>
                                            <input
                                                value={data.employment.position}
                                                onChange={(e) => handleEmploymentChange('position', e.target.value)}
                                                className={inputClass}
                                                maxLength={255}
                                            />
                                            <FieldError message={errors['employment.position']} />
                                        </div>

                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                    Start Date
                                                </label>
                                                <input
                                                    type="date"
                                                    value={data.employment.start_date}
                                                    onChange={(e) => handleEmploymentChange('start_date', e.target.value)}
                                                    min={employmentStartFloor(data.date_of_birth) || undefined}
                                                    max={TODAY}
                                                    className={inputClass}
                                                    required
                                                />
                                                <FieldError message={errors['employment.start_date']} />
                                            </div>
                                            <div>
                                                <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                    End Date
                                                </label>
                                                <input
                                                    type="date"
                                                    value={data.employment.end_date}
                                                    onChange={(e) => handleEmploymentChange('end_date', e.target.value)}
                                                    min={data.employment.start_date || undefined}
                                                    max={TODAY}
                                                    disabled={data.employment.is_present}
                                                    className={`${inputClass} disabled:opacity-50`}
                                                />
                                                <FieldError message={errors['employment.end_date']} />
                                            </div>
                                        </div>

                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Is Currently Employed
                                            </label>
                                            <input
                                                type="checkbox"
                                                checked={data.employment.is_present}
                                                onChange={(e) => {
                                                    const checked = e.target.checked;
                                                    setData('employment', {
                                                        ...data.employment,
                                                        is_present: checked,
                                                        end_date: checked ? '' : data.employment.end_date,
                                                    });
                                                }}
                                                className="w-4 h-4 rounded border-primary focus:ring-primary" />
                                            <span className="ml-2 text-sm text-slate-700">Present</span>
                                        </div>

                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Last Country of Employment
                                            </label>
                                            <CountrySelect
                                                value={data.employment.last_country}
                                                onChange={(v) => handleEmploymentChange('last_country', v)}
                                                placeholder="Select country..."
                                            />
                                            <FieldError message={errors['employment.last_country']} />
                                        </div>

                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Last Occupation
                                            </label>
                                            <SearchableSelect
                                                value={data.employment.last_position}
                                                onChange={(v) => handleEmploymentChange('last_position', v)}
                                                options={mergedOccupationOptions}
                                                placeholder="Select or type occupation..."
                                                allowCustom
                                            />
                                            <FieldError message={errors['employment.last_position']} />
                                        </div>

                                        <div>
                                            <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                                Arrival Date in Philippines
                                            </label>
                                            <input
                                                type="date"
                                                value={data.employment.date_of_arrival}
                                                onChange={(e) => handleEmploymentChange('date_of_arrival', e.target.value)}
                                                className={inputClass}
                                                required
                                            />
                                            <FieldError message={errors['employment.date_of_arrival']} />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Step navigation — mirrors Case/Create.jsx Back / Next / Submit footer */}
                            {currentStep < STEPS.length && !canProceedStep(currentStep) && getMissingFields(currentStep).length > 0 && (
                                <p className="text-[11px] text-amber-600 leading-tight">
                                    Complete: {getMissingFields(currentStep).join(', ')}
                                </p>
                            )}
                            <div className="flex items-center justify-between gap-3 border-t border-outline-variant pt-5">
                                <button
                                    type="button"
                                    onClick={handleBack}
                                    disabled={currentStep === 1}
                                    className="inline-flex flex-1 items-center justify-center gap-1 rounded-md border border-outline-variant bg-white px-5 py-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span className="material-symbols-outlined text-[18px] leading-none">chevron_left</span>
                                    Back
                                </button>
                                {currentStep < STEPS.length ? (
                                    <button
                                        type="button"
                                        onClick={handleNext}
                                        disabled={!canProceedStep(currentStep)}
                                        className="inline-flex flex-1 items-center justify-center gap-1 rounded-md border border-transparent bg-primary px-5 py-4 text-sm font-bold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Next
                                        <span className="material-symbols-outlined text-[18px] leading-none">chevron_right</span>
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={processing || !canSubmit()}
                                        className="w-full flex-1 bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-md"
                                    >
                                        {processing ? 'Finishing…' : 'Finish and Go to Dashboard'}
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
                </div>
            </main>

            <ReviewConfirmModal
                show={showReviewModal}
                rows={reviewRows}
                onConfirm={handleConfirmSubmit}
                onCancel={() => setShowReviewModal(false)}
                processing={processing}
            />

            <AppFooter />
        </div>
    );
}
