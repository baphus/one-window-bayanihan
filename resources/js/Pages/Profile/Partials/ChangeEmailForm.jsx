import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import Section from '@/Components/Section';

export default function ChangeEmailForm({
    initialStep,
    hint: propHint,
    debugOtp: propDebugOtp,
}) {
    const pageProps = usePage().props;
    const stepFromServer = initialStep || pageProps.email_change_step;
    const hintFromServer = propHint || pageProps.email_change_hint || '';
    const debugOtpFromServer = propDebugOtp || pageProps.email_change_debug_otp || '';

    const [step, setStep] = useState(stepFromServer || 'start');
    const [password, setPassword] = useState('');
    const [newEmail, setNewEmail] = useState('');
    const [otp, setOtp] = useState(['', '', '', '', '', '']);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [otpError, setOtpError] = useState('');
    const [emailError, setEmailError] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [resendCooldown, setResendCooldown] = useState(0);
    const otpRefs = useRef([]);
    const autoFilled = useRef(false);
    const cooldownInterval = useRef(null);

    // Sync step from server props
    useEffect(() => {
        if (stepFromServer) setStep(stepFromServer);
    }, [stepFromServer]);

    // Reset cooldown when entering OTP step
    useEffect(() => {
        if (step === 'otp') {
            setResendCooldown(30);
        }
    }, [step]);

    // Cooldown timer
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

    // Debug OTP auto-fill
    useEffect(() => {
        if (debugOtpFromServer && step === 'otp' && !autoFilled.current) {
            autoFilled.current = true;
            const digits = debugOtpFromServer.split('').slice(0, 6);
            const filled = ['', '', '', '', '', ''];
            digits.forEach((d, i) => { filled[i] = d; });
            setOtp(filled);
        }
    }, [debugOtpFromServer, step]);

    function handleInit(e) {
        e.preventDefault();
        router.get(route('profile.email-change.init'));
    }

    function handleSendOtp(e) {
        e.preventDefault();
        setPasswordError('');
        setEmailError('');
        setProcessing(true);

        router.post(route('profile.email-change.send-otp'), {
            password,
            new_email: newEmail,
        }, {
            onError: (errors) => {
                setProcessing(false);
                if (errors.password) setPasswordError(errors.password);
                if (errors.new_email) setEmailError(errors.new_email);
            },
            onFinish: () => setProcessing(false),
        });
    }

    function handleOtpChange(index, value) {
        if (value.length > 1) return;
        const nextOtp = [...otp];
        nextOtp[index] = value;
        setOtp(nextOtp);
        setOtpError('');

        if (value && index < 5) {
            otpRefs.current[index + 1]?.focus();
        }
    }

    function handleOtpKeyDown(index, e) {
        if (e.key === 'Backspace' && otp[index] === '' && index > 0) {
            otpRefs.current[index - 1]?.focus();
        }
    }

    function handlePaste(e) {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
        const nextOtp = ['', '', '', '', '', ''];
        pasted.forEach((char, idx) => { nextOtp[idx] = char; });
        setOtp(nextOtp);
        const lastIdx = Math.min(pasted.length - 1, 5);
        if (lastIdx >= 0) otpRefs.current[lastIdx]?.focus();
    }

    function handleVerifyOtp(e) {
        e.preventDefault();
        const otpValue = otp.join('');

        if (otpValue.length !== 6) {
            setOtpError('Please enter the complete 6-digit code.');
            return;
        }

        setProcessing(true);

        router.post(route('profile.email-change.verify-otp'), {
            new_email: newEmail,
            otp: otpValue,
        }, {
            onError: (errors) => {
                setOtpError(errors.otp || 'Invalid or expired OTP.');
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    }

    function handleResendOtp(e) {
        e.preventDefault();
        if (resendCooldown > 0) return;

        setProcessing(true);
        setOtpError('');

        router.post(route('profile.email-change.send-otp'), {
            new_email: newEmail,
        }, {
            onSuccess: () => {
                setProcessing(false);
                setResendCooldown(30);
                setOtp(['', '', '', '', '', '']);
                otpRefs.current[0]?.focus();
                autoFilled.current = false;
            },
            onError: (errors) => {
                setProcessing(false);
                setOtpError(errors.new_email || 'Failed to resend OTP. Please try again.');
            },
        });
    }

    // Step: start — collapsed card with a button to initiate the flow
    if (step === 'start') {
        return (
            <Section
                title="Change Email"
                description="Update your email address associated with your account."
            >
                <p className="text-sm text-slate-600">
                    Your email address is used for account notifications and password recovery.
                </p>
                <div className="flex items-center gap-4">
                    <PrimaryButton onClick={handleInit}>
                        Change Email
                    </PrimaryButton>
                </div>
            </Section>
        );
    }

    // Step: new-email — password confirmation + new email input
    if (step === 'new-email') {
        return (
            <Section
                title="Change Email Address"
                description="Enter your current password and new email address to begin."
            >
                <div>
                    <InputLabel htmlFor="current_password" value="Current Password" />
                    <TextInput
                        id="current_password"
                        type="password"
                        className="mt-1 block w-full"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        autoComplete="current-password"
                        required
                    />
                    {passwordError && (
                        <p className="mt-2 text-sm text-red-600">{passwordError}</p>
                    )}
                </div>

                <div>
                    <InputLabel htmlFor="new_email" value="New Email Address" />
                    <TextInput
                        id="new_email"
                        type="email"
                        className="mt-1 block w-full"
                        value={newEmail}
                        onChange={(e) => setNewEmail(e.target.value)}
                        autoComplete="email"
                        required
                    />
                    {emailError && (
                        <p className="mt-2 text-sm text-red-600">{emailError}</p>
                    )}
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton onClick={handleSendOtp} disabled={processing}>
                        {processing ? 'Sending...' : 'Send Verification Code'}
                    </PrimaryButton>
                </div>
            </Section>
        );
    }

    // Step: otp — OTP verification
    if (step === 'otp') {
        return (
            <Section
                title="Verify New Email"
                description={
                    hintFromServer
                        ? `Code sent to: ${hintFromServer}`
                        : 'Enter the verification code sent to your new email.'
                }
            >
                {otpError && (
                    <div className="rounded-md bg-red-50 border border-red-200 p-3">
                        <p className="text-sm font-medium text-red-800">{otpError}</p>
                    </div>
                )}

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-2">
                        Verification Code
                    </label>
                    <div className="flex gap-2 justify-center" onPaste={handlePaste}>
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
                                className="h-12 w-10 rounded-md border border-slate-300 bg-white text-center text-lg font-bold focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            />
                        ))}
                    </div>
                </div>

                <div className="flex items-center justify-between">
                    <PrimaryButton onClick={handleVerifyOtp} disabled={processing}>
                        {processing ? 'Verifying...' : 'Verify'}
                    </PrimaryButton>

                    <button
                        type="button"
                        onClick={handleResendOtp}
                        disabled={resendCooldown > 0 || processing}
                        className="text-sm font-semibold text-primary transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {resendCooldown > 0 ? (
                            <span className="text-slate-500">
                                Resend in {resendCooldown}s
                            </span>
                        ) : (
                            <span className="hover:text-primary/80 underline underline-offset-2">
                                Resend Code
                            </span>
                        )}
                    </button>
                </div>

                {debugOtpFromServer && (
                    <div className="rounded-md bg-amber-50 border border-amber-200 p-3">
                        <p className="text-xs font-bold text-amber-800 uppercase tracking-wider">
                            Debug Mode — OTP: {debugOtpFromServer}
                        </p>
                    </div>
                )}
            </Section>
        );
    }

    return null;
}
