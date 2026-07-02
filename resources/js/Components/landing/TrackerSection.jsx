import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppButton from './AppButton';

export default function TrackerSection() {
  const [trackerNumber, setTrackerNumber] = useState('');
  const [error, setError] = useState('');

  const trimmed = trackerNumber.trim();

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!trimmed) {
      setError('Please enter a tracker number before continuing.');
      return;
    }
    setError('');
    router.get(route('track.index', { tracker_number: trimmed }));
  };

  return (
    <section id="tracker" className="relative mx-auto max-w-6xl px-4 py-24 md:px-8">
      <div className="relative flex min-h-[400px] flex-col bg-primary md:flex-row shadow-2xl">
        <div className="flex flex-1 flex-col justify-center p-12 md:p-16 lg:p-20">
          <h2 className="mb-4 font-headline text-4xl font-extrabold text-white md:text-[44px] md:leading-tight">
            Ready to check your<br />status?
          </h2>
          <p className="max-w-md text-lg text-sky-100/90 leading-relaxed">
            Enter your tracking number to get real-time updates on your referral.
          </p>
        </div>

        <div className="relative flex flex-1 flex-col justify-center bg-primary-container p-12 md:p-16 lg:p-20">
          <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
            <div className="relative">
              <input type="text" value={trackerNumber}
                onChange={(e) => { setTrackerNumber(e.target.value); if (error && e.target.value.trim()) setError(''); }}
                placeholder="Enter Tracking Number"
                className="w-full border border-outline bg-surface-container px-4 py-4 text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary md:px-6 md:py-5"
                aria-label="Tracker Number"
              />
            </div>
            {error && <p className="text-sm font-medium text-error">{error}</p>}
            <AppButton type="submit" disabled={!trimmed} variant="mint" icon="search" className="px-8 py-4 text-base md:py-5">
              Go to Tracking
            </AppButton>
          </form>
        </div>
      </div>
    </section>
  );
}
