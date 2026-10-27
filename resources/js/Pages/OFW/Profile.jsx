import { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { z } from 'zod';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import PasswordInput from '@/Components/PasswordInput';
import createPasswordSchema from '@/utils/createPasswordSchema';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { getAvatarColor } from '@/Components/ui/UserAvatar';
import CountrySelect from '@/Components/CountrySelect';
import SearchableSelect from '@/Components/SearchableSelect';
import AddressDropdowns from '@/Components/AddressDropdowns';
import { DEFAULT_OCCUPATIONS } from '@/data/defaultOccupations';
import { resolveAddressToCodes, addressCodesToNames } from '@/data/philippine-addresses';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import { useToast } from '@/Hooks/useToast';
import safeRoute from '@/utils/safeRoute';

/**
 * My Profile.
 *
 * One card, one form, one Save and one Discard. The card is divided into
 * titled sections — Account, Contact, Home address, Employment, Family &
 * friends, Password — so the whole record reads top to bottom without
 * hopping between separate cards.
 *
 * Identity details (name, date of birth, email) are staff-owned: they are
 * shown read-only and never included in the save payload.
 */

const MAX = 'Must be 255 characters or fewer.';

const SUFFIX_OPTIONS = ['Jr', 'Sr', 'II', 'III', 'IV', 'V'];
const SEX_OPTIONS = ['Male', 'Female'];
const NOK_RELATIONSHIP_OPTIONS = ['Mother', 'Father', 'Spouse', 'Sibling', 'Child', 'Guardian', 'Other'];

const EMPTY_NOK_ROW = {
    first_name: '',
    middle_name: '',
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

/* ---------- Compact section building blocks ---------- */

/** A titled section inside the single profile card. */
function Section({ title, description, action, children }) {
    return (
        <section className="border-b border-slate-100 px-5 py-5 last:border-b-0 sm:px-6">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                        {title}
                    </h2>
                    {description ? <p className="mt-0.5 text-[12px] text-slate-500">{description}</p> : null}
                </div>
                {action ?? null}
            </div>
            {children}
        </section>
    );
}

function Field({ label, htmlFor, error, hint, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">
                {label}
            </label>
            {children}
            {hint && !error ? <p className="mt-1 text-[11px] text-slate-400">{hint}</p> : null}
            {error ? <p className="mt-1 text-[12px] font-medium text-rose-600">{error}</p> : null}
        </div>
    );
}

const INPUT_CLASS =
    'mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-[13px] text-slate-900 transition-colors placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';

function TextInput({ id, label, value, onChange, error, hint, type = 'text', placeholder, maxLength, autoComplete, disabled }) {
    return (
        <Field label={label} htmlFor={id} error={error} hint={hint}>
            <input
                id={id}
                type={type}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                maxLength={maxLength}
                autoComplete={autoComplete}
                disabled={disabled}
                className={INPUT_CLASS}
            />
        </Field>
    );
}

/** A read-only labelled value, used for staff-owned details. */
function StaticField({ label, value }) {
    return (
        <div>
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">{label}</p>
            <p className="mt-1.5 truncate text-[13px] text-slate-700">{value || '—'}</p>
        </div>
    );
}

/** "Use my home address" toggle for a next-of-kin contact. */
function SameAddressToggle({ checked, onChange, disabled }) {
    return (
        <label className={`inline-flex items-center gap-2 text-[12px] ${disabled ? 'cursor-not-allowed text-slate-400' : 'cursor-pointer text-slate-600'}`}>
            <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(e) => onChange(e.target.checked)}
                className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
            />
            Use the same address as my home address
        </label>
    );
}

export default function Profile({ user, client }) {
    const { passwordRules, occupationOptions } = usePage().props;
    // Another lane owns the controller; fall back so the meter/schema still
    // enforce a sensible policy when the prop is absent.
    const effectivePasswordRules = passwordRules ?? { min_length: 8 };
    const toast = useToast();

    // First · Middle · Last · Suffix — the order the profile record presents.
    const fullName = client
        ? [client.first_name, client.middle_name, client.last_name, client.suffix]
            .map((part) => (part ?? '').toString().trim())
            .filter(Boolean)
            .join(' ')
        : (user?.name ?? '');

    const homeAddress = {
        ...resolveAddressToCodes(client?.address),
        street: client?.address?.street ?? '',
    };

    /** Build the single form payload from the current client record. */
    const buildInitialData = () => ({
        sex: client?.sex ?? '',
        suffix: client?.suffix ?? '',
        contact_number: client?.contact_number ?? '',
        address: homeAddress,
        employment: {
            employer_name: client?.employment?.employer_name ?? '',
            position: client?.employment?.position ?? '',
            country: client?.employment?.country ?? '',
            start_date: client?.employment?.start_date ?? '',
            end_date: client?.employment?.end_date ?? '',
            // The payload carries no is_present flag (presence is end_date
            // being empty), so derive it the way Case/Create.jsx does.
            is_present: client?.employment?.is_present ?? (client?.employment ? !client.employment.end_date : false),
            last_country: client?.employment?.last_country ?? '',
            last_position: client?.employment?.last_position ?? '',
            date_of_arrival: client?.employment?.date_of_arrival ?? '',
        },
        next_of_kin: client?.next_of_kin ?? [],
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const form = useForm(buildInitialData());
    const [savedSnapshot, setSavedSnapshot] = useState(() => JSON.stringify(buildInitialData()));

    /** Next-of-kin rows where the citizen ticked "same as my address". */
    const [sameAddressRows, setSameAddressRows] = useState({});

    /* ---------- Client-side validation ---------- */

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
        is_present: z.boolean().optional(),
        last_country: z.string().max(255, MAX),
        last_position: z.string().max(255, MAX),
        date_of_arrival: z.string().max(10, 'Enter a valid date.'),
    }).superRefine((data, ctx) => {
        // ISO dates (YYYY-MM-DD) compare lexicographically.
        const dob = client?.date_of_birth;
        if (!data.is_present && data.start_date && data.end_date && data.start_date > data.end_date) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'End date must be on or after the start date.', path: ['end_date'] });
        }
        if (dob && data.start_date && data.start_date < dob) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Start date cannot be before your date of birth.', path: ['start_date'] });
        }
    }), [client?.date_of_birth]);

    // Mirrors the server rules: current password is only required when a new
    // password is actually being set. Leave everything blank to keep it.
    const passwordSchema = useMemo(() => z.object({
        current_password: z.string().optional(),
        password: createPasswordSchema(effectivePasswordRules).or(z.literal('')),
        password_confirmation: z.string(),
    }).superRefine((data, ctx) => {
        if (!data.password) return;
        if (!data.current_password) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Current password is required.', path: ['current_password'] });
        }
        if (data.password !== data.password_confirmation) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Passwords do not match.', path: ['password_confirmation'] });
        }
    }), [effectivePasswordRules]);

    const mergedOccupationOptions = useMemo(() => {
        const labels = [...DEFAULT_OCCUPATIONS, ...(occupationOptions || []).map((o) => o.label ?? o)]
            .map((p) => String(p).trim())
            .filter(Boolean);
        const unique = [...new Set(labels)].sort((a, b) => a.localeCompare(b));
        return unique.map((p) => ({ value: p, label: p }));
    }, [occupationOptions]);

    /* ---------- Dirty tracking: one form, one save ---------- */

    const isDirty = JSON.stringify(form.data) !== savedSnapshot;
    const { UnsavedModal, bypassNext } = useUnsavedChanges(isDirty);

    function setAddress(field, value) {
        if (typeof field === 'object') {
            form.setData('address', { ...form.data.address, ...field });
        } else {
            form.setData('address', { ...form.data.address, [field]: value });
        }
    }

    function setEmployment(field, value) {
        form.setData('employment', { ...form.data.employment, [field]: value });
    }

    function setNokField(index, field, value) {
        form.setData(`next_of_kin.${index}.${field}`, value);
    }

    /** Copy the home address onto a contact's address fields. */
    function applyHomeAddressToRow(index) {
        const home = form.data.address;
        const row = { ...form.data.next_of_kin[index] };
        // The home address holds dropdown codes; contacts store names, so map
        // back through the same dataset the dropdowns read.
        const names = addressCodesToNames(home);
        form.setData('next_of_kin', form.data.next_of_kin.map((r, i) => (
            i === index ? { ...row, ...names } : r
        )));
    }

    function clearRowAddress(index) {
        form.setData('next_of_kin', form.data.next_of_kin.map((r, i) => (
            i === index
                ? { ...r, region: '', province: '', city_municipality: '', barangay: '', street: '' }
                : r
        )));
    }

    function toggleSameAddress(index, checked) {
        setSameAddressRows((prev) => ({ ...prev, [index]: checked }));
        if (checked) applyHomeAddressToRow(index);
        else clearRowAddress(index);
    }

    function addNokRow() {
        form.setData('next_of_kin', [...form.data.next_of_kin, { ...EMPTY_NOK_ROW }]);
    }

    function removeNokRow(index) {
        form.setData('next_of_kin', form.data.next_of_kin.filter((_, i) => i !== index));
        setSameAddressRows((prev) => {
            const next = { ...prev };
            delete next[index];
            return next;
        });
    }

    const nokError = (index, field) => form.errors[`next_of_kin.${index}.${field}`];

    function handleSave(e) {
        e.preventDefault();
        if (form.processing || !isDirty) return;

        form.clearErrors();

        /* --- client-side checks before we touch the network --- */
        const addressCheck = addressSchema.safeParse(form.data.address);
        if (!addressCheck.success) {
            for (const issue of addressCheck.error.issues) {
                form.setError(`address.${issue.path.join('.')}`, issue.message);
            }
            return;
        }

        const employmentCheck = employmentSchema.safeParse(form.data.employment);
        if (!employmentCheck.success) {
            for (const issue of employmentCheck.error.issues) {
                form.setError(`employment.${issue.path.join('.')}`, issue.message);
            }
            return;
        }

        const passwordCheck = passwordSchema.safeParse({
            current_password: form.data.current_password,
            password: form.data.password,
            password_confirmation: form.data.password_confirmation,
        });
        if (!passwordCheck.success) {
            for (const issue of passwordCheck.error.issues) {
                form.setError(issue.path.join('.'), issue.message);
            }
            return;
        }

        // Blank next-of-kin rows carry no meaning — drop them before saving so
        // an added-then-abandoned row is never persisted.
        const cleanedNok = (form.data.next_of_kin ?? []).filter(
            (row) => (row?.first_name ?? '').trim() || (row?.last_name ?? '').trim(),
        );

        bypassNext();

        // The home address holds dropdown codes; the API and every other
        // surface store names, so resolve the codes back to names here.
        const homeNames = addressCodesToNames(form.data.address);

        form.transform(() => ({
            sex: form.data.sex,
            suffix: form.data.suffix,
            contact_number: form.data.contact_number,
            address: { ...homeNames, street: form.data.address.street },
            employment: form.data.employment,
            next_of_kin: cleanedNok,
            // Only send credentials when the OFW actually set a new password.
            ...(form.data.password ? {
                current_password: form.data.current_password,
                password: form.data.password,
                password_confirmation: form.data.password_confirmation,
            } : {}),
        }));

        form.put(safeRoute('ofw.profile.update', undefined, '/my-cases/profile'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const freshClient = page.props.client ?? client;

                const fresh = {
                    ...buildInitialData(),
                    sex: freshClient?.sex ?? '',
                    suffix: freshClient?.suffix ?? '',
                    contact_number: freshClient?.contact_number ?? '',
                    address: { ...resolveAddressToCodes(freshClient?.address), street: freshClient?.address?.street ?? '' },
                    employment: {
                        ...buildInitialData().employment,
                        ...(freshClient?.employment ?? {}),
                        is_present: freshClient?.employment?.is_present ?? (freshClient?.employment ? !freshClient.employment.end_date : false),
                    },
                    next_of_kin: freshClient?.next_of_kin ?? [],
                };

                form.setData(fresh);
                form.reset('current_password', 'password', 'password_confirmation');
                setSavedSnapshot(JSON.stringify(fresh));
                setSameAddressRows({});
                toast.success('Profile updated successfully.');
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(String(first));
            },
        });
    }

    function handleDiscard() {
        form.clearErrors();
        form.setData(buildInitialData());
        setSameAddressRows({});
    }

    const notLinked = !client;

    return (
        <OfwPortalLayout title="My Profile">
            <p className="text-[12px] text-slate-500">Home / My Profile</p>

            <h1 className="mt-2 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                My Profile
            </h1>
            <p className="mt-1.5 text-[14px] text-slate-600">
                Keep your contact details, address and employment information up to date.
            </p>

            {notLinked ? (
                <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] text-amber-800">
                    We don&rsquo;t have a complete client record linked to your account yet. Contact your case
                    manager to update your details.
                </div>
            ) : null}

            <form onSubmit={handleSave} className="mt-5">
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    {/* ---------- Account ---------- */}
                    <Section
                        title="Account"
                        description="Your photo and verified identity. Name, date of birth and email are managed by DMW."
                    >
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="shrink-0">
                                <ProfilePictureUpload
                                    currentUrl={user?.avatar_url ?? client?.avatar_url ?? null}
                                    name={fullName}
                                    uploadRoute={safeRoute('ofw.profile.avatar.store', undefined, '/my-cases/profile/avatar')}
                                    destroyRoute={safeRoute('ofw.profile.avatar.destroy', undefined, '/my-cases/profile/avatar')}
                                    fallbackClassName={getAvatarColor(fullName)}
                                />
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
                                    Citizen account
                                </p>
                                <h3 className="mt-0.5 truncate font-headline text-[18px] font-extrabold tracking-tight text-slate-900">
                                    {fullName || 'Your profile'}
                                </h3>
                                <p className="mt-0.5 truncate text-[13px] text-slate-500">{user?.email}</p>
                            </div>

                            <span className="inline-flex shrink-0 items-center gap-1.5 self-start rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 sm:self-center">
                                <span className="h-1.5 w-1.5 rounded-full bg-current" />
                                {notLinked ? 'Profile incomplete' : 'Verified client'}
                            </span>
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-4">
                            <StaticField label="Date of birth" value={formatDate(client?.date_of_birth)} />
                            <StaticField label="Sex" value={formatSex(client?.sex)} />
                            <StaticField label="Suffix" value={client?.suffix} />
                            <StaticField label="Assigned office" value="DMW Region VII" />
                        </div>
                    </Section>

                    {/* ---------- Contact ---------- */}
                    <Section title="Contact" description="Your mobile number. Changes are recorded and sent to your case manager.">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <TextInput
                                id="contact_number"
                                label="Contact number"
                                value={form.data.contact_number}
                                onChange={(v) => form.setData('contact_number', v)}
                                error={form.errors.contact_number}
                                type="tel"
                                placeholder="e.g. 09171234567"
                                maxLength={20}
                                autoComplete="tel"
                                disabled={notLinked}
                            />
                            <TextInput
                                id="profile_email"
                                label="Email address"
                                value={user?.email ?? ''}
                                onChange={() => {}}
                                hint="Managed by DMW"
                                type="email"
                                disabled
                            />
                            <Field label="Sex" htmlFor="profile_sex" error={form.errors.sex}>
                                <select
                                    id="profile_sex"
                                    value={form.data.sex}
                                    onChange={(e) => form.setData('sex', e.target.value)}
                                    disabled={notLinked}
                                    className={INPUT_CLASS}
                                >
                                    <option value="">Select sex</option>
                                    {SEX_OPTIONS.map((s) => (
                                        <option key={s} value={s.toUpperCase()}>{s}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Suffix" htmlFor="profile_suffix" error={form.errors.suffix}>
                                <select
                                    id="profile_suffix"
                                    value={form.data.suffix}
                                    onChange={(e) => form.setData('suffix', e.target.value)}
                                    disabled={notLinked}
                                    className={INPUT_CLASS}
                                >
                                    <option value="">None</option>
                                    {SUFFIX_OPTIONS.map((s) => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                    {/* Stored free-text values predate the select; keep them selectable. */}
                                    {form.data.suffix && !SUFFIX_OPTIONS.includes(form.data.suffix) && (
                                        <option value={form.data.suffix}>{form.data.suffix}</option>
                                    )}
                                </select>
                            </Field>
                        </div>
                    </Section>

                    {/* ---------- Home address ---------- */}
                    <Section title="Home address" description="Where we can reach you while abroad or at home.">
                        <AddressDropdowns
                            values={form.data.address}
                            onChange={setAddress}
                            errors={form.errors}
                        />
                    </Section>

                    {/* ---------- Employment ---------- */}
                    <Section title="Employment" description="Your current or most recent employment details.">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <TextInput
                                id="emp_employer"
                                label="Employer / Agency"
                                value={form.data.employment.employer_name}
                                onChange={(v) => setEmployment('employer_name', v)}
                                error={form.errors['employment.employer_name']}
                                placeholder="e.g. ABC Manpower Services"
                            />
                            <Field label="Position" htmlFor="emp_position" error={form.errors['employment.position']}>
                                <SearchableSelect
                                    value={form.data.employment.position}
                                    onChange={(v) => setEmployment('position', v)}
                                    options={mergedOccupationOptions}
                                    placeholder="Select or type a position"
                                    allowCustom
                                />
                            </Field>
                            <Field label="Country" htmlFor="emp_country" error={form.errors['employment.country']}>
                                <CountrySelect
                                    value={form.data.employment.country}
                                    onChange={(v) => setEmployment('country', v)}
                                    placeholder="Select country"
                                />
                            </Field>
                            <TextInput
                                id="emp_start"
                                label="Start date"
                                type="date"
                                value={form.data.employment.start_date}
                                onChange={(v) => setEmployment('start_date', v)}
                                error={form.errors['employment.start_date']}
                            />
                            <div>
                                <Field label="End date" htmlFor="emp_end" error={form.errors['employment.end_date']}>
                                    {form.data.employment.is_present ? (
                                        <span className="mt-1.5 flex h-10 w-full items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-[13px] font-medium text-emerald-700">
                                            Present
                                        </span>
                                    ) : (
                                        <input
                                            id="emp_end"
                                            type="date"
                                            value={form.data.employment.end_date ?? ''}
                                            onChange={(e) => setEmployment('end_date', e.target.value)}
                                            className={INPUT_CLASS}
                                        />
                                    )}
                                </Field>
                                <label className="mt-2 inline-flex cursor-pointer select-none items-center gap-2 text-[12px] text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={!!form.data.employment.is_present}
                                        onChange={(e) => {
                                            const checked = e.target.checked;
                                            form.setData('employment', {
                                                ...form.data.employment,
                                                is_present: checked,
                                                end_date: checked ? '' : form.data.employment.end_date,
                                            });
                                        }}
                                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                                    />
                                    Currently employed here
                                </label>
                            </div>
                            <TextInput
                                id="emp_arrival"
                                label="Date of arrival"
                                type="date"
                                value={form.data.employment.date_of_arrival}
                                onChange={(v) => setEmployment('date_of_arrival', v)}
                                error={form.errors['employment.date_of_arrival']}
                            />
                            <Field label="Last country" htmlFor="emp_last_country" error={form.errors['employment.last_country']}>
                                <CountrySelect
                                    value={form.data.employment.last_country}
                                    onChange={(v) => setEmployment('last_country', v)}
                                    placeholder="Select country"
                                />
                            </Field>
                            <Field label="Last position" htmlFor="emp_last_position" error={form.errors['employment.last_position']}>
                                <SearchableSelect
                                    value={form.data.employment.last_position}
                                    onChange={(v) => setEmployment('last_position', v)}
                                    options={mergedOccupationOptions}
                                    placeholder="Select or type a position"
                                    allowCustom
                                />
                            </Field>
                        </div>
                    </Section>

                    {/* ---------- Family & friends ---------- */}
                    <Section
                        title="Family & friends"
                        description="Optional — add people we may contact on your behalf."
                        action={
                            <button
                                type="button"
                                onClick={addNokRow}
                                disabled={notLinked}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span className="material-symbols-outlined text-[15px]" aria-hidden="true">add</span>
                                Add contact
                            </button>
                        }
                    >
                        {form.data.next_of_kin.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-[13px] text-slate-500">
                                No next of kin recorded. You may leave this section empty.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {form.data.next_of_kin.map((row, i) => {
                                    const sameAsHome = sameAddressRows[i] ?? false;

                                    return (
                                        <div key={i} className="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                            <div className="flex items-center justify-between">
                                                <p className="text-[12px] font-bold uppercase tracking-[0.08em] text-slate-500">
                                                    Contact {i + 1}
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() => removeNokRow(i)}
                                                    className="inline-flex items-center gap-1 text-[12px] font-semibold text-rose-600 transition-colors hover:text-rose-700"
                                                >
                                                    <span className="material-symbols-outlined text-[15px]" aria-hidden="true">delete</span>
                                                    Remove
                                                </button>
                                            </div>

                                            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                <TextInput id={`nok_${i}_first`} label="First name" value={row.first_name ?? ''} onChange={(v) => setNokField(i, 'first_name', v)} error={nokError(i, 'first_name')} placeholder="e.g. Maria" />
                                                <TextInput id={`nok_${i}_middle`} label="Middle name" value={row.middle_name ?? ''} onChange={(v) => setNokField(i, 'middle_name', v)} error={nokError(i, 'middle_name')} placeholder="Optional" maxLength={255} />
                                                <TextInput id={`nok_${i}_last`} label="Last name" value={row.last_name ?? ''} onChange={(v) => setNokField(i, 'last_name', v)} error={nokError(i, 'last_name')} placeholder="e.g. Dela Cruz" />
                                                <Field label="Relationship" htmlFor={`nok_${i}_relationship`} error={nokError(i, 'relationship')}>
                                                    <select
                                                        id={`nok_${i}_relationship`}
                                                        value={row.relationship ?? ''}
                                                        onChange={(e) => setNokField(i, 'relationship', e.target.value)}
                                                        className={INPUT_CLASS}
                                                    >
                                                        <option value="">Select relationship</option>
                                                        {NOK_RELATIONSHIP_OPTIONS.map((r) => (
                                                            <option key={r} value={r}>{r}</option>
                                                        ))}
                                                    </select>
                                                </Field>
                                                <TextInput id={`nok_${i}_phone`} label="Phone number" type="tel" value={row.phone_number ?? ''} onChange={(v) => setNokField(i, 'phone_number', v)} error={nokError(i, 'phone_number')} placeholder="e.g. 09171234567" maxLength={20} />
                                                <TextInput id={`nok_${i}_email`} label="Email" type="email" value={row.email ?? ''} onChange={(v) => setNokField(i, 'email', v)} error={nokError(i, 'email')} placeholder="e.g. name@example.com" />
                                            </div>

                                            {/* Address — same dropdown chain as the home address */}
                                            <div className="mt-4 border-t border-slate-200 pt-3">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <p className="text-[12px] font-bold text-slate-700">Address</p>
                                                    <SameAddressToggle
                                                        checked={sameAsHome}
                                                        onChange={(checked) => toggleSameAddress(i, checked)}
                                                    />
                                                </div>

                                                <div className={`mt-3 ${sameAsHome ? 'pointer-events-none opacity-60' : ''}`}>
                                                    <AddressDropdowns
                                                        values={row}
                                                        onChange={(field, value) => {
                                                            if (typeof field === 'object') {
                                                                form.setData('next_of_kin', form.data.next_of_kin.map((r, idx) => (
                                                                    idx === i ? { ...r, ...field } : r
                                                                )));
                                                            } else {
                                                                setNokField(i, field, value);
                                                            }
                                                        }}
                                                        errors={{
                                                            region: nokError(i, 'region'),
                                                            province: nokError(i, 'province'),
                                                            city_municipality: nokError(i, 'city_municipality'),
                                                            barangay: nokError(i, 'barangay'),
                                                            street: nokError(i, 'street'),
                                                        }}
                                                        emitNames
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </Section>

                    {/* ---------- Password ---------- */}
                    <Section
                        title="Password"
                        description="Leave the new password fields blank to keep your current password."
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <PasswordInput
                                id="ofw-current-password"
                                label="Current password"
                                value={form.data.current_password}
                                onChange={(v) => form.setData('current_password', v)}
                                error={form.errors.current_password}
                                autoComplete="current-password"
                            />
                            <PasswordInput
                                id="ofw-new-password"
                                label="New password"
                                value={form.data.password}
                                onChange={(v) => form.setData('password', v)}
                                error={form.errors.password}
                                autoComplete="new-password"
                                showStrengthMeter
                                rules={effectivePasswordRules}
                                confirmation={form.data.password_confirmation}
                            />
                            <PasswordInput
                                id="ofw-confirm-password"
                                label="Confirm new password"
                                value={form.data.password_confirmation}
                                onChange={(v) => form.setData('password_confirmation', v)}
                                error={form.errors.password_confirmation}
                                autoComplete="new-password"
                            />
                        </div>
                    </Section>

                    {/* ---------- The single Save / Discard bar ---------- */}
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-5 py-3 sm:px-6">
                        <p className="text-[12px] text-slate-500">
                            {isDirty ? 'You have unsaved changes.' : 'All changes are saved.'}
                        </p>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={handleDiscard}
                                disabled={!isDirty || form.processing}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[13px] font-bold text-slate-700 transition-colors hover:border-slate-300 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Discard changes
                            </button>
                            <button
                                type="submit"
                                disabled={!isDirty || form.processing}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-5 py-2.5 text-[13px] font-bold text-white shadow-sm transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {form.processing && (
                                    <span className="material-symbols-outlined animate-spin text-[17px]" aria-hidden="true">progress_activity</span>
                                )}
                                {form.processing ? 'Saving…' : 'Save changes'}
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <p className="mt-5 flex items-center gap-1.5 text-[11px] text-slate-400">
                <span aria-hidden="true" className="material-symbols-outlined text-[14px] leading-none">info</span>
                Only you and authorized DMW staff can see your profile details.
            </p>

            {UnsavedModal}
        </OfwPortalLayout>
    );
}
