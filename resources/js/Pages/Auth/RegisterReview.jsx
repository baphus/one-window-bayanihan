import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import AvatarPicker from '@/Components/AvatarPicker';
import { useToast } from '@/Hooks/useToast';
import { formatResolvedAddress } from '@/lib/addressResolver';

const ROLE_LABELS = {
    OFW: 'I am an OFW',
    NEXT_OF_KIN: 'Filing for a family member',
};

function Section({ title, children }) {
    return (
        <section className="rounded-lg border border-outline-variant bg-surface-container p-4">
            <h3 className="mb-3 text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">
                {title}
            </h3>
            <dl className="space-y-2 text-[13px]">{children}</dl>
        </section>
    );
}

function Row({ label, value, wide = false }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="shrink-0 text-slate-500">{label}</dt>
            <dd className={`text-right font-semibold text-slate-800 ${wide ? 'break-words max-w-[65%]' : ''}`}>
                {value || '—'}
            </dd>
        </div>
    );
}

export default function RegisterReview({ pendingName = {}, pending = {} }) {
    const { flash } = usePage().props;
    const toast = useToast();
    const [codeSent, setCodeSent] = useState(false);

    const { data, setData, errors } = useForm({ otp: '' });
    const sendForm = useForm({});
    const sending = sendForm.processing;

    const [avatarFile, setAvatarFile] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const address = pending.address ?? {};
    const employment = pending.employment ?? {};
    const fullName = [pendingName.first_name, pendingName.middle_name, pendingName.last_name, pending.suffix]
        .filter(Boolean)
        .join(' ');
    const addressText = formatResolvedAddress(address, '') || address.street || '';
    const workRange = [employment.start_date, employment.is_present ? 'Present' : employment.end_date]
        .filter(Boolean)
        .join(' – ');

    function sendCode() {
        sendForm.post(route('register.review.send-otp'), {
            preserveScroll: true,
            onSuccess: () => {
                setCodeSent(true);
                toast.success('Code sent. Check your inbox and spam folder.');
            },
            onError: () => toast.error('Could not send the code. Please try again.'),
        });
    }

    function submit(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('otp', data.otp);
        if (avatarFile) {
            formData.append('profile_picture', avatarFile);
        }

        setSubmitting(true);
        router.post(route('register.review.store'), formData, {
            forceFormData: true,
            onFinish: () => setSubmitting(false),
            onError: (errs) => {
                if (errs.otp) {
                    toast.error(Array.isArray(errs.otp) ? errs.otp[0] : errs.otp);
                } else if (errs.profile_picture) {
                    toast.error(Array.isArray(errs.profile_picture) ? errs.profile_picture[0] : errs.profile_picture);
                } else {
                    const first = Object.values(errs)[0];
                    toast.error((Array.isArray(first) ? first[0] : first) || 'Could not finish registration.');
                }
            },
        });
    }

    return (
        <div className="min-h-dvh bg-surface font-body text-on-surface">
            <Head title="Review your details" />

            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                    <div className="flex-1 flex items-center justify-center px-4 py-12 font-body text-on-surface">
                        <div className="w-full max-w-2xl">
                            <div className="mb-6">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    fact_check
                                </span>
                                <h2 className="font-headline text-2xl font-bold text-slate-900">Review your details</h2>
                                <p className="mt-2 text-sm text-slate-500">
                                    Step 2 of 2 — check everything below, then verify your email to finish.
                                </p>
                            </div>

                            <div className="space-y-3">
                                <Section title="Account for">
                                    <Row label="Registering as" value={ROLE_LABELS[pending.type] ?? '—'} />
                                    <Row label="Name" value={fullName} />
                                    <Row label="Birth / Sex" value={[pending.date_of_birth, pending.sex].filter(Boolean).join(' · ')} />
                                    <Row label="Contact" value={pending.contact_number} />
                                </Section>

                                <Section title="Address">
                                    <Row label="Address" value={addressText} wide />
                                </Section>

                                <Section title="Employment">
                                    <Row label="Employer" value={employment.employer_name} wide />
                                    <Row label="Position" value={employment.position} wide />
                                    <Row label="Period" value={workRange} />
                                    <Row label="Last country" value={employment.last_country} />
                                    <Row label="Last occupation" value={employment.last_position} wide />
                                    <Row label="Arrival date" value={employment.date_of_arrival} />
                                </Section>
                            </div>

                            {flash?.success && (
                                <p className="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    {flash.success}
                                </p>
                            )}

                            {!codeSent ? (
                                <button
                                    type="button"
                                    onClick={sendCode}
                                    disabled={sending}
                                    className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-primary px-6 py-4 text-sm font-bold text-white shadow-xl transition-all hover:brightness-110 active:scale-95 disabled:opacity-60"
                                >
                                    <span className="material-symbols-outlined text-[18px] leading-none">mark_email_unread</span>
                                    {sending ? 'Sending…' : 'Send Verification Code'}
                                </button>
                            ) : (
                                <form onSubmit={submit} className="mt-5 space-y-4">
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Email Verification Code
                                        </label>
                                        <input
                                            type="text"
                                            value={data.otp}
                                            onChange={(e) => setData('otp', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                            placeholder="6-digit code"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            autoFocus
                                            className="w-full rounded-md border border-outline-variant bg-surface-container px-4 py-3 text-center text-sm font-bold tracking-[0.3em] focus:border-primary focus:outline-none"
                                            required
                                        />
                                        <p className="mt-1 text-xs text-slate-500">
                                            Enter the 6-digit code we sent to {pending.email ?? 'your email address'}.
                                        </p>
                                        {errors.otp && <p className="mt-1 text-xs text-red-600">{errors.otp}</p>}
                                        <button
                                            type="button"
                                            onClick={sendCode}
                                            disabled={sending}
                                            className="mt-1 text-xs font-semibold text-primary underline disabled:opacity-50"
                                        >
                                            Resend code
                                        </button>
                                    </div>

                                    <div className="border-t border-outline-variant pt-4">
                                        <AvatarPicker
                                            file={avatarFile}
                                            onFileChange={setAvatarFile}
                                            name={fullName}
                                            error={errors.profile_picture}
                                            disabled={submitting}
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={submitting || !/^\d{6}$/.test(data.otp)}
                                        className="w-full rounded-md bg-primary px-8 py-4 text-sm font-bold text-white shadow-xl transition-all hover:brightness-110 active:scale-95 disabled:opacity-60"
                                    >
                                        {submitting ? 'Finishing…' : 'Verify & Finish'}
                                    </button>
                                </form>
                            )}

                            <div className="mt-6 text-center">
                                <Link href={route('register')} className="text-xs font-bold text-on-surface-variant hover:text-primary transition-colors">
                                    Back to edit my details
                                </Link>
                            </div>
                        </div>
                    </div>
                </main>

            <AppFooter />
        </div>
    );
}
