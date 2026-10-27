import { useRef, useMemo, useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import FaqSection from '@/Components/landing/FaqSection';
import ChatBot from '@/Components/ChatBot';
import InputError from '@/Components/InputError';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import TurnstileWidget from '@/Components/TurnstileWidget';
import useInView from '@/Hooks/useInView';

import useClientValidation from '@/Hooks/useClientValidation';
import safeRoute from '@/utils/safeRoute';
import { z } from 'zod';
import { getTurnstileError } from '@/lib/turnstile';

export default function TrackingPortal() {
  const { turnstile, tracker_number: initialTrackerNumber } = usePage().props;
  const [turnstileToken, setTurnstileToken] = useState('');
  const [turnstileStatus, setTurnstileStatus] = useState('idle');
  const [formRef, formVisible] = useInView();
  const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
    tracker_number: initialTrackerNumber || '',
    email: '',
    cf_turnstile_response: '',
  });
  const initialRef = useRef({ tracker_number: initialTrackerNumber || '', email: '' });
  const hasDirty = useMemo(() => (
    data.tracker_number !== initialRef.current.tracker_number
    || data.email !== initialRef.current.email
  ), [data]);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(hasDirty);

  const localSchema = z.object({
    tracker_number: z.string().min(1, 'Please enter your Tracking ID.'),
    email: z
      .string()
      .min(1, 'Please enter your email address.')
      .email('Please enter a valid email address.'),
  });

  const { validate } = useClientValidation(localSchema, data, setError);

  const handleTrackSubmit = () => {
    const normalized = data.tracker_number.trim().toUpperCase();
    setData('tracker_number', normalized);
    clearErrors();
    if (!validate()) return;

    if (turnstile?.enabled) {
      const turnstileError = getTurnstileError({ token: turnstileToken, status: turnstileStatus });
      if (turnstileError) {
        setError('captcha', turnstileError);
        return;
      }
    }

    bypassNext();
    setData('cf_turnstile_response', turnstileToken);
    post(route('track.send-otp'));
  };

  const handleTrackerChange = (value) => {
    setData('tracker_number', value.toUpperCase());
  };

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Track Your Case" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1 pt-20">
        <section className="relative flex min-h-[300px] w-full items-center justify-center overflow-hidden bg-primary">
          <div className="absolute inset-0 z-0">
            <div className="absolute inset-0 bg-gradient-to-br from-primary via-primary/90 to-primary-container/30"></div>
          </div>

          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <div className="mx-auto max-w-3xl">
              <h1 className="mb-4 font-headline text-3xl font-extrabold leading-tight tracking-tight text-white sm:text-4xl md:text-5xl">
                Track Your Case
              </h1>
              <p className="mx-auto mb-10 max-w-2xl text-sm sm:text-base md:text-lg leading-relaxed text-white/80">
                Enter your unique case tracking number below to view the
                real-time status and history of your OFW application or
                request safely across all partner agencies.
              </p>
            </div>
          </div>
        </section>

        <section className="relative -mt-12 pb-24 px-4 md:-mt-16">
          <div className="mx-auto max-w-3xl">
            <div ref={formRef} className={`rounded-xl border border-slate-200 bg-white p-5 shadow-lg md:p-8 md:shadow-2xl owb-reveal ${formVisible ? 'is-visible' : ''}`}>
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
                Step 1 of 2
              </span>

              <h2 className="mt-4 font-headline text-[26px] font-extrabold tracking-tight text-slate-900 sm:text-[30px]">
                Track your case securely
              </h2>
              <p className="mt-2 text-[14px] leading-relaxed text-slate-600">
                Enter your tracking number. We&rsquo;ll send a one-time code to the registered
                email associated with your case.
              </p>

              <form
                className="mt-6 flex flex-col gap-4"
                onSubmit={(e) => { e.preventDefault(); handleTrackSubmit(); }}
              >
                <div>
                  <label htmlFor="tracker-number" className="block text-[13px] font-bold text-slate-700">
                    Tracking number
                  </label>
                  <input
                    id="tracker-number"
                    type="text"
                    value={data.tracker_number}
                    onChange={(e) => handleTrackerChange(e.target.value)}
                    placeholder="OWB-2026-001284"
                    className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-[14px] text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    aria-label="Tracker Number"
                    required
                  />
                  <InputError message={errors.tracker_number} className="mt-1" />
                </div>

                <div>
                  <label htmlFor="tracker-email" className="block text-[13px] font-bold text-slate-700">
                    Registered email address
                  </label>
                  <input
                    id="tracker-email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="you@example.com"
                    className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-[14px] text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    aria-label="Email Address"
                    required
                  />
                  <InputError message={errors.email} className="mt-1" />
                </div>

                {turnstile?.enabled && (
                  <div className="text-center">
                    <TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} onStatusChange={setTurnstileStatus} />
                    <InputError message={errors.captcha} className="mt-1" />
                  </div>
                )}

                <button
                  type="submit"
                  disabled={processing}
                  className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3.5 text-[14px] font-bold text-white shadow-md transition-all hover:bg-primary-container active:scale-[0.99] disabled:opacity-60"
                >
                  <span className="material-symbols-outlined text-[18px]" aria-hidden="true">mail</span>
                  {processing ? 'Sending OTP...' : 'Send One-Time Code'}
                </button>
              </form>

              {/* Privacy note */}
              <div className="mt-5 rounded-lg border border-info/25 bg-info-container/40 px-4 py-3.5">
                <div className="flex gap-3">
                  <span className="material-symbols-outlined mt-px shrink-0 text-[18px] leading-none text-info" aria-hidden="true">info</span>
                  <div className="text-[13px]">
                    <p className="font-bold text-slate-900">Your case stays private</p>
                    <p className="mt-0.5 leading-relaxed text-slate-600">
                      No case details are displayed until the verification code is confirmed.
                    </p>
                  </div>
                </div>
              </div>

              <p className="mt-5 text-[13px] text-slate-500">Can&rsquo;t access your registered email?</p>
              <a
                href={safeRoute('contact', undefined, '/contact')}
                className="mt-2 flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[13px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50"
              >
                Contact DMW
              </a>
            </div>
          </div>
        </section>

        <FaqSection />
      </main>

      <AppFooter />
      <ChatBot />
      {UnsavedModal}
    </div>
  );
}
