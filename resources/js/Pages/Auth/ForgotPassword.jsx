import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import TurnstileWidget from '@/Components/TurnstileWidget';

export default function ForgotPassword({ status }) {
    const { errors: pageErrors, turnstile } = usePage().props;
    const [email, setEmail] = useState('');
    const [processing, setProcessing] = useState(false);
    const [successMsg, setSuccessMsg] = useState(status || '');
    const [errorMsg, setErrorMsg] = useState(pageErrors?.email || '');
    const [turnstileToken, setTurnstileToken] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrorMsg('');
        setProcessing(true);

        router.post(route('password.email'), { email, cf_turnstile_response: turnstileToken }, {
            onSuccess: () => {
                setProcessing(false);
                setSuccessMsg('We have emailed your password reset link. Please check your inbox.');
            },
            onError: (err) => {
                setProcessing(false);
                setErrorMsg(err.captcha || err.email || 'Unable to send reset link. Please try again.');
            },
        });
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Forgot Password" />

            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex flex-col lg:flex-row">

                    {/* Left: Branding */}
                    <div className="lg:w-[60%] relative min-h-[320px] lg:min-h-full flex flex-col justify-center text-white overflow-hidden">
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
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8 text-center">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    lock_reset
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Reset Password
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Forgot your password? No problem. Enter your email and we will send you a reset link.
                                </p>
                            </div>

                            {successMsg && (
                                <div className="mb-6 bg-primary-fixed p-4 border border-primary/20 flex items-center gap-3">
                                    <span className="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                                    <p className="text-xs font-semibold text-primary">{successMsg}</p>
                                </div>
                            )}

                            <form onSubmit={handleSubmit} className="space-y-6">
                                {errorMsg && (
                                    <div className="bg-error-container p-4 border border-error/20 flex items-center gap-3">
                                        <span className="material-symbols-outlined text-error text-[20px]">error</span>
                                        <p className="text-xs font-semibold text-on-error-container">{errorMsg}</p>
                                    </div>
                                )}

                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email Address</label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">alternate_email</span>
                                        <input
                                            type="email"
                                            value={email}
                                            onChange={(e) => { setEmail(e.target.value); setErrorMsg(''); }}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                            placeholder="you@agency.gov.ph"
                                            required
                                        />
                                    </div>
                                </div>

                                {turnstile?.enabled && (
                                    <div className="text-center">
                                        <TurnstileWidget
                                            onToken={setTurnstileToken}
                                            onExpire={() => setTurnstileToken('')}
                                        />
                                    </div>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing || (turnstile?.enabled && !turnstileToken)}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                >
                                    {processing ? 'Sending...' : 'Send Password Reset Link'}
                                </button>
                            </form>

                            <Link
                                href={route('login')}
                                className="mt-8 flex items-center justify-center gap-2 text-sm font-bold text-on-surface-variant hover:text-primary mx-auto transition-colors"
                            >
                                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                                Return to Sign In
                            </Link>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
