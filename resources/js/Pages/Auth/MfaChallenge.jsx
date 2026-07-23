import { useState, useRef, useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';

export default function MfaChallenge() {
    const { errors: pageErrors } = usePage().props;
    const [mode, setMode] = useState('totp'); // 'totp' | 'recovery'

    const totpForm = useForm({ code: '' });
    const recoveryForm = useForm({ code: '' });
    const cancelForm = useRef(null);

    const form = mode === 'totp' ? totpForm : recoveryForm;
    const setCode = mode === 'totp'
        ? (v) => totpForm.setData('code', v)
        : (v) => recoveryForm.setData('code', v);

    // Focus code input on mount and mode switch
    const codeRef = useRef(null);
    useEffect(() => {
        codeRef.current?.focus();
    }, [mode]);

    function handleTotpSubmit(e) {
        e.preventDefault();
        totpForm.post(route('mfa.challenge.totp'), {
            onFinish: () => totpForm.setData('code', ''),
        });
    }

    function handleRecoverySubmit(e) {
        e.preventDefault();
        recoveryForm.post(route('mfa.challenge.recovery'), {
            onFinish: () => recoveryForm.setData('code', ''),
        });
    }

    function handleCancel() {
        cancelForm.current?.submit();
    }

    const processing = form.processing;
    const code = form.data.code;

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Two-Factor Authentication" />

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
                            <div className="mb-8">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    verified_user
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Two-Factor Verification
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Enter the code from your authenticator app to complete sign in.
                                </p>
                            </div>

                            {/* Error banners */}
                            {pageErrors?.code && (
                                <div className="bg-error-container p-4 border border-error/20 flex items-center gap-3 mb-6">
                                    <span className="material-symbols-outlined text-error text-[20px]">error</span>
                                    <p className="text-xs font-semibold text-on-error-container">{pageErrors.code}</p>
                                </div>
                            )}

                            {mode === 'totp' && (
                                <form onSubmit={handleTotpSubmit} className="space-y-6">
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Authentication Code
                                        </label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
                                                pin
                                            </span>
                                            <input
                                                ref={codeRef}
                                                type="text"
                                                inputMode="numeric"
                                                autoComplete="one-time-code"
                                                value={code}
                                                onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 text-sm font-mono tracking-[0.5em] text-center focus:border-primary focus:outline-none rounded-none"
                                                placeholder="000000"
                                                maxLength={6}
                                                required
                                            />
                                        </div>
                                        {totpForm.errors.code && (
                                            <p className="mt-2 text-sm text-red-600">{totpForm.errors.code}</p>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing || code.length !== 6}
                                        className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                    >
                                        {processing ? 'Verifying...' : 'Verify'}
                                    </button>
                                </form>
                            )}

                            {mode === 'recovery' && (
                                <form onSubmit={handleRecoverySubmit} className="space-y-6">
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Recovery Code
                                        </label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
                                                key
                                            </span>
                                            <input
                                                ref={codeRef}
                                                type="text"
                                                inputMode="text"
                                                autoComplete="off"
                                                value={code}
                                                onChange={(e) => setCode(e.target.value.trim().slice(0, 20))}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 text-sm font-mono focus:border-primary focus:outline-none rounded-none"
                                                placeholder="Enter your recovery code"
                                                required
                                            />
                                        </div>
                                        {recoveryForm.errors.code && (
                                            <p className="mt-2 text-sm text-red-600">{recoveryForm.errors.code}</p>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing || !code}
                                        className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                    >
                                        {processing ? 'Verifying...' : 'Verify Recovery Code'}
                                    </button>
                                </form>
                            )}

                            {/* Toggle between TOTP and recovery */}
                            <div className="mt-6 text-center">
                                {mode === 'totp' ? (
                                    <button
                                        type="button"
                                        onClick={() => { setMode('recovery'); recoveryForm.clearErrors(); recoveryForm.setData('code', ''); }}
                                        className="text-xs font-bold text-primary hover:underline"
                                    >
                                        Use a recovery code instead
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => { setMode('totp'); totpForm.clearErrors(); totpForm.setData('code', ''); }}
                                        className="text-xs font-bold text-primary hover:underline"
                                    >
                                        Use authenticator app instead
                                    </button>
                                )}
                            </div>

                            {/* Cancel */}
                            <div className="mt-8 text-center">
                                <form ref={cancelForm} method="POST" action={route('mfa.challenge.cancel')}>
                                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                                </form>
                                <button
                                    type="button"
                                    onClick={handleCancel}
                                    className="flex items-center justify-center gap-2 text-sm font-bold text-on-surface-variant hover:text-primary mx-auto transition-colors"
                                >
                                    <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                                    Back to Sign In
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
