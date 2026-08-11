import { useMemo, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { z } from 'zod';
import OfwLayout from '@/Layouts/OfwLayout';
import PasswordInput from '@/Components/PasswordInput';
import createPasswordSchema from '@/utils/createPasswordSchema';
import useClientValidation from '@/Hooks/useClientValidation';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { getAvatarColor } from '@/Components/ui/UserAvatar';

const MAX = 'Must be 255 characters or fewer.';

/* ---------- Facebook-style building blocks ---------- */

function Card({ id, children, className = '' }) {
    return (
        <div id={id} className={`scroll-mt-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}>
            {children}
        </div>
    );
}

function SectionHeader({ icon, title, description, badge }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
            <div className="flex items-start gap-3">
                <span className="material-symbols-outlined mt-0.5 text-[20px] text-primary" aria-hidden="true">
                    {icon}
                </span>
                <div>
                    <h2 className="font-headline text-[15px] font-bold tracking-tight text-slate-900">{title}</h2>
                    {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
                </div>
            </div>
            {badge}
        </div>
    );
}

function ReadOnlyBadge() {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">
            <span className="material-symbols-outlined text-[13px]" aria-hidden="true">lock</span>
            Read-only
        </span>
    );
}

function IntroRow({ icon, label, value }) {
    if (!value) return null;
    return (
        <div className="flex items-start gap-3 text-sm text-slate-700">
            <span className="material-symbols-outlined mt-0.5 text-[18px] text-slate-400" aria-hidden="true">
                {icon}
            </span>
            <div className="min-w-0">
                <p className="truncate">{value}</p>
                {label && <p className="text-xs text-slate-400">{label}</p>}
            </div>
        </div>
    );
}

function Field({ label, htmlFor, error, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function TextInput({ id, label, value, onChange, error, type = 'text', placeholder, maxLength, autoComplete }) {
    return (
        <Field label={label} htmlFor={id} error={error}>
            <input
                id={id}
                type={type}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                maxLength={maxLength}
                autoComplete={autoComplete}
                className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
        </Field>
    );
}

function SaveButton({ processing, children }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
        >
            {processing && (
                <span className="material-symbols-outlined animate-spin text-[18px]" aria-hidden="true">progress_activity</span>
            )}
            {children}
        </button>
    );
}

function formatDate(value) {
    if (!value) return null;
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-PH', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatSex(value) {
    if (!value) return null;
    return value.charAt(0) + value.slice(1).toLowerCase();
}

const EMPTY_NOK_ROW = {
    first_name: '',
    middle_initial: '',
    last_name: '',
    relationship: '',
    phone_number: '',
    email: '',
    region: '',
    province: '',
    city_municipality: '',
    barangay: '',
    street: '',
};

const NAV_TABS = [
    { id: 'section-identity', label: 'About', icon: 'person' },
    { id: 'section-contact', label: 'Contact', icon: 'call' },
    { id: 'section-address', label: 'Address', icon: 'home_pin' },
    { id: 'section-employment', label: 'Employment', icon: 'work' },
    { id: 'section-nok', label: 'Family & Friends', icon: 'group' },
    { id: 'section-security', label: 'Security', icon: 'password' },
];

export default function Profile({ user, client }) {
    const { passwordRules } = usePage().props;
    const [activeTab, setActiveTab] = useState(NAV_TABS[0].id);

    const fullName = client
        ? [client.first_name, client.middle_initial, client.last_name].filter(Boolean).join(' ')
        : (user?.name ?? '');
    const initials = (client
        ? [client.first_name, client.last_name].filter(Boolean).map((s) => s[0])
        : []
    ).join('').toUpperCase() || (user?.name ? user.name.slice(0, 2).toUpperCase() : 'OFW');

    const contactForm = useForm({
        contact_number: client?.contact_number ?? '',
    });

    const addressForm = useForm({
        region: client?.address?.region ?? '',
        province: client?.address?.province ?? '',
        city_municipality: client?.address?.city_municipality ?? '',
        barangay: client?.address?.barangay ?? '',
        street: client?.address?.street ?? '',
    });

    const employmentForm = useForm({
        employer_name: client?.employment?.employer_name ?? '',
        position: client?.employment?.position ?? '',
        country: client?.employment?.country ?? '',
        start_date: client?.employment?.start_date ?? '',
        end_date: client?.employment?.end_date ?? '',
        last_country: client?.employment?.last_country ?? '',
        last_position: client?.employment?.last_position ?? '',
        date_of_arrival: client?.employment?.date_of_arrival ?? '',
    });

    const nokForm = useForm({
        next_of_kin: client?.next_of_kin ?? [],
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const contactSchema = useMemo(() => z.object({
        contact_number: z.string().max(20, 'Contact number must be 20 characters or fewer.'),
    }), []);

    const addressSchema = useMemo(() => z.object({
        region: z.string().max(255, MAX),
        province: z.string().max(255, MAX),
        city_municipality: z.string().max(255, MAX),
        barangay: z.string().max(255, MAX),
        street: z.string().max(255, MAX),
    }), []);

    const employmentSchema = useMemo(() => z.object({
        employer_name: z.string().max(255, MAX),
        position: z.string().max(255, MAX),
        country: z.string().max(255, MAX),
        start_date: z.string().max(10, 'Enter a valid date.'),
        end_date: z.string().max(10, 'Enter a valid date.'),
        last_country: z.string().max(255, MAX),
        last_position: z.string().max(255, MAX),
        date_of_arrival: z.string().max(10, 'Enter a valid date.'),
    }), []);

    const nokSchema = useMemo(() => z.object({
        next_of_kin: z.array(z.object({
            first_name: z.string().max(255, MAX).optional(),
            middle_initial: z.string().max(10, 'Must be 10 characters or fewer.').optional(),
            last_name: z.string().max(255, MAX).optional(),
            relationship: z.string().max(255, MAX).optional(),
            phone_number: z.string().max(20, 'Contact number must be 20 characters or fewer.').optional(),
            email: z.string().email('Enter a valid email address.').optional().or(z.literal('')),
            region: z.string().max(255, MAX).optional(),
            province: z.string().max(255, MAX).optional(),
            city_municipality: z.string().max(255, MAX).optional(),
            barangay: z.string().max(255, MAX).optional(),
            street: z.string().max(255, MAX).optional(),
        })),
    }), []);

    // Mirrors the server rules: current password is only required when a new
    // password is actually being set. Leave everything blank to keep it.
    const passwordSchema = useMemo(() => z.object({
        current_password: z.string().optional(),
        password: createPasswordSchema(passwordRules).or(z.literal('')),
        password_confirmation: z.string(),
    }).superRefine((data, ctx) => {
        if (!data.password) return;
        if (!data.current_password) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Current password is required.', path: ['current_password'] });
        }
        if (data.password !== data.password_confirmation) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Passwords do not match.', path: ['password_confirmation'] });
        }
    }), [passwordRules]);

    const contactValidation = useClientValidation(contactSchema, contactForm.data, contactForm.setError);
    const addressValidation = useClientValidation(addressSchema, addressForm.data, addressForm.setError);
    const employmentValidation = useClientValidation(employmentSchema, employmentForm.data, employmentForm.setError);
    const passwordValidation = useClientValidation(passwordSchema, passwordForm.data, passwordForm.setError);

    function handleContactSubmit(e) {
        e.preventDefault();
        contactForm.clearErrors();
        if (!contactValidation.validate()) return;
        contactForm.put(route('ofw.profile.update'), {
            preserveScroll: true,
            onSuccess: (page) => {
                contactForm.setData('contact_number', page.props.client?.contact_number ?? '');
            },
        });
    }

    function handleAddressSubmit(e) {
        e.preventDefault();
        addressForm.clearErrors();
        if (!addressValidation.validate()) return;
        addressForm.transform((data) => ({ address: data }));
        addressForm.put(route('ofw.profile.update'), {
                preserveScroll: true,
                onSuccess: (page) => {
                    const a = page.props.client?.address ?? {};
                    addressForm.setData({
                        region: a.region ?? '',
                        province: a.province ?? '',
                        city_municipality: a.city_municipality ?? '',
                        barangay: a.barangay ?? '',
                        street: a.street ?? '',
                    });
                },
            });
    }

    function handleEmploymentSubmit(e) {
        e.preventDefault();
        employmentForm.clearErrors();
        if (!employmentValidation.validate()) return;
        employmentForm.transform((data) => ({ employment: data }));
        employmentForm.put(route('ofw.profile.update'), {
                preserveScroll: true,
                onSuccess: (page) => {
                    const emp = page.props.client?.employment ?? {};
                    employmentForm.setData({
                        employer_name: emp.employer_name ?? '',
                        position: emp.position ?? '',
                        country: emp.country ?? '',
                        start_date: emp.start_date ?? '',
                        end_date: emp.end_date ?? '',
                        last_country: emp.last_country ?? '',
                        last_position: emp.last_position ?? '',
                        date_of_arrival: emp.date_of_arrival ?? '',
                    });
                },
            });
    }

    function handleNokSubmit(e) {
        e.preventDefault();
        nokForm.clearErrors();
        const cleaned = (nokForm.data.next_of_kin ?? []).filter(
            (row) => (row?.first_name || '').trim() || (row?.last_name || '').trim(),
        );
        const result = nokSchema.safeParse({ next_of_kin: cleaned });
        if (!result.success) {
            for (const issue of result.error.issues) {
                nokForm.setError(issue.path.join('.'), issue.message);
            }
            return;
        }
        nokForm.setData('next_of_kin', cleaned);
        nokForm.put(route('ofw.profile.update'), {
            preserveScroll: true,
            onSuccess: (page) => {
                nokForm.setData('next_of_kin', page.props.client?.next_of_kin ?? []);
            },
        });
    }

    function handlePasswordSubmit(e) {
        e.preventDefault();
        passwordForm.clearErrors();
        if (!passwordValidation.validate()) return;
        passwordForm.put(route('ofw.profile.update'), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset('current_password', 'password', 'password_confirmation'),
        });
    }

    function addNokRow() {
        nokForm.setData('next_of_kin', [...nokForm.data.next_of_kin, { ...EMPTY_NOK_ROW }]);
    }

    function removeNokRow(index) {
        nokForm.setData('next_of_kin', nokForm.data.next_of_kin.filter((_, i) => i !== index));
    }

    function setNokField(index, field, value) {
        nokForm.setData(`next_of_kin.${index}.${field}`, value);
    }

    const nokError = (index, field) => nokForm.errors[`next_of_kin.${index}.${field}`];

    const dirty = contactForm.isDirty
        || addressForm.isDirty
        || employmentForm.isDirty
        || nokForm.isDirty
        || passwordForm.isDirty;
    const { UnsavedModal } = useUnsavedChanges(dirty);

    function scrollToSection(id) {
        setActiveTab(id);
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    return (
        <OfwLayout title="Profile">
            <Link
                href={route('ofw.dashboard')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Back to My Cases
            </Link>

            {/* ---------- Facebook-style cover + avatar header ---------- */}
            <div className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="relative h-36 w-full bg-primary sm:h-52">
                    <div className="absolute inset-0 bg-black/10" />
                </div>

                <div className="relative px-5 pb-5 sm:px-8">
                    <div className="flex flex-col items-center sm:flex-row sm:items-end sm:gap-5">
                        <div className="-mt-14 shrink-0 sm:-mt-16">
                            {client?.avatar_url ? (
                                <img
                                    src={client.avatar_url}
                                    alt=""
                                    className="h-28 w-28 rounded-circle border-4 border-white object-cover shadow-md sm:h-36 sm:w-36"
                                    onError={(e) => {
                                        e.currentTarget.style.display = 'none';
                                        e.currentTarget.nextElementSibling?.classList.remove('hidden');
                                    }}
                                />
                            ) : null}
                            <span
                                className={`avatar-fallback ${client?.avatar_url ? 'hidden' : ''} flex h-28 w-28 items-center justify-center rounded-circle border-4 border-white ${getAvatarColor(fullName)} font-headline text-3xl font-bold text-white shadow-md sm:h-36 sm:w-36 sm:text-4xl`}
                            >
                                {initials}
                            </span>
                        </div>

                        <div className="mt-3 min-w-0 flex-1 text-center sm:mt-0 sm:pb-2 sm:text-left">
                            <h1 className="truncate font-headline text-2xl font-extrabold tracking-tight text-slate-900">
                                {fullName}
                            </h1>
                            <p className="mt-0.5 truncate text-sm text-slate-500">{user.email}</p>
                        </div>

                        {client && (
                            <div className="mt-3 hidden shrink-0 sm:mt-0 sm:block sm:pb-2">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                    <span className="material-symbols-outlined text-[15px]" aria-hidden="true">verified</span>
                                    Verified Client
                                </span>
                            </div>
                        )}
                    </div>

                    {client && (
                        <nav className="mt-5 -mb-5 flex gap-1 overflow-x-auto border-t border-slate-100 pt-1 sm:mt-6">
                            {NAV_TABS.map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => scrollToSection(tab.id)}
                                    className={`flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-3 text-sm font-semibold transition-colors ${
                                        activeTab === tab.id
                                            ? 'border-primary text-primary'
                                            : 'border-transparent text-slate-500 hover:border-slate-200 hover:text-slate-800'
                                    }`}
                                >
                                    <span className="material-symbols-outlined text-[17px]" aria-hidden="true">{tab.icon}</span>
                                    {tab.label}
                                </button>
                            ))}
                        </nav>
                    )}
                </div>
            </div>

            {!client ? (
                <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    We don't have a complete client record linked to your account yet. Contact your case manager
                    to update your details.
                </div>
            ) : (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,320px)_1fr]">
                    {/* ---------- Left column: Intro sidebar ---------- */}
                    <div className="space-y-6 lg:sticky lg:top-6 lg:self-start">
                        <Card>
                            <SectionHeader icon="info" title="Intro" badge={<ReadOnlyBadge />} />
                            <div className="mt-4 space-y-3">
                                <IntroRow icon="cake" label="Date of Birth" value={formatDate(client.date_of_birth)} />
                                <IntroRow icon="wc" label="Sex" value={formatSex(client.sex)} />
                                <IntroRow icon="badge" label="Suffix" value={client.suffix} />
                                <IntroRow icon="call" label="Contact Number" value={contactForm.data.contact_number} />
                                <IntroRow
                                    icon="home_pin"
                                    label="Address"
                                    value={[addressForm.data.city_municipality, addressForm.data.province]
                                        .filter(Boolean)
                                        .join(', ')}
                                />
                                <IntroRow
                                    icon="work"
                                    label="Employment"
                                    value={[employmentForm.data.position, employmentForm.data.employer_name]
                                        .filter(Boolean)
                                        .join(' at ')}
                                />
                            </div>
                            <p className="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">
                                Identity details are verified by our office. Contact your case manager to correct any of these.
                            </p>
                        </Card>

                        {nokForm.data.next_of_kin.length > 0 && (
                            <Card>
                                <SectionHeader icon="group" title="Family & Friends" />
                                <ul className="mt-4 space-y-3">
                                    {nokForm.data.next_of_kin.slice(0, 4).map((row, i) => {
                                        const name = [row.first_name, row.last_name].filter(Boolean).join(' ') || `Contact ${i + 1}`;
                                        return (
                                            <li key={i} className="flex items-center gap-3">
                                                <span className={`avatar-fallback flex h-9 w-9 shrink-0 items-center justify-center rounded-circle ${getAvatarColor(name)} text-xs font-bold text-white`}>
                                                    {name.slice(0, 2).toUpperCase()}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-slate-800">{name}</p>
                                                    {row.relationship && (
                                                        <p className="truncate text-xs text-slate-400">{row.relationship}</p>
                                                    )}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </Card>
                        )}
                    </div>

                    {/* ---------- Right column: editable sections as a feed ---------- */}
                    <div className="space-y-6">
                        <Card id="section-identity">
                            <SectionHeader
                                icon="badge"
                                title="Identity"
                                badge={<ReadOnlyBadge />}
                                description="Verified by our office. Contact your case manager to correct any of these."
                            />
                            <dl className="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                                <div>
                                    <dt className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Date of Birth</dt>
                                    <dd className="mt-1 text-sm font-medium text-slate-800">{formatDate(client.date_of_birth) || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Sex</dt>
                                    <dd className="mt-1 text-sm font-medium text-slate-800">{formatSex(client.sex) || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Suffix</dt>
                                    <dd className="mt-1 text-sm font-medium text-slate-800">{client.suffix || '—'}</dd>
                                </div>
                            </dl>
                        </Card>

                        <form onSubmit={handleContactSubmit}>
                            <Card id="section-contact">
                                <SectionHeader
                                    icon="call"
                                    title="Contact Information"
                                    description="Your mobile number. Changes are recorded and sent to your case manager."
                                />
                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <TextInput
                                        id="contact_number"
                                        label="Contact Number"
                                        value={contactForm.data.contact_number}
                                        onChange={(v) => contactForm.setData('contact_number', v)}
                                        error={contactForm.errors.contact_number}
                                        type="tel"
                                        placeholder="e.g. 09171234567"
                                        maxLength={20}
                                        autoComplete="tel"
                                    />
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <SaveButton processing={contactForm.processing}>Save Contact</SaveButton>
                                </div>
                            </Card>
                        </form>

                        <form onSubmit={handleAddressSubmit}>
                            <Card id="section-address">
                                <SectionHeader
                                    icon="home_pin"
                                    title="Address"
                                    description="Where we can reach you while abroad or at home."
                                />
                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <TextInput id="address_region" label="Region" value={addressForm.data.region} onChange={(v) => addressForm.setData('region', v)} error={addressForm.errors.region} />
                                    <TextInput id="address_province" label="Province" value={addressForm.data.province} onChange={(v) => addressForm.setData('province', v)} error={addressForm.errors.province} />
                                    <TextInput id="address_city" label="City / Municipality" value={addressForm.data.city_municipality} onChange={(v) => addressForm.setData('city_municipality', v)} error={addressForm.errors.city_municipality} />
                                    <TextInput id="address_barangay" label="Barangay" value={addressForm.data.barangay} onChange={(v) => addressForm.setData('barangay', v)} error={addressForm.errors.barangay} />
                                    <div className="sm:col-span-2">
                                        <TextInput id="address_street" label="House No. / Street" value={addressForm.data.street} onChange={(v) => addressForm.setData('street', v)} error={addressForm.errors.street} />
                                    </div>
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <SaveButton processing={addressForm.processing}>Save Address</SaveButton>
                                </div>
                            </Card>
                        </form>

                        <form onSubmit={handleEmploymentSubmit}>
                            <Card id="section-employment">
                                <SectionHeader
                                    icon="work"
                                    title="Employment"
                                    description="Your current or most recent employment details."
                                />
                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <TextInput id="emp_employer" label="Employer / Agency" value={employmentForm.data.employer_name} onChange={(v) => employmentForm.setData('employer_name', v)} error={employmentForm.errors.employer_name} />
                                    <TextInput id="emp_position" label="Position" value={employmentForm.data.position} onChange={(v) => employmentForm.setData('position', v)} error={employmentForm.errors.position} />
                                    <TextInput id="emp_country" label="Country" value={employmentForm.data.country} onChange={(v) => employmentForm.setData('country', v)} error={employmentForm.errors.country} />
                                    <TextInput id="emp_start" label="Start Date" type="date" value={employmentForm.data.start_date} onChange={(v) => employmentForm.setData('start_date', v)} error={employmentForm.errors.start_date} />
                                    <TextInput id="emp_end" label="End Date" type="date" value={employmentForm.data.end_date} onChange={(v) => employmentForm.setData('end_date', v)} error={employmentForm.errors.end_date} />
                                    <TextInput id="emp_last_country" label="Last Country" value={employmentForm.data.last_country} onChange={(v) => employmentForm.setData('last_country', v)} error={employmentForm.errors.last_country} />
                                    <TextInput id="emp_last_position" label="Last Position" value={employmentForm.data.last_position} onChange={(v) => employmentForm.setData('last_position', v)} error={employmentForm.errors.last_position} />
                                    <TextInput id="emp_arrival" label="Date of Arrival" type="date" value={employmentForm.data.date_of_arrival} onChange={(v) => employmentForm.setData('date_of_arrival', v)} error={employmentForm.errors.date_of_arrival} />
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <SaveButton processing={employmentForm.processing}>Save Employment</SaveButton>
                                </div>
                            </Card>
                        </form>

                        <form onSubmit={handleNokSubmit}>
                            <Card id="section-nok">
                                <SectionHeader
                                    icon="group"
                                    title="Next of Kin"
                                    description="People we can contact on your behalf. Keep at least one."
                                />
                                <div className="mt-4 space-y-4">
                                    {nokForm.data.next_of_kin.map((row, i) => (
                                        <div key={i} className="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-semibold text-slate-700">Contact {i + 1}</p>
                                                <button
                                                    type="button"
                                                    onClick={() => removeNokRow(i)}
                                                    className="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700"
                                                >
                                                    <span className="material-symbols-outlined text-[15px]" aria-hidden="true">delete</span>
                                                    Remove
                                                </button>
                                            </div>
                                            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                <TextInput id={`nok_${i}_first`} label="First Name" value={row.first_name ?? ''} onChange={(v) => setNokField(i, 'first_name', v)} error={nokError(i, 'first_name')} />
                                                <TextInput id={`nok_${i}_middle`} label="Middle Initial" value={row.middle_initial ?? ''} onChange={(v) => setNokField(i, 'middle_initial', v)} error={nokError(i, 'middle_initial')} maxLength={10} />
                                                <TextInput id={`nok_${i}_last`} label="Last Name" value={row.last_name ?? ''} onChange={(v) => setNokField(i, 'last_name', v)} error={nokError(i, 'last_name')} />
                                                <TextInput id={`nok_${i}_rel`} label="Relationship" value={row.relationship ?? ''} onChange={(v) => setNokField(i, 'relationship', v)} error={nokError(i, 'relationship')} />
                                                <TextInput id={`nok_${i}_phone`} label="Phone Number" type="tel" value={row.phone_number ?? ''} onChange={(v) => setNokField(i, 'phone_number', v)} error={nokError(i, 'phone_number')} maxLength={20} />
                                                <TextInput id={`nok_${i}_email`} label="Email" type="email" value={row.email ?? ''} onChange={(v) => setNokField(i, 'email', v)} error={nokError(i, 'email')} />
                                                <TextInput id={`nok_${i}_region`} label="Region" value={row.region ?? ''} onChange={(v) => setNokField(i, 'region', v)} error={nokError(i, 'region')} />
                                                <TextInput id={`nok_${i}_province`} label="Province" value={row.province ?? ''} onChange={(v) => setNokField(i, 'province', v)} error={nokError(i, 'province')} />
                                                <TextInput id={`nok_${i}_city`} label="City / Municipality" value={row.city_municipality ?? ''} onChange={(v) => setNokField(i, 'city_municipality', v)} error={nokError(i, 'city_municipality')} />
                                                <TextInput id={`nok_${i}_barangay`} label="Barangay" value={row.barangay ?? ''} onChange={(v) => setNokField(i, 'barangay', v)} error={nokError(i, 'barangay')} />
                                                <div className="sm:col-span-3">
                                                    <TextInput id={`nok_${i}_street`} label="House No. / Street" value={row.street ?? ''} onChange={(v) => setNokField(i, 'street', v)} error={nokError(i, 'street')} />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={addNokRow}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-primary/40 hover:text-primary"
                                    >
                                        <span className="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>
                                        Add Contact
                                    </button>
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <SaveButton processing={nokForm.processing}>Save Next of Kin</SaveButton>
                                </div>
                            </Card>
                        </form>

                        <form onSubmit={handlePasswordSubmit}>
                            <Card id="section-security">
                                <SectionHeader
                                    icon="password"
                                    title="Change Password"
                                    description="Leave the new password fields blank to keep your current password."
                                />
                                <div className="mt-4 grid grid-cols-1 gap-4">
                                    <PasswordInput
                                        id="ofw-current-password"
                                        label="Current Password"
                                        value={passwordForm.data.current_password}
                                        onChange={(v) => passwordForm.setData('current_password', v)}
                                        error={passwordForm.errors.current_password}
                                        autoComplete="current-password"
                                    />
                                    <PasswordInput
                                        id="ofw-new-password"
                                        label="New Password"
                                        value={passwordForm.data.password}
                                        onChange={(v) => passwordForm.setData('password', v)}
                                        error={passwordForm.errors.password}
                                        autoComplete="new-password"
                                        showStrengthMeter
                                        rules={passwordRules}
                                        confirmation={passwordForm.data.password_confirmation}
                                    />
                                    <PasswordInput
                                        id="ofw-confirm-password"
                                        label="Confirm New Password"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(v) => passwordForm.setData('password_confirmation', v)}
                                        error={passwordForm.errors.password_confirmation}
                                        autoComplete="new-password"
                                    />
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <SaveButton processing={passwordForm.processing}>Save Password</SaveButton>
                                </div>
                            </Card>
                        </form>
                    </div>
                </div>
            )}

            {!client && (
                <form onSubmit={handlePasswordSubmit} className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <SectionHeader
                        icon="password"
                        title="Change Password"
                        description="Leave the new password fields blank to keep your current password."
                    />
                    <div className="mt-4 grid grid-cols-1 gap-4">
                        <PasswordInput
                            id="ofw-current-password"
                            label="Current Password"
                            value={passwordForm.data.current_password}
                            onChange={(v) => passwordForm.setData('current_password', v)}
                            error={passwordForm.errors.current_password}
                            autoComplete="current-password"
                        />
                        <PasswordInput
                            id="ofw-new-password"
                            label="New Password"
                            value={passwordForm.data.password}
                            onChange={(v) => passwordForm.setData('password', v)}
                            error={passwordForm.errors.password}
                            autoComplete="new-password"
                            showStrengthMeter
                            rules={passwordRules}
                            confirmation={passwordForm.data.password_confirmation}
                        />
                        <PasswordInput
                            id="ofw-confirm-password"
                            label="Confirm New Password"
                            value={passwordForm.data.password_confirmation}
                            onChange={(v) => passwordForm.setData('password_confirmation', v)}
                            error={passwordForm.errors.password_confirmation}
                            autoComplete="new-password"
                        />
                    </div>
                    <div className="mt-4 flex justify-end">
                        <SaveButton processing={passwordForm.processing}>Save Password</SaveButton>
                    </div>
                </form>
            )}

            {UnsavedModal}
        </OfwLayout>
    );
}