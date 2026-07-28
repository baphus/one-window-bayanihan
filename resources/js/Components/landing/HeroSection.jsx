import { HERO_IMAGE } from './appData';
import LogoMarquee from './LogoMarquee';
import AppButton from './AppButton';

export default function HeroSection({ title, description, onTrackAction, agencies }) {
  return (
    <section className="relative flex min-h-screen w-full flex-col overflow-x-hidden">
      {/* Background */}
      <div className="absolute inset-0 z-0">
        <img
          src={HERO_IMAGE}
          alt="One Window Bayanihan Assistance Program in action"
          className="h-full w-full object-cover object-center"
        />
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900/85 via-slate-900/70 to-primary-container/95" />
      </div>

      {/* Hero content — single centered column */}
      <div className="relative z-10 flex flex-1 items-center px-4 pt-36 pb-20 md:px-8 md:pt-40">
        <div className="mx-auto flex w-full max-w-5xl flex-col items-center gap-10">

          {/* Emblem row — DMW left, Bagong Pilipinas right */}
          <div
            className="animate-fade-in-up flex w-full items-center justify-between px-4 sm:px-8"
            style={{ animationDelay: '0.1s', animationFillMode: 'both' }}
          >
            {/* DMW Seal */}
            <div className="relative flex-shrink-0">
              <div className="absolute inset-0 -m-8 rounded-full bg-sky-400/10 blur-3xl" />
              <img
                src="https://res.cloudinary.com/dzjshue6h/image/upload/v1783960989/agency-logos/agency-dmw.png"
                alt="Department of Migrant Workers Official Seal"
                className="relative h-[36px] w-[36px] rounded-full object-contain drop-shadow-2xl sm:h-[48px] sm:w-[48px] lg:h-[64px] lg:w-[64px]"
                referrerPolicy="no-referrer"
              />
            </div>

            {/* Bagong Pilipinas Logo */}
            <div className="relative flex-shrink-0">
              <div className="absolute inset-0 -m-8 rounded-full bg-sky-400/10 blur-3xl" />
              <img
                src="/images/landing/logo-bagong-pilipinas.webp"
                alt="Bagong Pilipinas Official Logo"
                className="relative h-[36px] w-[36px] rounded-full object-contain drop-shadow-2xl sm:h-[48px] sm:w-[48px] lg:h-[64px] lg:w-[64px]"
              />
            </div>
          </div>

          {/* Headline — centered */}
          <h1
            className="animate-fade-in-up text-center text-3xl font-semibold leading-[1.1] tracking-tight text-white drop-shadow-xl sm:text-4xl lg:text-5xl"
            style={{ animationDelay: '0.2s', animationFillMode: 'both', fontFamily: "'Outfit', sans-serif" }}
          >
            Connecting Government
            <br />
            Services Through
            <br />
            <span className="italic text-blue-200">One Window</span>
          </h1>

          {/* Description — centered */}
          <p
            className="animate-fade-in-up mx-auto max-w-lg text-center text-sm leading-relaxed text-slate-300 drop-shadow md:text-base"
            style={{ animationDelay: '0.35s', animationFillMode: 'both' }}
          >
            {description}
          </p>

          {/* CTA buttons — centered */}
          <div
            className="animate-fade-in-up flex flex-wrap items-center justify-center gap-4"
            style={{ animationDelay: '0.5s', animationFillMode: 'both' }}
          >
            <AppButton
              as="link"
              href={route('intake.index')}
              variant="mint"
              icon="edit_note"
              className="px-6 py-3 text-sm shadow-xl"
            >
              File a Case
            </AppButton>
            <button
              onClick={onTrackAction}
              className="inline-flex items-center justify-center gap-2 border border-white/40 bg-white/10 px-6 py-3 text-sm font-bold text-white backdrop-blur-md transition-all hover:-translate-y-0.5 hover:bg-white/20 active:scale-95"
            >
              <span className="material-symbols-outlined text-[22px]">travel_explore</span>
              Track Your Case
            </button>
          </div>
        </div>
      </div>

      {/* Partner marquee strip */}
      <div className="relative z-10 -mt-12 bg-gradient-to-b from-transparent via-primary-container/60 to-primary-container pt-2 pb-6">
        <h3
          className="animate-fade-in-up mb-4 px-8 text-center font-headline text-[11px] font-bold uppercase tracking-[0.25em] text-white/40"
          style={{ animationDelay: '0.6s', animationFillMode: 'both' }}
        >
          Our Partner Agencies
        </h3>
        <div
          className="animate-fade-in-up marquee-fade"
          style={{ animationDelay: '0.7s', animationFillMode: 'both' }}
        >
          <LogoMarquee agencies={agencies} />
        </div>
      </div>

      <style>{`
        /* --- Marquee --- */
        @keyframes marquee {
          0% { transform: translateX(0); }
          100% { transform: translateX(-50%); }
        }
        .animate-marquee {
          animation: marquee 15s linear infinite;
          will-change: transform;
        }
        @media (min-width: 640px) {
          .animate-marquee { animation-duration: 25s; }
        }
        @media (min-width: 1024px) {
          .animate-marquee { animation-duration: 30s; }
        }
        .group:hover .animate-marquee {
          animation-play-state: paused;
        }

        /* --- Entrance animation --- */
        @keyframes fade-in-up {
          0% { opacity: 0; transform: translateY(24px); }
          100% { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
          animation: fade-in-up 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        /* --- Marquee fade edges --- */
        .marquee-fade {
          mask-image: linear-gradient(to right, transparent 0%, black 5%, black 95%, transparent 100%);
          -webkit-mask-image: linear-gradient(to right, transparent 0%, black 5%, black 95%, transparent 100%);
        }
        .marquee-fade img {
          filter: brightness(1.2);
          opacity: 0.75;
          transition: opacity 0.4s ease;
        }
        .marquee-fade img:hover {
          opacity: 1;
        }
      `}</style>
    </section>
  );
}
