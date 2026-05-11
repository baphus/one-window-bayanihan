import { Link } from '@inertiajs/react';

export default function TrackingNotFoundState({ description }) {
  return (
    <section className="border-l-[3px] border-red-600 bg-white px-[28px] py-[24px] shadow-[0_1px_3px_rgba(0,0,0,0.05)]">
      <h1 className="font-headline text-[26px] font-[900] uppercase tracking-[-0.02em] text-red-700">Tracking ID Not Found</h1>
      <p className="mt-[8px] text-[11px] font-[500] text-on-surface-variant">{description}</p>
      <Link
        href={route('track.index')}
        className="mt-4 inline-flex items-center gap-2 bg-primary px-4 py-2 text-[10px] font-bold uppercase tracking-[0.1em] text-white"
      >
        <span className="material-symbols-outlined text-[14px]">arrow_back</span>
        Back to Tracking Search
      </Link>
    </section>
  );
}
