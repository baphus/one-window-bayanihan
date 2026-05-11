import { useState, useRef, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';

export default function Login({ status, canResetPassword }) {
    const { errors: pageErrors, step: initialStep, debug_otp } = usePage().props;
    const [step, setStep] = useState(initialStep || 'login');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [loginError, setLoginError] = useState('');
    const [hint, setHint] = useState('');
    const [otp, setOtp] = useState(['', '', '', '', '', '']);
    const [otpError, setOtpError] = useState('');
    const [processing, setProcessing] = useState(false);
    const otpRefs = useRef([]);
    const autoFilled = useRef(false);

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
            onError: (err) => {
                setOtpError(err.otp || 'Invalid or expired OTP.');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
            <Head title="Log in" />

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
                            {step === 'login' && (
                                <div className="max-w-md mx-auto">
                                    <div className="mb-8 flex items-center gap-3 border-b border-outline-variant pb-4">
                                        <span className="material-symbols-outlined text-primary text-2xl">lock_open</span>
                                        <h2 className="font-headline text-xl font-bold">Sign In</h2>
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
                                                <span className="material-symbols-outlined text-error text-[20px]">alert_circle</span>
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
