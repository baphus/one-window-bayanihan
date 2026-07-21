import { useRef, useMemo, useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import AppButton from '@/Components/landing/AppButton';
import FaqSection from '@/Components/landing/FaqSection';
import ChatBot from '@/Components/ChatBot';
import InputError from '@/Components/InputError';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import TurnstileWidget from '@/Components/TurnstileWidget';
import useInView from '@/Hooks/useInView';

import useClientValidation from '@/Hooks/useClientValidation';
import { z } from 'zod';

export default function TrackingPortal() {
  const { turnstile } = usePage().props;
  const [turnstileToken, setTurnstileToken] = useState('');
  const [formRef, formVisible] = useInView();
  const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
    tracker_number: '',
    email: '',
    cf_turnstile_response: '',
  });
  const initialRef = useRef({ tracker_number: '', email: '' });
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
    bypassNext();
    data.cf_turnstile_response = turnstileToken;
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
        <section className="relative flex min-h-[400px] w-full items-center justify-center overflow-hidden py-12 md:py-20 bg-primary">
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

        <section className="relative -mt-12 md:-mt-16 pb-24 px-4">
          <div className="mx-auto max-w-3xl">
            <div ref={formRef} className={`bg-white p-5 md:p-8 shadow-lg md:shadow-2xl border border-slate-200 rounded-xl owb-reveal ${formVisible ? 'is-visible' : ''}`}>
              <div className="mb-6 flex items-center gap-3 border-b border-slate-200 pb-4">
                <span className="material-symbols-outlined text-blue-900 text-2xl">confirmation_number</span>
                <h2 className="font-headline text-lg font-bold text-slate-900">Tracking ID Details</h2>
              </div>

              <form
                className="flex flex-col gap-4"
                onSubmit={(e) => { e.preventDefault(); handleTrackSubmit(); }}
              >
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-[20px] material-symbols-outlined">confirmation_number</span>
                  <input
                    type="text"
                    value={data.tracker_number}
                    onChange={(e) => handleTrackerChange(e.target.value)}
                    placeholder="Enter Tracking Number"
                    className="w-full border border-slate-200 bg-white px-4 py-3 md:py-5 pl-12 text-sm md:text-base text-slate-900 placeholder:text-slate-400 focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900 md:px-6 md:pl-14 rounded-lg"
                    aria-label="Tracker Number"
                    required
                  />
                  <InputError message={errors.tracker_number} className="mt-1" />
                </div>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-[20px] material-symbols-outlined">alternate_email</span>
                  <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="Enter your email address"
                    className="w-full border border-slate-200 bg-white px-4 py-3 md:py-5 pl-12 text-sm md:text-base text-slate-900 placeholder:text-slate-400 focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900 md:px-6 md:pl-14 rounded-lg"
                    aria-label="Email Address"
                    required
                  />
                  <InputError message={errors.email} className="mt-1" />
                </div>
                {turnstile?.enabled && (
                  <div className="text-center">
                    <TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} />
                    <InputError message={errors.captcha} className="mt-1" />
                  </div>
                )}
                <button
                  type="submit"
                  disabled={processing}
                  className="w-full bg-blue-900 text-white rounded-lg px-8 py-4 text-sm font-bold shadow-md hover:bg-blue-800 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2"
                >
                  <span className="material-symbols-outlined text-[18px]">search</span>
                  {processing ? 'Sending OTP...' : 'Go to Tracking'}
                </button>
              </form>

              <div className="mt-8 bg-blue-50/50 border border-blue-100 rounded-lg p-4">
                <div className="flex gap-3">
                  <span className="material-symbols-outlined text-blue-900 text-[20px] shrink-0">info</span>
                  <div className="text-sm text-slate-600 leading-relaxed">
                    <p className="font-bold text-slate-900 mb-1">Where can I find my Tracking ID?</p>
                    <p>Tracking IDs (e.g., OWBAP-A7K2M9Q) are typically found on your acknowledgment receipt or sent via SMS/Email after your initial case intake.</p>
                  </div>
                </div>
              </div>
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
