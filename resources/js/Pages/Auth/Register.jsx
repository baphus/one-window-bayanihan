import { useState, useMemo } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import AddressDropdowns from '@/Components/AddressDropdowns';
import CountrySelect from '@/Components/CountrySelect';
import PasswordInput from '@/Components/PasswordInput';
import SearchableSelect from '@/Components/SearchableSelect';
import TurnstileWidget from '@/Components/TurnstileWidget';
import { DEFAULT_OCCUPATIONS } from '@/data/defaultOccupations';
import { getProvincesByRegion } from '@/data/philippine-addresses';
import { makeRegisterSchema } from '@/Schemas/authSchemas';
import useClientValidation from '@/Hooks/useClientValidation';
import { getTurnstileError } from '@/lib/turnstile';

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

export default function Register({ initial = {} }) {
    const { passwordRules, turnstile } = usePage().props;
    const [turnstileToken, setTurnstileToken] = useState('');
    const [turnstileStatus, setTurnstileStatus] = useState('idle');

    const prefill = initial.profile ?? {};
    const creds = initial.credentials ?? {};
    // A stored password hash is never sent to the client, so a returning user
    // re-enters the password; everything else survives "Back to edit".
    const mustResetPassword = Boolean(creds.has_password);

    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
        first_name: creds.first_name ?? '',
        middle_name: creds.middle_name ?? '',
        last_name: creds.last_name ?? '',
        email: creds.email ?? '',
        password: '',
        password_confirmation: '',
        cf_turnstile_response: '',
        type: prefill.type ?? 'OFW',
        suffix: prefill.suffix ?? '',
        date_of_birth: prefill.date_of_birth ?? '',
        sex: prefill.sex ?? '',
        contact_number: prefill.contact_number ?? '',
        address: {
            region: prefill.address?.region ?? '',
            province: prefill.address?.province ?? '',
            city_municipality: prefill.address?.city_municipality ?? '',
            barangay: prefill.address?.barangay ?? '',
            street: prefill.address?.street ?? '',
        },
        employment: {
            employer_name: prefill.employment?.employer_name ?? '',
            position: prefill.employment?.position ?? '',
            country: prefill.employment?.country ?? '',
            start_date: prefill.employment?.start_date ?? '',
            end_date: prefill.employment?.end_date ?? '',
            is_present: prefill.employment?.is_present ?? false,
            last_country: prefill.employment?.last_country ?? '',
            last_position: prefill.employment?.last_position ?? '',
            date_of_arrival: prefill.employment?.date_of_arrival ?? '',
        },
    });

    const schema = useMemo(() => makeRegisterSchema(passwordRules), [passwordRules]);
    const { validate } = useClientValidation(schema, data, setError);

    // Occupation dropdown options — same curated DMW/PSA-based OFW list as
    // Case/Create.jsx (DEFAULT_OCCUPATIONS), shaped for SearchableSelect.
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

    // Lightweight gate for the Continue button (no error writes) —
    // Case/Create.jsx canProceed pattern, collapsed across every section.
    function allFieldsComplete() {
        return (
            data.type.length > 0
            && data.date_of_birth.length > 0
            && data.date_of_birth <= DOB_BOUNDS.max
            && data.date_of_birth >= DOB_BOUNDS.min
            && data.sex.length > 0
            && data.contact_number.trim().length > 0
            && data.address.region.length > 0
            && (!regionRequiresProvince(data.address.region) || data.address.province.length > 0)
            && data.address.city_municipality.length > 0
            && data.address.barangay.length > 0
            && data.employment.employer_name.trim().length > 0
            && data.employment.start_date.length > 0
            && (data.employment.is_present || data.employment.end_date.length > 0)
            && data.employment.last_country.length > 0
            && data.employment.last_position.trim().length > 0
            && data.employment.date_of_arrival.length > 0
        );
    }

    function getMissingFields() {
        const missing = [];
        if (!data.date_of_birth) missing.push('Date of Birth');
        if (!data.sex) missing.push('Sex');
        if (!data.contact_number.trim()) missing.push('Contact Number');
        if (!data.address.region) missing.push('Region');
        if (regionRequiresProvince(data.address.region) && !data.address.province) missing.push('Province');
        if (!data.address.city_municipality) missing.push('City/Municipality');
        if (!data.address.barangay) missing.push('Barangay');
        if (!data.employment.employer_name.trim()) missing.push('Employer Name');
        if (!data.employment.start_date) missing.push('Start Date');
        if (!data.employment.is_present && !data.employment.end_date) missing.push('End Date');
        if (!data.employment.last_country) missing.push('Last Country');
        if (!data.employment.last_position.trim()) missing.push('Last Occupation');
        if (!data.employment.date_of_arrival) missing.push('Arrival Date');
        return missing;
    }

    const missingFields = getMissingFields();

    const submit = (e) => {
        e.preventDefault();
        clearErrors();
        if (!validate()) return;

        setData('cf_turnstile_response', turnstileToken);
        post(route('register.store'), {
            onFinish: () => { reset('password', 'password_confirmation'); },
        });
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Register" />

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

                    {/* Right: Form — the whole profile is captured here, so the
                        column needs room for roughly three screens of fields. */}
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 lg:min-h-[max(1200px,calc(100dvh-72px))] bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    person_add
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Create your account
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Step 1 of 2 — tell us about you. You'll verify your email on the next screen.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
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

                                {/* Suffix */}
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

                                {/* Name */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            First Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
                                            className={inputClass}
                                            autoComplete="given-name"
                                            autoFocus
                                            required
                                            maxLength={255}
                                        />
                                        {errors.first_name && (
                                            <p className="mt-1 text-xs text-red-600">{errors.first_name}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Last Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                            className={inputClass}
                                            autoComplete="family-name"
                                            required
                                            maxLength={255}
                                        />
                                        {errors.last_name && (
                                            <p className="mt-1 text-xs text-red-600">{errors.last_name}</p>
                                        )}
                                    </div>
                                    <div className="col-span-2">
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Middle Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.middle_name}
                                            onChange={(e) => setData('middle_name', e.target.value)}
                                            className={inputClass}
                                            autoComplete="additional-name"
                                            maxLength={255}
                                        />
                                        {errors.middle_name && (
                                            <p className="mt-1 text-xs text-red-600">{errors.middle_name}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Email */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        Email Address
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
                                            alternate_email
                                        </span>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 text-sm focus:border-primary focus:outline-none rounded-md"
                                            autoComplete="username"
                                            required
                                        />
                                    </div>
                                    {errors.email && (
                                        <p className="mt-1 text-xs text-red-600">{errors.email}</p>
                                    )}
                                    {mustResetPassword && (
                                        <p className="mt-1 text-xs text-amber-600">
                                            Re-enter your password to continue.
                                        </p>
                                    )}
                                </div>

                                {/* Password */}
                                <PasswordInput
                                    id="register-password"
                                    label="Password"
                                    value={data.password}
                                    onChange={(v) => setData('password', v)}
                                    error={errors.password}
                                    autoComplete="new-password"
                                    showStrengthMeter
                                    rules={passwordRules}
                                    confirmation={data.password_confirmation}
                                />

                                {/* Confirm Password */}
                                <PasswordInput
                                    id="register-password-confirm"
                                    label="Confirm Password"
                                    value={data.password_confirmation}
                                    onChange={(v) => setData('password_confirmation', v)}
                                    error={errors.password_confirmation}
                                    autoComplete="new-password"
                                />

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

                                {/* Employment */}
                                <div className="space-y-4">
                                    <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">
                                        Employment
                                    </h4>

                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Employer Name <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            value={data.employment.employer_name}
                                            onChange={(e) => handleEmploymentChange('employer_name', e.target.value)}
                                            className={inputClass}
                                            maxLength={255}
                                            required
                                        />
                                        <FieldError message={errors['employment.employer_name']} />
                                    </div>

                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Current Position / Job Title
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
                                            {data.employment.is_present ? (
                                                <div className="w-full border border-outline-variant bg-surface-container/60 px-4 py-3 text-sm rounded-md text-slate-600">
                                                    Present
                                                </div>
                                            ) : (
                                                <input
                                                    type="date"
                                                    value={data.employment.end_date}
                                                    onChange={(e) => handleEmploymentChange('end_date', e.target.value)}
                                                    min={data.employment.start_date || undefined}
                                                    max={TODAY}
                                                    className={inputClass}
                                                />
                                            )}
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

                                {turnstile?.enabled && (
                                    <div className="text-center">
                                        <TurnstileWidget
                                            onToken={setTurnstileToken}
                                            onExpire={() => setTurnstileToken('')}
                                            onStatusChange={setTurnstileStatus}
                                        />
                                        {errors.captcha && (
                                            <p className="mt-1 text-xs text-red-600">{errors.captcha}</p>
                                        )}
                                        {!turnstileToken && (
                                            <p className={`mt-1 text-xs ${turnstileStatus === 'error' || turnstileStatus === 'expired' ? 'text-red-600' : 'text-slate-500'}`}>
                                                {getTurnstileError({ token: turnstileToken, status: turnstileStatus })}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {!allFieldsComplete() && missingFields.length > 0 && (
                                    <p className="text-[11px] text-amber-600 leading-tight">
                                        Complete: {missingFields.join(', ')}
                                    </p>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing || !allFieldsComplete() || (turnstile?.enabled && !turnstileToken)}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-md"
                                >
                                    {processing ? 'Saving...' : 'Continue to Review'}
                                </button>

                                <div className="text-center">
                                    <Link
                                        href={route('login')}
                                        className="text-xs font-bold text-on-surface-variant hover:text-primary transition-colors"
                                    >
                                        Already have an account? Sign in
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
