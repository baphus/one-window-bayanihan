import { useRef, useState, useEffect, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';

export default function TrackingVerify({ tracker_number, email, hint, debug_otp }) {
  const [otp, setOtp] = useState(['', '', '', '', '', '']);
  const [error, setError] = useState('');
  const [processing, setProcessing] = useState(false);
  const [resendCooldown, setResendCooldown] = useState(0);
  const otpRefs = useRef([]);
  const autoFilled = useRef(false);
  const cooldownInterval = useRef(null);

  useEffect(() => {
    if (debug_otp && !autoFilled.current) {
      autoFilled.current = true;
      const digits = debug_otp.split('').slice(0, 6);
      const filled = ['', '', '', '', '', ''];
      digits.forEach((d, i) => { filled[i] = d; });
      setOtp(filled);
    }
  }, [debug_otp]);

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
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center">
            <div className="mb-8 flex flex-col items-center gap-4">
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 border border-blue-100 text-blue-900 shadow-sm">
                <span className="material-symbols-outlined text-[36px]" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>verified_user</span>
              </div>
              <div>
                <h2 className="font-headline text-xl font-extrabold text-slate-900">
                  Verify Your Identity
                </h2>
                <p className="text-sm text-slate-500 mt-1.5 leading-relaxed max-w-xs mx-auto">
                  For security, enter the 6-digit verification code sent to <span className="font-bold text-slate-900">{hint}</span>
                </p>
              </div>
            </div>

            <form onSubmit={handleVerify}>
              <div className="mb-8 flex justify-center gap-2.5" onPaste={handlePaste}>
                {otp.map((digit, index) => (
                  <input
                    key={`otp-${index}`}
                    ref={(el) => { otpRefs.current[index] = el; }}
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={1}
                    value={digit}
                    onChange={(e) => handleOtpChange(index, e.target.value)}
                    onKeyDown={(e) => handleOtpKeyDown(index, e)}
                    className="h-14 w-11 border-2 border-slate-200 bg-white text-center text-xl font-bold text-slate-900 focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900 rounded-lg transition-colors"
                  />
                ))}
              </div>

              {error && (
                <div className="mb-4 flex items-center justify-center gap-2 text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5">
                  <span className="material-symbols-outlined text-[14px]">error</span>
                  {error}
                </div>
              )}

              <button
                type="submit"
                disabled={processing}
                className="w-full bg-blue-900 text-white rounded-lg px-8 py-4 text-sm font-bold shadow-md hover:bg-blue-800 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2"
              >
                {processing ? (
                  <>
                    <span className="material-symbols-outlined text-[18px] animate-spin">sync</span>
                    Verifying...
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-[18px]">lock_open</span>
                    Verify &amp; Continue
                  </>
                )}
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
                  <span className="text-slate-400">
                    Resend code in <span className="text-blue-900">{resendCooldown}s</span>
                  </span>
                ) : (
                  <span className="text-blue-900 hover:text-blue-700 underline underline-offset-2">
                    Resend code
                  </span>
                )}
              </button>
            </div>

            {debug_otp && (
              <div className="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs font-bold text-amber-700 uppercase tracking-wider">
                Debug Mode — OTP: {debug_otp}
              </div>
            )}
          </div>

          <button
            type="button"
            onClick={() => router.get(route('track.index'))}
            className="mt-6 flex items-center justify-center gap-2 text-sm font-bold text-slate-500 hover:text-blue-900 mx-auto transition-colors"
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
