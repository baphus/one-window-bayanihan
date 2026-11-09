import { useRef, useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import OtpInput from '@/Components/OtpInput';

export default function TrackingVerify({ tracker_number, email, hint }) {
  const [otp, setOtp] = useState('');
  const [error, setError] = useState('');
  const [processing, setProcessing] = useState(false);
  const [resendCooldown, setResendCooldown] = useState(0);
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

  const handleVerify = async (event) => {
    event.preventDefault();
    const otpValue = otp;

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
        setOtp('');
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
              DMW Region VII · Secure request access
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
              <OtpInput
                id="tracking-otp"
                label="Verification code"
                value={otp}
                onChange={(value) => { setOtp(value); setError(''); }}
                error={error}
                disabled={processing}
              />

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
                  'Verify and View Request'
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
                Never share your one-time code. Your request information remains hidden until verification succeeds.
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
