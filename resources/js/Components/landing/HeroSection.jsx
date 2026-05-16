import { HERO_IMAGE } from './appData';
import LogoMarquee from './LogoMarquee';

export default function HeroSection({ title, description, onTrackAction, agencies }) {
  return (
    <section className="relative flex min-h-screen w-full flex-col overflow-x-hidden">
      <div className="absolute inset-0 z-0">
        <img
          src={HERO_IMAGE}
          alt="Bayanihan One Window System in action"
          className="h-full w-full object-cover object-center"
        />
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/60 to-[#003a63]/90"></div>
      </div>

      <div className="relative z-10 flex flex-1 items-center justify-center px-4 pt-24 md:px-8 md:pt-32">
        <div className="mx-auto w-full max-w-7xl">
          <div className="mx-auto max-w-4xl text-center">
            <h1 className="mb-6 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white drop-shadow-xl sm:text-5xl md:text-6xl lg:text-7xl">
              {title}
            </h1>
            <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-slate-200 drop-shadow-md md:text-xl">
              {description}
            </p>

            <div className="flex flex-wrap items-center justify-center gap-4">
              <button
                onClick={onTrackAction}
                className="inline-flex items-center justify-center gap-2 bg-white px-8 py-4 text-base font-bold text-[#005288] shadow-xl transition-all hover:bg-slate-100 active:scale-95"
              >
                <span className="material-symbols-outlined text-[22px]">travel_explore</span>
                Track Your Case
              </button>
              <a href="#features" className="inline-flex items-center justify-center gap-2 border border-white/40 bg-white/10 px-8 py-4 text-base font-bold text-white backdrop-blur-md transition-all hover:-translate-y-0.5 hover:bg-white/20 active:scale-95">
                Learn More
              </a>
            </div>
          </div>
        </div>
      </div>

      <div className="relative z-10 bg-gradient-to-b from-transparent via-[#003a63]/60 to-[#003a63] pt-20 pb-16">
        <h3
          className="animate-fade-in-up mb-5 px-8 text-center font-headline text-sm font-bold uppercase tracking-widest text-white/50"
          style={{ animationDelay: '0.2s', animationFillMode: 'both' }}
        >
          Trusted by Partner Agencies & Stakeholders
        </h3>
        <div
          className="animate-fade-in-up marquee-fade"
          style={{ animationDelay: '0.5s', animationFillMode: 'both' }}
        >
          <LogoMarquee agencies={agencies} />
        </div>
      </div>

      <style>{`
        @keyframes marquee {
          0% { transform: translateX(0); }
          100% { transform: translateX(-50%); }
        }
        @keyframes marquee2 {
          0% { transform: translateX(50%); }
          100% { transform: translateX(0%); }
        }
        .animate-marquee {
          animation: marquee 60s linear infinite;
          will-change: transform;
        }
        .animate-marquee2 {
          animation: marquee2 60s linear infinite;
          will-change: transform;
        }
        .group:hover .animate-marquee,
        .group:hover .animate-marquee2 {
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
