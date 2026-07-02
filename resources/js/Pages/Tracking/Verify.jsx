import { useRef, useState, useEffect, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import ChatBot from '@/Components/ChatBot';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function TrackingVerify({ tracker_number, email, hint, debug_otp }) {
  const [otp, setOtp] = useState(['', '', '', '', '', '']);
  const initialOtpRef = useRef(['', '', '', '', '', '']);
  const hasDirty = useMemo(() => otp.some((d, i) => d !== initialOtpRef.current[i]), [otp]);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);
  const [error, setError] = useState('');
  const [processing, setProcessing] = useState(false);
  const otpRefs = useRef([]);
  const autoFilled = useRef(false);

  useEffect(() => {
    if (debug_otp && !autoFilled.current) {
      autoFilled.current = true;
      const digits = debug_otp.split('').slice(0, 6);
      const filled = ['', '', '', '', '', ''];
      digits.forEach((d, i) => { filled[i] = d; });
      setOtp(filled);
    }
  }, [debug_otp]);

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
      setError('Please enter the 6-digit OTP to continue.');
      return;
    }

    setProcessing(true);

    bypassNext();
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

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Verify OTP" />
      <AppHeader />

      <main className="flex flex-1 items-center justify-center px-4 pb-16 pt-36">
        <section className="w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 sm:p-10 text-center shadow-lg">
          <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-primary-fixed">
            <span className="material-symbols-outlined text-primary text-3xl" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>verified_user</span>
          </div>
          <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Security Check</p>
          <h1 className="mt-2 text-2xl font-black uppercase tracking-tight text-on-surface">Verify Identity</h1>
          <p className="mt-3 text-sm leading-6 text-on-surface-variant">
            For security, verify your identity before we show progress for tracking ID <span className="font-bold text-on-surface">{tracker_number}</span>.
          </p>

          <form className="mt-8 space-y-6" onSubmit={handleVerify}>
            <div className="flex justify-center gap-2 sm:gap-3">
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
                  onPaste={handlePaste}
                  className="h-14 w-14 sm:h-16 sm:w-16 rounded-xl border border-outline bg-surface-container text-center text-xl sm:text-2xl font-bold text-primary outline-none transition focus:border-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                />
              ))}
            </div>

            {error && <p className="text-xs font-semibold text-error">{error}</p>}

            {debug_otp && (
              <div className="mt-4 rounded-lg bg-tertiary-container border border-tertiary p-3 text-xs font-bold text-on-tertiary-container uppercase tracking-wider">
                Debug Mode — OTP: {debug_otp}
              </div>
            )}

            <div className="mt-8 flex flex-col items-center gap-3 sm:flex-row">
              <button
                type="button"
                onClick={() => router.get(route('track.index'))}
                className="w-full rounded-xl border border-outline px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-on-surface-variant transition hover:bg-surface-container-high sm:w-auto sm:flex-1"
              >
                Back
              </button>
              <button
                type="submit"
                disabled={processing}
                className="w-full rounded-xl bg-primary px-4 py-3.5 text-xs font-bold uppercase tracking-widest text-on-primary transition hover:bg-primary-fixed-dim disabled:opacity-60 sm:w-auto sm:flex-1"
              >
                {processing ? 'Verifying...' : 'Verify OTP'}
              </button>
            </div>
          </form>
        </section>
      </main>

      <AppFooter />
      <ChatBot />
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </div>
  );
}
