import { Link } from '@inertiajs/react';
import { FOOTER_COVER } from './appData';
import useInView from '@/Hooks/useInView';

export default function AppFooter({ showImage = true }) {
  const [ref, isVisible] = useInView();

  return (
    <footer ref={ref} className={`w-full border-t-4 border-sky-900 bg-slate-100 owb-reveal ${isVisible ? 'is-visible' : ''}`}>
      {showImage && (
        <figure className="relative w-full overflow-hidden border-b border-slate-200/80">
          <img src={FOOTER_COVER} alt="OWBAP cover photo"
            className="h-[320px] w-full object-cover object-center md:h-[460px]" loading="lazy" referrerPolicy="no-referrer"
          />
          <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-900/35 via-transparent to-transparent" />
        </figure>
      )}

      <div className="flex w-full flex-col items-center justify-between gap-8 px-12 py-12 md:flex-row">
        <div className="flex max-w-md flex-col gap-4">
          <span className="font-headline text-lg font-bold text-sky-900">One Window Bayanihan Assistance Program</span>
          <p className="text-[12px] leading-relaxed text-slate-500">
            A centralized digital infrastructure for the Bureau of Migrant Workers, enhancing the Philippine government&apos;s commitment to the welfare of overseas workers.
          </p>
          <p className="mt-2 text-[10px] font-medium tracking-tight text-slate-400">
            In compliance with the Data Privacy Act of 2012 (RA 10173).
          </p>
        </div>

        <div className="flex flex-col gap-6 md:items-end">
          <div className="flex flex-wrap justify-center gap-6 md:justify-end">
            <Link href={route('helpdesk.index')} className="text-[10px] font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-sky-700">Help Center</Link>
            <Link href={route('partners')} className="text-[10px] font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-sky-700">Partner Agencies</Link>
            {showImage && <Link href={route('contact')} className="text-[10px] font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-sky-700">Contact Support</Link>}
            <Link href={route('privacy')} className="text-[10px] font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-sky-700">Privacy Policy</Link>
            <Link href={route('terms')} className="text-[10px] font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-sky-700">Terms of Service</Link>
            <Link href={route('track.index')} className="text-[10px] font-bold uppercase tracking-wider text-slate-500 transition-colors hover:text-sky-700">Track Your Case</Link>
          </div>
          <div className="text-center text-[9px] uppercase tracking-widest text-slate-500 md:text-right">
            &copy; 2026 One Window Bayanihan Assistance Program. All Rights Reserved. Bureau of Migrant Workers.
          </div>
        </div>
      </div>
    </footer>
  );
}
