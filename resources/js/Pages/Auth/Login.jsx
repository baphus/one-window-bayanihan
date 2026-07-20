import { useState, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import TurnstileWidget from '@/Components/TurnstileWidget';

export default function Login({ status, canResetPassword }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(true);
    const [loginError, setLoginError] = useState('');
    const [processing, setProcessing] = useState(false);
    const [turnstileToken, setTurnstileToken] = useState('');

    const loginForm = useRef(null);

    const handleSubmit = (e) => {
        e.preventDefault();
        setLoginError('');
        setProcessing(true);

        router.post(route('login'), { email, password, remember, cf_turnstile_response: turnstileToken }, {
            onFinish: () => setProcessing(false),
            onError: (err) => {
                setLoginError(err.email || 'Invalid email or password.');
            },
        });
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Log in" />

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
                            <div>
                                <div className="mb-8">
                                    <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                        lock
                                    </span>

                                    <h2 className="font-headline text-2xl font-bold text-slate-900">
                                        Sign In
                                    </h2>

                                    <p className="mt-2 text-sm text-slate-500">
                                        Sign in using your government account credentials.
                                    </p>
                                </div>

                                <form ref={loginForm} onSubmit={handleSubmit} className="space-y-6">
                                    {loginError && (
                                        <div className="bg-error-container p-4 border border-error/20 flex items-center gap-3 mb-6">
                                            <span className="material-symbols-outlined text-error text-[20px]">error</span>
                                            <p className="text-xs font-semibold text-on-error-container">{loginError}</p>
                                        </div>
                                    )}

                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email Address</label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">alternate_email</span>
                                            <input
                                                type="email"
                                                value={email}
                                                onChange={(e) => setEmail(e.target.value)}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <div className="flex justify-between mb-2">
                                            <label className="block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Password</label>
                                            {canResetPassword && (
                                                <Link href={route('password.request')} className="text-xs font-bold text-primary hover:underline">Forgot Access?</Link>
                                            )}
                                            <Link href={route('forgot-email')} className="text-xs font-bold text-primary hover:underline">Forgot Email?</Link>
                                        </div>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">lock</span>
                                            <input
                                                type={showPassword ? 'text' : 'password'}
                                                value={password}
                                                onChange={(e) => setPassword(e.target.value)}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 pr-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                                required
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword(!showPassword)}
                                                className="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-primary"
                                            >
                                                <span className="material-symbols-outlined text-[20px]">{showPassword ? 'visibility_off' : 'visibility'}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <input
                                            id="remember"
                                            type="checkbox"
                                            checked={remember}
                                            onChange={(e) => setRemember(e.target.checked)}
                                            className="h-4 w-4 border-outline-variant bg-surface-container text-primary focus:ring-primary rounded-none"
                                        />
                                        <label htmlFor="remember" className="text-xs font-bold uppercase tracking-widest text-on-surface-variant cursor-pointer select-none">
                                            Remember Me
                                        </label>
                                    </div>

                                    <TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} />

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                    >
                                        {processing ? 'Verifying...' : 'Sign In'}
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
