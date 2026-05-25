import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';

export default function ForgotPassword({ status }) {
    const { errors: pageErrors } = usePage().props;
    const [email, setEmail] = useState('');
    const [processing, setProcessing] = useState(false);
    const [successMsg, setSuccessMsg] = useState(status || '');
    const [errorMsg, setErrorMsg] = useState(pageErrors?.email || '');


    const handleSubmit = (e) => {
        e.preventDefault();
        setErrorMsg('');
        setProcessing(true);

        router.post(route('password.email'), { email }, {
            onSuccess: () => {
                setProcessing(false);
                setSuccessMsg('We have emailed your password reset link. Please check your inbox.');
            },
            onError: (err) => {
                setProcessing(false);
                setErrorMsg(err.email || 'Unable to send reset link. Please try again.');
            },
        });
    };

    return (
        <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
            <Head title="Forgot Password" />

            <AppHeader minimal />

            <main className="flex-1 flex flex-col">
                <div className="bg-primary pt-16 pb-32">
                    <div className="absolute inset-0 z-0 pointer-events-none overflow-hidden">
                        <div className="absolute inset-0 bg-gradient-to-br from-primary via-primary/95 to-primary-container/30" />
                    </div>
                </div>

                <div className="relative z-10 mx-auto -mt-32 mb-24 w-full max-w-6xl px-4 lg:px-8">
                    <div className="flex flex-col lg:flex-row shadow-2xl bg-surface border border-outline-variant/30 overflow-hidden">

                        {/* Left: Branding */}
                        <div className="lg:w-2/5 relative min-h-[500px] flex flex-col justify-center text-white overflow-hidden">
                            <div className="absolute inset-0 z-0">
                                <img
                                    src="https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&q=80"
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                                <div className="absolute inset-0 bg-primary/85 mix-blend-multiply" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-primary/40 to-transparent" />
                            </div>

                            <div className="relative z-10 p-10 lg:p-14">
                                <div className="mb-8">
                                    <img src="/logo.png" alt="Bayanihan Logo" className="h-14 w-14 object-contain" />
                                </div>

                                <h1 className="mb-6 font-headline text-2xl lg:text-3xl font-black leading-tight tracking-tight uppercase">
                                    Bayanihan<br />One Window
                                </h1>

                                <div className="h-1 w-16 bg-secondary-container mb-8" />

                                <p className="max-w-xs text-base text-white/80 leading-relaxed font-medium">
                                    Connecting government agencies for seamless migrant worker assistance across Region VII.
                                </p>
                            </div>
                        </div>

                        {/* Right: Form */}
                        <div className="lg:w-3/5 p-10 lg:p-14 bg-surface">
                            <div className="max-w-md mx-auto">
                                <div className="mb-8 flex items-center gap-3 border-b border-outline-variant pb-4">
                                    <span className="material-symbols-outlined text-primary text-2xl">lock_reset</span>
                                    <h2 className="font-headline text-xl font-bold">Reset Password</h2>
                                </div>

                                <p className="mb-8 text-sm text-on-surface-variant leading-relaxed">
                                    Forgot your password? No problem. Just enter your email address and we will send you a link to reset your password.
                                </p>

                                {successMsg && (
                                    <div className="mb-6 bg-primary-fixed p-4 border border-primary/20 flex items-center gap-3">
                                        <span className="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                                        <p className="text-xs font-semibold text-primary">{successMsg}</p>
                                    </div>
                                )}

                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {errorMsg && (
                                        <div className="bg-error-container p-4 border border-error/20 flex items-center gap-3">
                                            <span className="material-symbols-outlined text-error text-[20px]">alert_circle</span>
                                            <p className="text-xs font-semibold text-on-error-container">{errorMsg}</p>
                                        </div>
                                    )}

                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Work Email Address</label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">alternate_email</span>
                                            <input
                                                type="email"
                                                value={email}
                                                onChange={(e) => { setEmail(e.target.value); setErrorMsg(''); }}
                                                className="w-full border border-outline bg-surface-container px-4 py-3 pl-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                                placeholder="you@agency.gov.ph"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing}
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
                                    Back to Sign In
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}

function AppHeader({ minimal }) {
    return (
        <nav className="fixed top-0 z-50 w-full border-b border-outline-variant bg-surface-bright">
            <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 md:px-8">
                <Link href="/" className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-surface-bright">
                        <span className="material-symbols-outlined text-2xl text-primary">handshake</span>
                    </div>
                    <div className="flex flex-col">
                        <span className="font-headline text-[18px] font-bold text-primary">Bayanihan One Window</span>
                        <span className="font-label text-[12px] font-medium uppercase tracking-wide text-on-surface-variant">DMW Region VII</span>
                    </div>
                </Link>
                {!minimal && (
                    <div className="hidden items-center gap-8 md:flex">
                        <Link href="/" className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Home</Link>
                        <Link href={route('track.index')} className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Track Your Case</Link>
                        <Link href={route('partners')} className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Partners</Link>
                        <Link href={route('contact')} className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Contact</Link>
                    </div>
                )}
                <Link href={route('register')}>
                    <button className="font-label text-sm font-semibold text-primary hover:underline bg-transparent border-none cursor-pointer">Create Account</button>
                </Link>
            </div>
        </nav>
    );
}
