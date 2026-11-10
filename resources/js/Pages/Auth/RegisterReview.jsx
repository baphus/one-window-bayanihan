import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import OtpInput from '@/Components/OtpInput';
import { useToast } from '@/Hooks/useToast';

export default function RegisterReview({ pending = {} }) {
    const { flash } = usePage().props;
    const toast = useToast();
    const [codeSent, setCodeSent] = useState(false);
    const { data, setData, errors } = useForm({ otp: '' });
    const sendForm = useForm({});
    const sending = sendForm.processing;
    const [submitting, setSubmitting] = useState(false);

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

    function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        router.post(route('register.review.store'), { otp: data.otp }, {
            onFinish: () => setSubmitting(false),
            onError: (errs) => {
                toast.error(errs.otp || Object.values(errs)[0] || 'Could not finish registration.');
            },
        });
    }

    return (
        <div className="min-h-dvh bg-surface font-body text-on-surface">
            <Head title="Verify your email" />
            <AppHeader minimal />
            <main className="flex min-h-dvh flex-col pt-[72px]">
                <div className="flex flex-1 items-center justify-center px-4 py-12">
                    <div className="w-full max-w-xl">
                        <span className="material-symbols-outlined mb-4 block text-[32px] text-primary" aria-hidden="true">mark_email_read</span>
                        <h1 className="font-headline text-2xl font-bold text-slate-900">Verify your email</h1>
                        <p className="mt-2 text-sm text-slate-600">
                            We’ll send a 6-digit code to {pending.email ?? 'your email address'}. Enter it to finish creating your account.
                        </p>

                        {flash?.success && <p className="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700">{flash.success}</p>}

                        {!codeSent ? (
                            <button type="button" onClick={sendCode} disabled={sending} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-primary px-6 py-4 text-sm font-bold text-white shadow-xl transition hover:brightness-110 disabled:opacity-60">
                                <span className="material-symbols-outlined text-[18px] leading-none" aria-hidden="true">mark_email_unread</span>
                                {sending ? 'Sending…' : 'Send verification code'}
                            </button>
                        ) : (
                            <form onSubmit={submit} className="mt-5 space-y-4">
                                <OtpInput
                                    id="registration-email-otp"
                                    label="Email verification code"
                                    value={data.otp}
                                    onChange={(value) => setData('otp', value)}
                                    error={errors.otp}
                                    autoFocus
                                    disabled={submitting || sending}
                                />
                                <button type="button" onClick={sendCode} disabled={sending} className="text-xs font-semibold text-primary underline disabled:opacity-50">
                                    Resend code
                                </button>
                                <button type="submit" disabled={submitting || !/^\d{6}$/.test(data.otp)} className="w-full rounded-md bg-primary px-8 py-4 text-sm font-bold text-white shadow-xl transition hover:brightness-110 disabled:opacity-60">
                                    {submitting ? 'Finishing…' : 'Verify and finish'}
                                </button>
                            </form>
                        )}

                        <div className="mt-6 text-center">
                            <Link href={route('register')} className="text-xs font-bold text-on-surface-variant transition-colors hover:text-primary">
                                Back to registration
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
            <AppFooter />
        </div>
    );
}
