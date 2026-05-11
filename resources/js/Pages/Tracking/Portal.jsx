import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import AppFooter from '@/Components/landing/AppFooter';
import TrackerInput from '@/Components/TrackerInput';
import FaqSection from '@/Components/landing/FaqSection';
import ChatBot from '@/Components/ChatBot';

export default function TrackingPortal() {
  const { data, setData, post, processing, errors } = useForm({
    tracker_number: '',
  });
  const [inputError, setInputError] = useState('');

  const handleTrackSubmit = () => {
    const normalized = data.tracker_number.trim().toUpperCase();

    if (!normalized) {
      setInputError('Please enter your Tracking ID.');
      return;
    }

    if (!/^OW-[A-Z0-9]{7}$/.test(normalized)) {
      setInputError('Tracking ID must be in the format OW-XXXXXXX.');
      return;
    }

    setInputError('');
    setData('tracker_number', normalized);
    post(route('track.send-otp'));
  };

  const handleTrackerChange = (value) => {
    setData('tracker_number', value.toUpperCase());
    if (inputError) setInputError('');
  };

  const displayError = inputError || errors.tracker_number;

  return (
    <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
      <Head title="Track Your Case" />
      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main className="flex-1">
        <section className="relative flex min-h-[400px] w-full items-center justify-center overflow-hidden py-20 bg-primary">
          <div className="absolute inset-0 z-0">
            <div className="absolute inset-0 bg-gradient-to-br from-primary via-primary/90 to-primary-container/30"></div>
          </div>

          <div className="relative z-10 mx-auto w-full max-w-7xl px-4 text-center md:px-8">
            <div className="mx-auto max-w-3xl">
              <h1 className="mb-4 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
                Track Your Case
              </h1>
              <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-white/80">
                Enter your unique case tracking number below to view the
                real-time status and history of your OFW application or
                request safely across all partner agencies.
              </p>
            </div>
          </div>
        </section>

        <section className="relative -mt-16 pb-24 px-4">
          <div className="mx-auto max-w-3xl">
            <div className="bg-surface p-6 shadow-2xl border border-outline-variant/30">
              <div className="mb-6 flex items-center gap-3 border-b border-outline-variant pb-4">
                <span className="material-symbols-outlined text-primary text-2xl">confirmation_number</span>
                <h2 className="font-headline text-lg font-bold text-on-surface">Tracking ID Details</h2>
              </div>

              <TrackerInput
                trackerNumber={data.tracker_number}
                errorMessage={displayError}
                onTrackerChange={handleTrackerChange}
                onSubmit={handleTrackSubmit}
                isDisabled={processing}
              />

              <div className="mt-8 bg-surface-container-highest/30 p-4">
                <div className="flex gap-3">
                  <span className="material-symbols-outlined text-primary text-[20px]">info</span>
                  <div className="text-sm text-on-surface-variant leading-relaxed">
                    <p className="font-semibold text-primary mb-1">Where can I find my Tracking ID?</p>
                    <p>Tracking IDs (e.g., OW-A7K2M9Q) are typically found on your acknowledgment receipt or sent via SMS/Email after your initial case intake.</p>
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
    </div>
  );
}
