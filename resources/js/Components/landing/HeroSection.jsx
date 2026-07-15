import { HERO_IMAGE } from './appData';
import LogoMarquee from './LogoMarquee';

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

      {/* Hero content — two column on md+ */}
      <div className="relative z-10 flex flex-1 items-center px-4 pt-36 pb-20 md:px-8 md:pt-40">
        <div className="mx-auto flex w-full max-w-7xl flex-col items-center gap-12 md:flex-row md:items-center md:gap-16">

          {/* Left column — text + CTAs (60%) */}
          <div className="flex w-full flex-col items-start md:w-[60%]">
            {/* Headline */}
            <h1
              className="animate-fade-in-up mb-6 text-4xl font-semibold leading-[1.1] tracking-tight text-white drop-shadow-xl sm:text-5xl lg:text-6xl"
              style={{ animationDelay: '0.2s', animationFillMode: 'both', fontFamily: "'Outfit', sans-serif" }}
            >
              Connecting Government
              <br />
              Services Through
              <br />
              <span className="italic text-blue-200">One Window</span>
            </h1>

            {/* Description */}
            <p
              className="animate-fade-in-up mb-10 max-w-lg text-base leading-relaxed text-slate-300 drop-shadow md:text-lg"
              style={{ animationDelay: '0.35s', animationFillMode: 'both' }}
            >
              {description}
            </p>

            {/* CTA buttons */}
            <div
              className="animate-fade-in-up flex flex-wrap items-center gap-4"
              style={{ animationDelay: '0.5s', animationFillMode: 'both' }}
            >
              <button
                onClick={onTrackAction}
                className="inline-flex items-center justify-center gap-2 bg-white px-8 py-4 text-base font-bold text-primary shadow-xl transition-all hover:bg-slate-100 active:scale-95"
              >
                <span className="material-symbols-outlined text-[22px]">travel_explore</span>
                Track Your Case
              </button>
              <a
                href="#features"
                className="inline-flex items-center justify-center gap-2 border border-white/40 bg-white/10 px-8 py-4 text-base font-bold text-white backdrop-blur-md transition-all hover:-translate-y-0.5 hover:bg-white/20 active:scale-95"
              >
                Learn More
              </a>
            </div>
          </div>

          {/* Right column — DMW seal (40%) */}
          <div
            className="animate-fade-in-up flex w-full items-center justify-end md:w-[40%]"
            style={{ animationDelay: '0.4s', animationFillMode: 'both' }}
          >
            <div className="relative">
              <div className="absolute inset-0 -m-8 rounded-full bg-sky-400/10 blur-3xl" />
              <img
                src="https://res.cloudinary.com/dzjshue6h/image/upload/v1783960989/agency-logos/agency-dmw.png"
                alt="Department of Migrant Workers Official Seal"
                className="relative h-[220px] w-[220px] object-contain drop-shadow-2xl sm:h-[280px] sm:w-[280px] lg:h-[340px] lg:w-[340px]"
                referrerPolicy="no-referrer"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Partner marquee strip */}
      <div className="relative z-10 -mt-12 bg-gradient-to-b from-transparent via-primary-container/60 to-primary-container pt-8 pb-14">
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
        @keyframes fade-in-up {
          0% { opacity: 0; transform: translateY(24px); }
          100% { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
          animation: fade-in-up 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
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
