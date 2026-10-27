import { useRef, useState, useEffect, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';

export default function TrackingVerify({ tracker_number, email, hint }) {
  const [otp, setOtp] = useState(['', '', '', '', '', '']);
  const [error, setError] = useState('');
  const [processing, setProcessing] = useState(false);
  const [resendCooldown, setResendCooldown] = useState(0);
  const otpRefs = useRef([]);
  const cooldownInterval = useRef(null);

  useEffect(() => {
    setResendCooldown(30);
  }, []);

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

  const handleOtpChange = (index, value) => {
    if (value.length > 1) return;
    const sanitized = value.replace(/\D/g, '');
    const nextOtp = [...otp];
    nextOtp[index] = sanitized;
    setOtp(nextOtp);
    setError('');

    if (sanitized && index < nextOtp.length - 1) {
      otpRefs.current[index + 1]?.focus();
    }
  };

  const handleOtpKeyDown = (index, event) => {
    if (event.key === 'Backspace' && otp[index] === '' && index > 0) {
      otpRefs.current[index - 1]?.focus();
    }
  };

  const handlePaste = (event) => {
    event.preventDefault();
    const value = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    const nextOtp = ['', '', '', '', '', ''];
    value.split('').forEach((digit, index) => { nextOtp[index] = digit; });
    setOtp(nextOtp);
    setError('');
    if (value.length > 0) {
      otpRefs.current[Math.min(value.length - 1, 5)]?.focus();
    }
  };

  const handleVerify = async (event) => {
    event.preventDefault();
    const otpValue = otp.join('');

    if (otpValue.length !== 6) {
      setError('Please enter the complete 6-digit code.');
      return;
    }

    setProcessing(true);

    router.post(route('track.verify-otp'), {
      tracker_number,
      email,
      otp: otpValue,
    }, {
      onError: (err) => {
        setError(err.otp || 'Invalid or expired OTP.');
        setProcessing(false);
      },
      onFinish: () => setProcessing(false),
    });
  };

  const handleResendOtp = (e) => {
    e.preventDefault();
    if (resendCooldown > 0) return;

    setProcessing(true);
    setError('');

    router.post(route('track.send-otp'), { tracker_number, email }, {
      onSuccess: () => {
        setProcessing(false);
        setResendCooldown(30);
        setOtp(['', '', '', '', '', '']);
        otpRefs.current[0]?.focus();
        autoFilled.current = false;
      },
      onError: () => {
        setProcessing(false);
        setError('Failed to resend OTP. Please try again.');
      },
    });
  };

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Verify OTP" />
      <AppHeader />

      <main className="flex flex-1 items-center justify-center px-4 pb-16 pt-36">
        <div className="w-full max-w-md mx-auto">
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            {/* Program identity */}
            <div className="flex items-center gap-2.5">
              <img src="/logo.png" alt="One Window Bayanihan logo" className="h-9 w-9 shrink-0 object-contain" />
              <span className="min-w-0 leading-tight">
                <span className="block font-headline text-[13px] font-extrabold tracking-tight text-blue-950">
                  One Window Bayanihan
                </span>
                <span className="block text-[8px] font-bold uppercase tracking-[0.14em] text-slate-500">
                  Assistance Program
                </span>
              </span>
            </div>

            <p className="mt-5 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">
              DMW Region VII · Secure case access
            </p>
            <span className="mt-3 inline-flex items-center gap-1.5 rounded-md border border-primary/25 bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary">
              <span className="h-1.5 w-1.5 rounded-full bg-current" />
              Step 2 of 2
            </span>

            <h2 className="mt-4 font-headline text-[26px] font-extrabold tracking-tight text-slate-900">
              Check your registered email
            </h2>
            <p className="mt-2 text-[14px] leading-relaxed text-slate-600">
              Enter the one-time code sent to the email registered for the tracking number you provided
              {hint ? <> (<span className="font-semibold text-slate-800">{hint}</span>)</> : null}.
            </p>

            <form onSubmit={handleVerify} className="mt-6">
              <label htmlFor="otp-0" className="block text-[13px] font-bold text-slate-700">
                Verification code
              </label>
              <div
                id="otp-group"
                aria-label="Verification code"
                className="mt-2 flex justify-start gap-2"
                onPaste={handlePaste}
              >
                {otp.map((digit, index) => (
                  <input
                    key={`otp-${index}`}
                    id={`otp-${index}`}
                    ref={(el) => { otpRefs.current[index] = el; }}
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={1}
                    value={digit}
                    onChange={(e) => handleOtpChange(index, e.target.value)}
                    onKeyDown={(e) => handleOtpKeyDown(index, e)}
                    aria-invalid={Boolean(error)}
                    className={`h-14 w-full min-w-0 rounded-lg border bg-white text-center text-[20px] font-bold text-slate-900 transition-colors focus:outline-none focus:ring-1 ${
                      error
                        ? 'border-red-400 text-red-700 focus:border-red-500 focus:ring-red-400'
                        : 'border-slate-200 focus:border-primary focus:ring-primary'
                    }`}
                  />
                ))}
              </div>

              {error ? (
                <p className="mt-2 flex items-center gap-1.5 text-[12px] font-medium text-red-600">
                  <span className="material-symbols-outlined text-[14px]" aria-hidden="true">error</span>
                  {error}
                </p>
              ) : null}

              <button
                type="submit"
                disabled={processing}
                className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3.5 text-[14px] font-bold text-white shadow-md transition-all hover:bg-primary-container active:scale-[0.99] disabled:opacity-60"
              >
                {processing ? (
                  <>
                    <span className="material-symbols-outlined animate-spin text-[18px]" aria-hidden="true">sync</span>
                    Verifying...
                  </>
                ) : (
                  'Verify and View Case'
                )}
              </button>

              <div className="mt-4 grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={handleResendOtp}
                  disabled={resendCooldown > 0 || processing}
                  className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend Code'}
                </button>
                <button
                  type="button"
                  onClick={() => router.get(route('track.index'))}
                  className="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-[13px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50"
                >
                  Change Tracking Number
                </button>
              </div>

              <p className="mt-4 flex items-start gap-2 text-[12px] leading-relaxed text-slate-500">
                <span className="material-symbols-outlined mt-px shrink-0 text-[16px] leading-none text-slate-400" aria-hidden="true">shield</span>
                Never share your one-time code. Your case information remains hidden until verification succeeds.
              </p>
            </form>
          </div>

          <button
            type="button"
            onClick={() => router.get(route('track.index'))}
            className="mt-6 flex items-center justify-center gap-2 text-sm font-bold text-slate-500 hover:text-primary mx-auto transition-colors"
          >
            <span className="material-symbols-outlined text-[18px]">arrow_back</span>
            Return to Tracking
          </button>
        </div>
      </main>

      <AppFooter />
      <ChatBot />
    </div>
  );
}
