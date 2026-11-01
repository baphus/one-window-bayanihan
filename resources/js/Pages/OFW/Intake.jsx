import { useForm, usePage, Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { PageHeader, SectionCard, MaterialSymbol } from '@/Components/Dashboard/primitives';
import InputError from '@/Components/InputError';
import safeRoute from '@/utils/safeRoute';
import { useToast } from '@/Hooks/useToast';

// Same values as Case/Create.jsx / VulnerabilityRule — used when the backend
// has not passed vulnerabilityOptions yet.
const DEFAULT_VULNERABILITY_OPTIONS = ['None', 'PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'];

function ProfileRow({ icon, label, value }) {
    return (
        <div className="flex items-start gap-3 text-sm text-slate-700">
            <MaterialSymbol name={icon} className="mt-0.5 text-[18px] text-slate-400" />
            <div className="min-w-0">
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{label}</p>
                <p className="break-words">{value || '—'}</p>
            </div>
        </div>
    );
}

function normalizeOptions(options) {
    return (Array.isArray(options) && options.length > 0 ? options : DEFAULT_VULNERABILITY_OPTIONS)
        .map((option) => (typeof option === 'string' ? { value: option, label: option } : option))
        .filter((option) => option && option.value);
}

export default function Intake({ profile: profileProp, client, vulnerabilityOptions }) {
    const { auth } = usePage().props;
    const profile = profileProp ?? client ?? null;
    const options = normalizeOptions(vulnerabilityOptions);
    const toast = useToast();

    const form = useForm({
        summary: '',
        vulnerability_indicator: '',
        consent: false,
    });

    const { UnsavedModal } = useUnsavedChanges(form.isDirty);

    const fullName = profile
        ? [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ')
        : (auth?.user?.name ?? '');
    const address = profile?.address
        ? [profile.address.street, profile.address.barangay, profile.address.city_municipality, profile.address.province, profile.address.region]
              .filter(Boolean)
              .join(', ')
        : '';
    // Records written before the placeholder guard existed can hold "None" as
    // a job title; treat it as missing so intake never reads "None at Test".
    const displayText = (value) => {
        const text = String(value ?? '').trim();

        return ['', 'none', 'n/a', 'na', 'null', '-'].includes(text.toLowerCase()) ? '' : text;
    };
    const employment = profile?.employment
        ? [
              [displayText(profile.employment.position), displayText(profile.employment.employer_name)].filter(Boolean).join(' at '),
              displayText(profile.employment.country),
          ].filter(Boolean).join(' · ')
        : '';

    function submit(e) {
        e.preventDefault();
        if (form.processing) return;
        form.clearErrors();
        form.post(safeRoute('ofw.intake.store', undefined, '/my-cases/intake'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Case submitted successfully.'),
            onError: (errors) => toast.error(Object.values(errors)[0] || 'Unable to submit your case.'),
        });
    }

    return (
        <OfwLayout title="File a New Case">
            <Link
                href={safeRoute('ofw.dashboard', undefined, '/my-cases')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <MaterialSymbol name="arrow_back" className="text-[18px]" />
                Back to My Cases
            </Link>

            <PageHeader
                title="File a new case"
                subtitle="Tell us what you need help with. A case manager will review your request."
            />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                {/* (a) Read-only profile review */}
                <SectionCard
                    title="Review your details"
                    action={
                        <Link
                            href={safeRoute('ofw.profile.edit', undefined, '/my-cases/profile')}
                            className="inline-flex items-center gap-1 text-xs font-bold text-primary hover:text-primary/80"
                        >
                            <MaterialSymbol name="edit" className="text-[14px]" />
                            Edit profile
                        </Link>
                    }
                >
                    {form.errors.client && (
                        <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            {form.errors.client}
                        </p>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <ProfileRow icon="person" label="Full name" value={fullName} />
                        <ProfileRow icon="mail" label="Email" value={profile?.email ?? auth?.user?.email} />
                        <ProfileRow icon="call" label="Contact number" value={profile?.contact_number} />
                        <ProfileRow icon="home_pin" label="Address" value={address} />
                        <ProfileRow icon="work" label="Employment" value={employment} />
                    </div>
                    <p className="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">
                        These details come from your profile. Use <strong>Edit profile</strong> to change them before filing.
                    </p>
                </SectionCard>

                {/* Your concern */}
                <SectionCard title="Your concern">
                    <div className="space-y-4">
                        <div>
                            <label htmlFor="intake-summary" className="block text-sm font-medium text-slate-700">
                                Case summary <span className="text-red-600">*</span>
                            </label>
                            <textarea
                                id="intake-summary"
                                rows={6}
                                value={form.data.summary}
                                onChange={(e) => form.setData('summary', e.target.value)}
                                placeholder="Describe what happened, the desired outcome, and any other relevant details."
                                maxLength={5000}
                                className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            />
                            <InputError message={form.errors.summary} className="mt-1" />
                        </div>
                    </div>
                </SectionCard>

                {/* (c) Vulnerability indicator */}
                <SectionCard title="Vulnerability status">
                    <span id="intake-vulnerability-label" className="block text-sm font-medium text-slate-700">
                        Do you belong to any vulnerable sector?{' '}
                        <span className="font-normal text-slate-400">Select all that apply.</span>
                    </span>
                    <div className="mt-2 flex flex-wrap gap-x-5 gap-y-2" role="group" aria-labelledby="intake-vulnerability-label">
                        {options
                            .filter((option) => option.value !== 'None')
                            .map((option) => {
                                const selected = form.data.vulnerability_indicator && form.data.vulnerability_indicator !== 'None'
                                    ? form.data.vulnerability_indicator.split(',').map((s) => s.trim()).filter(Boolean)
                                    : [];
                                const checked = selected.includes(option.value);
                                return (
                                    <label key={option.value} className="inline-flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() => {
                                                const next = checked
                                                    ? selected.filter((v) => v !== option.value)
                                                    : [...selected, option.value];
                                                form.setData('vulnerability_indicator', next.join(', '));
                                            }}
                                            className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40"
                                        />
                                        <span className="text-sm text-slate-700">{option.label}</span>
                                    </label>
                                );
                            })}
                    </div>
                    <InputError message={form.errors.vulnerability_indicator} className="mt-1" />
                </SectionCard>

                {/* (d) Consent */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <label htmlFor="intake-consent" className="flex items-start gap-3 text-sm text-slate-700">
                        <input
                            id="intake-consent"
                            type="checkbox"
                            checked={form.data.consent}
                            onChange={(e) => form.setData('consent', e.target.checked)}
                            className="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40"
                        />
                        <span>
                            I consent to the collection and use of my personal data for case management, in line with
                            the privacy policy. <span className="text-red-600">*</span>
                        </span>
                    </label>
                    <InputError message={form.errors.consent} className="mt-2" />
                </div>

                {/* (e) Submit */}
                <div className="flex items-center justify-end gap-3">
                    <Link
                        href={safeRoute('ofw.dashboard', undefined, '/my-cases')}
                        className="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {form.processing && (
                            <MaterialSymbol name="progress_activity" className="animate-spin text-[18px]" />
                        )}
                        {form.processing ? 'Submitting...' : 'Submit case'}
                    </button>
                </div>
            </form>

            {UnsavedModal}
        </OfwLayout>
    );
}
