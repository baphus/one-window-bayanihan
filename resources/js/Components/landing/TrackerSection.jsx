import { router } from '@inertiajs/react';
import AppButton from './AppButton';

export default function TrackerSection() {
  return (
    <section id="tracker" className="bg-[#015289] px-8 py-20">
      <div className="mx-auto max-w-4xl text-center">
        <span className="material-symbols-outlined mb-4 block text-4xl text-white/50">radar</span>
        <h2 className="mb-4 font-headline text-2xl font-extrabold text-white md:text-3xl">
          Track Your Case
        </h2>
        <p className="mx-auto mb-8 max-w-xl text-sm leading-relaxed text-white/70 md:text-base">
          Have a tracking number? Check the real-time status of your referral and see which agency is handling your case.
        </p>
        <AppButton
          variant="outline"
          icon="search"
          className="border-2 border-white px-8 py-3 text-base text-white hover:bg-white/10"
          onClick={() => router.get(route('track.index'))}
        >
          Go to Tracking Page
        </AppButton>
      </div>
    </section>
  );
}
