import { useState, useRef, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';

export default function Login({ status, canResetPassword }) {
    const { errors: pageErrors, step: initialStep, email: initialEmail, hint: initialHint, debug_otp } = usePage().props;
    const [step, setStep] = useState(initialStep || 'login');
    const [email, setEmail] = useState(initialEmail || '');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loginError, setLoginError] = useState('');
    const [hint, setHint] = useState(initialHint || '');
    const [otp, setOtp] = useState(['', '', '', '', '', '']);
    const [otpError, setOtpError] = useState('');
    const [processing, setProcessing] = useState(false);
    const [selectedRole, setSelectedRole] = useState(null);
    const [resendCooldown, setResendCooldown] = useState(0);
    const otpRefs = useRef([]);
    const autoFilled = useRef(false);
    const cooldownInterval = useRef(null);
    const [mfaMode, setMfaMode] = useState('totp'); // 'totp' | 'recovery'
    const [mfaTotp, setMfaTotp] = useState(['', '', '', '', '', '']);
    const [mfaTotpError, setMfaTotpError] = useState('');
    const [mfaRecoveryCode, setMfaRecoveryCode] = useState('');
    const [mfaRecoveryError, setMfaRecoveryError] = useState('');
    const mfaTotpRefs = useRef([]);


    useEffect(() => {
        if (step === 'otp') {
            setResendCooldown(30);
        }
    }, [step]);

    useEffect(() => {
        if (resendCooldown > 0) {
            cooldownInterval.current = setInterval(() => {
                setResendCooldown((prev) => prev - 1);
            }, 1000);
        }
        return () => {
            if (cooldownInterval.current) {
                clearInterval(cooldownInterval.current);
                cooldownInterval.current = null;
            }
        };
    }, [resendCooldown > 0]);

    useEffect(() => {
        if (debug_otp && step === 'otp' && !autoFilled.current) {
            autoFilled.current = true;
            const digits = debug_otp.split('').slice(0, 6);
            const filled = ['', '', '', '', '', ''];
            digits.forEach((d, i) => { filled[i] = d; });
            setOtp(filled);
        }
    }, [debug_otp, step]);

    const loginForm = useRef(null);
    const errorMsg = pageErrors?.email || loginError;

    const applyMockCredentials = (e, creds) => {
        e.preventDefault();
        setEmail(creds.email);
        setPassword(creds.password);
        setLoginError('');
        setSelectedRole(null);
    };

    const handleRoleSelect = (role) => {
        setSelectedRole(role);
        setLoginError('');
        if (role === 'case_manager') {
            setEmail('case@bayanihan.gov.ph');
            setPassword('password');
        } else if (role === 'agency') {
            setEmail('owwa@bayanihan.gov.ph');
            setPassword('password');
        }
    };

    const handleLoginSubmit = (e) => {
        e.preventDefault();
        setLoginError('');
        setProcessing(true);

        router.post(route('login.init'), { email, password }, {
            onSuccess: (page) => {
                setProcessing(false);
                setStep('otp');
                setHint(page.props?.hint || email.replace(/(.{2}).+(@.+)/, '$1***$2'));
            },
            onError: (err) => {
                setProcessing(false);
                setLoginError(err.email || 'Invalid email or password.');
            },
        });
    };

    const handleOtpChange = (index, value) => {
        if (value.length > 1) return;
        const nextOtp = [...otp];
        nextOtp[index] = value;
        setOtp(nextOtp);
        setOtpError('');

        if (value && index < 5) {
            otpRefs.current[index + 1]?.focus();
        }
    };

    const handleOtpKeyDown = (index, e) => {
        if (e.key === 'Backspace' && otp[index] === '' && index > 0) {
            otpRefs.current[index - 1]?.focus();
        }
    };

    const handlePaste = (e) => {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
        const nextOtp = ['', '', '', '', '', ''];
        pasted.forEach((char, idx) => { nextOtp[idx] = char; });
        setOtp(nextOtp);
        const lastIdx = Math.min(pasted.length - 1, 5);
        if (lastIdx >= 0) otpRefs.current[lastIdx]?.focus();
    };

    const handleVerifyOtp = (e) => {
        e.preventDefault();
        const otpValue = otp.join('');

        if (otpValue.length !== 6) {
            setOtpError('Please enter the complete 6-digit code.');
            return;
        }

        setProcessing(true);

        router.post(route('login.verify-otp'), { email, otp: otpValue }, {
            onSuccess: (page) => {
                setProcessing(false);
                if (page.props.step === 'mfa-challenge') {
                    setStep('mfa-challenge');
                    setHint(page.props?.hint || '');
                }
            },
            onError: (err) => {
                setOtpError(err.otp || 'Invalid or expired OTP.');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleResendOtp = (e) => {
        e.preventDefault();
        if (resendCooldown > 0) return;

        setProcessing(true);
        setOtpError('');

        router.post(route('login.resend-otp'), { email }, {
            onSuccess: (page) => {
                setProcessing(false);
                setResendCooldown(30);
                setOtp(['', '', '', '', '', '']);
                otpRefs.current[0]?.focus();
                autoFilled.current = false;
                if (page.props?.hint) setHint(page.props.hint);
            },
            onError: (err) => {
                setProcessing(false);
                setOtpError(err.email || 'Failed to resend OTP. Please try again.');
            },
        });
    };

    const handleMfaTotpChange = (index, value) => {
        if (value.length > 1) return;
        const next = [...mfaTotp];
        next[index] = value;
        setMfaTotp(next);
        setMfaTotpError('');
        if (value && index < 5) {
            mfaTotpRefs.current[index + 1]?.focus();
        }
    };

    const handleMfaTotpKeyDown = (index, e) => {
        if (e.key === 'Backspace' && mfaTotp[index] === '' && index > 0) {
            mfaTotpRefs.current[index - 1]?.focus();
        }
    };

    const handleMfaPaste = (e) => {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
        const next = ['', '', '', '', '', ''];
        pasted.forEach((char, idx) => { next[idx] = char; });
        setMfaTotp(next);
        const lastIdx = Math.min(pasted.length - 1, 5);
        if (lastIdx >= 0) mfaTotpRefs.current[lastIdx]?.focus();
    };

    const handleVerifyTotp = (e) => {
        e.preventDefault();
        const code = mfaTotp.join('');
        if (code.length !== 6) {
            setMfaTotpError('Please enter the complete 6-digit code.');
            return;
        }
        setProcessing(true);
        router.post(route('login.verify-totp'), { email, otp: code }, {
            onError: (err) => {
                setMfaTotpError(err.otp || 'Invalid authentication code.');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleVerifyRecoveryCode = (e) => {
        e.preventDefault();
        if (!mfaRecoveryCode.trim()) {
            setMfaRecoveryError('Please enter a recovery code.');
            return;
        }
        setProcessing(true);
        router.post(route('login.verify-recovery-code'), { email, recovery_code: mfaRecoveryCode.trim() }, {
            onSuccess: () => setProcessing(false),
            onError: (err) => {
                setMfaRecoveryError(err.recovery_code || 'Invalid recovery code.');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Log in" />

            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex items-center justify-center p-8">
                    <div className="w-full max-w-5xl">
                        <div className="flex flex-col lg:flex-row shadow-2xl bg-surface border border-outline-variant/30 overflow-hidden">

                        {/* Left: Branding */}
                        <div className="lg:w-1/2 relative min-h-[500px] flex flex-col justify-center text-white overflow-hidden">
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
                        <div className="lg:w-1/2 p-10 lg:p-14 bg-surface">
                            {step === 'login' && (
                                <div className="max-w-md mx-auto">
                                    <div className="mb-8 flex items-center gap-3 border-b border-outline-variant pb-4">
                                        <span className="material-symbols-outlined text-primary text-2xl">lock_open</span>
                                        <h2 className="font-headline text-xl font-bold">Sign In</h2>
                                    </div>

                                    <div className="mb-6">
                                        <p className="mb-3 text-xs font-bold uppercase tracking-widest text-on-surface-variant">Log in as</p>
                                        <div className="relative flex border-2 border-outline-variant bg-surface-container">
                                            <div
                                                className={`absolute top-0 bottom-0 w-1/2 bg-primary transition-all duration-200 ease-in-out ${
                                                    selectedRole === 'agency' ? 'left-1/2' : 'left-0'
                                                }`}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => handleRoleSelect('case_manager')}
                                                className={`relative z-10 flex w-1/2 items-center justify-center gap-2 py-3 text-sm font-bold transition-colors duration-200 ${
                                                    selectedRole !== 'agency'
                                                        ? 'text-on-primary'
                                                        : 'text-on-surface-variant hover:text-primary'
                                                }`}
                                            >
                                                <span className="material-symbols-outlined text-[18px]">badge</span>
                                                Case Manager
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleRoleSelect('agency')}
                                                className={`relative z-10 flex w-1/2 items-center justify-center gap-2 py-3 text-sm font-bold transition-colors duration-200 ${
                                                    selectedRole === 'agency'
                                                        ? 'text-on-primary'
                                                        : 'text-on-surface-variant hover:text-primary'
                                                }`}
                                            >
                                                <span className="material-symbols-outlined text-[18px]">account_balance</span>
                                                Agency Focal
                                            </button>
                                        </div>
                                    </div>

                                    <div className="mb-8 bg-surface-container-highest/20 p-5 border border-outline-variant/20 italic">
                                        <div className="flex items-start gap-3 text-xs font-medium text-on-surface-variant">
                                            <span className="material-symbols-outlined text-primary shrink-0 text-[20px]">info</span>
                                            <div>
                                                <p className="font-bold text-primary mb-1 uppercase tracking-tighter">Mock Credentials:</p>
                                                <div className="flex flex-col gap-2">
                                                    <button type="button" onClick={(e) => applyMockCredentials(e, { email: 'admin@bayanihan.gov.ph', password: 'password' })} className="text-left hover:underline">System Admin: admin@bayanihan.gov.ph</button>
                                                    <button type="button" onClick={(e) => applyMockCredentials(e, { email: 'case@bayanihan.gov.ph', password: 'password' })} className="text-left hover:underline">Case Manager: case@bayanihan.gov.ph</button>
                                                    <button type="button" onClick={(e) => applyMockCredentials(e, { email: 'owwa@bayanihan.gov.ph', password: 'password' })} className="text-left hover:underline">Agency: owwa@bayanihan.gov.ph</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <form onSubmit={handleLoginSubmit} className="space-y-6">
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
                                                    className="w-full border border-outline bg-surface-container px-4 py-3 pl-12 text-sm focus:border-primary focus:outline-none rounded-none"
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
                                            </div>
                                            <div className="relative">
                                                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">lock</span>
                                                <input
                                                    type={showPassword ? 'text' : 'password'}
                                                    value={password}
                                                    onChange={(e) => setPassword(e.target.value)}
                                                    className="w-full border border-outline bg-surface-container px-4 py-3 pl-12 pr-12 text-sm focus:border-primary focus:outline-none rounded-none"
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

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                        >
                                            {processing ? 'Verifying...' : 'Sign In'}
                                        </button>
                                    </form>
                                </div>
                            )}

                            {step === 'otp' && (
                                <div className="max-w-md mx-auto text-center">
                                    <div className="mb-10 flex flex-col items-center gap-4">
                                        <div className="h-20 w-20 bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                                            <span className="material-symbols-outlined text-[40px]" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>verified_user</span>
                                        </div>
                                        <h2 className="font-headline text-2xl font-black uppercase text-primary">OTP SENT</h2>
                                        <p className="text-sm text-on-surface-variant leading-relaxed">
                                            For security, enter the 6-digit verification code sent to <span className="font-bold text-on-surface">{hint}</span>
                                        </p>
                                    </div>

                                    <form onSubmit={handleVerifyOtp}>
                                        <div className="mb-10 flex justify-center gap-3" onPaste={handlePaste}>
                                            {otp.map((digit, idx) => (
                                                <input
                                                    key={idx}
                                                    ref={(el) => { otpRefs.current[idx] = el; }}
                                                    type="text"
                                                    inputMode="numeric"
                                                    autoComplete="one-time-code"
                                                    maxLength={1}
                                                    value={digit}
                                                    onChange={(e) => handleOtpChange(idx, e.target.value)}
                                                    onKeyDown={(e) => handleOtpKeyDown(idx, e)}
                                                    className="h-14 w-12 border border-outline bg-surface-container text-center text-xl font-bold focus:border-primary focus:outline-none rounded-none"
                                                />
                                            ))}
                                        </div>

                                        {otpError && (
                                            <p className="text-xs font-semibold text-error mb-4">{otpError}</p>
                                        )}

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                        >
                                            {processing ? 'Verifying...' : 'Verify & Continue'}
                                        </button>
                                    </form>

                                    <div className="mt-6 text-center">
                                        <button
                                            type="button"
                                            onClick={handleResendOtp}
                                            disabled={resendCooldown > 0 || processing}
                                            className="text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {resendCooldown > 0 ? (
                                                <span className="text-on-surface-variant">
                                                    Resend code in <span className="text-primary">{resendCooldown}s</span>
                                                </span>
                                            ) : (
                                                <span className="text-primary hover:text-primary/80 underline underline-offset-2">
                                                    Resend code
                                                </span>
                                            )}
                                        </button>
                                    </div>

                                    {debug_otp && (
                                        <div className="mt-4 rounded bg-amber-50 border border-amber-300 p-3 text-xs font-bold text-amber-700 uppercase tracking-wider">
                                            Debug Mode — OTP: {debug_otp}
                                        </div>
                                    )}

                                    <button
                                        type="button"
                                        onClick={() => { setStep('login'); setOtp(['', '', '', '', '', '']); setOtpError(''); autoFilled.current = false; }}
                                        className="mt-8 flex items-center justify-center gap-2 text-sm font-bold text-on-surface-variant hover:text-primary mx-auto transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                                        Return to Login
                                    </button>
                                </div>
                            )}

                            {step === 'mfa-challenge' && (
                                <div className="max-w-md mx-auto text-center">
                                    {mfaMode === 'totp' ? (
                                        <>
                                            <div className="mb-10 flex flex-col items-center gap-4">
                                                <div className="h-20 w-20 bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                                                    <span className="material-symbols-outlined text-[40px]" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>security</span>
                                                </div>
                                                <h2 className="font-headline text-2xl font-black uppercase text-primary">TWO-FACTOR AUTH</h2>
                                                <p className="text-sm text-on-surface-variant leading-relaxed">
                                                    Enter the 6-digit code from your authenticator app for <span className="font-bold text-on-surface">{hint}</span>
                                                </p>
                                            </div>

                                            <form onSubmit={handleVerifyTotp}>
                                                <div className="mb-10 flex justify-center gap-3" onPaste={handleMfaPaste}>
                                                    {mfaTotp.map((digit, idx) => (
                                                        <input
                                                            key={idx}
                                                            ref={(el) => { mfaTotpRefs.current[idx] = el; }}
                                                            type="text"
                                                            inputMode="numeric"
                                                            autoComplete="one-time-code"
                                                            maxLength={1}
                                                            value={digit}
                                                            onChange={(e) => handleMfaTotpChange(idx, e.target.value)}
                                                            onKeyDown={(e) => handleMfaTotpKeyDown(idx, e)}
                                                            className="h-14 w-12 border border-outline bg-surface-container text-center text-xl font-bold focus:border-primary focus:outline-none rounded-none"
                                                        />
                                                    ))}
                                                </div>

                                                {mfaTotpError && (
                                                    <p className="text-xs font-semibold text-error mb-4">{mfaTotpError}</p>
                                                )}

                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                                >
                                                    {processing ? 'Verifying...' : 'Verify & Continue'}
                                                </button>
                                            </form>

                                            <div className="mt-6 text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => { setMfaMode('recovery'); setMfaRecoveryCode(''); setMfaRecoveryError(''); }}
                                                    className="text-sm font-bold text-primary hover:text-primary/80 underline underline-offset-2"
                                                >
                                                    Use a recovery code instead
                                                </button>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <div className="mb-10 flex flex-col items-center gap-4">
                                                <div className="h-20 w-20 bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                                                    <span className="material-symbols-outlined text-[40px]" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>key</span>
                                                </div>
                                                <h2 className="font-headline text-2xl font-black uppercase text-primary">RECOVERY CODE</h2>
                                                <p className="text-sm text-on-surface-variant leading-relaxed">
                                                    Enter one of your recovery codes to access your account.
                                                </p>
                                            </div>

                                            <form onSubmit={handleVerifyRecoveryCode}>
                                                <div className="mb-6">
                                                    <input
                                                        type="text"
                                                        value={mfaRecoveryCode}
                                                        onChange={(e) => { setMfaRecoveryCode(e.target.value.toUpperCase()); setMfaRecoveryError(''); }}
                                                        placeholder="XXXX-XXXX-XXXX"
                                                        className="w-full border border-outline bg-surface-container px-4 py-3 text-center text-lg font-mono font-bold tracking-widest focus:border-primary focus:outline-none rounded-none uppercase"
                                                        disabled={processing}
                                                        autoComplete="off"
                                                    />
                                                </div>

                                                {mfaRecoveryError && (
                                                    <p className="text-xs font-semibold text-error mb-4">{mfaRecoveryError}</p>
                                                )}

                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                                >
                                                    {processing ? 'Verifying...' : 'Verify Recovery Code'}
                                                </button>
                                            </form>

                                            <div className="mt-6 text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => { setMfaMode('totp'); setMfaTotp(['', '', '', '', '', '']); setMfaTotpError(''); }}
                                                    className="text-sm font-bold text-primary hover:text-primary/80 underline underline-offset-2"
                                                >
                                                    Use authenticator app instead
                                                </button>
                                            </div>
                                        </>
                                    )}

                                    <button
                                        type="button"
                                        onClick={() => { setStep('login'); setMfaTotp(['', '', '', '', '', '']); setMfaTotpError(''); setMfaRecoveryCode(''); setMfaRecoveryError(''); setMfaMode('totp'); }}
                                        className="mt-8 flex items-center justify-center gap-2 text-sm font-bold text-on-surface-variant hover:text-primary mx-auto transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                                        Return to Login
                                    </button>
                                </div>
                            )}

                        </div>
                    </div>
                </div>
            </div>
        </main>

        <AppFooter />
        </div>
    );
}
