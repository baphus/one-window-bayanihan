import { HERO_IMAGE } from './appData';
import AppButton from './AppButton';

export default function HeroSection({ title, description, onTrackAction }) {
  return (
    <section className="relative flex min-h-[600px] w-full items-center justify-center overflow-hidden py-24 md:min-h-[85vh] md:py-32">
      <div className="absolute inset-0 z-0">
        <img
          src={HERO_IMAGE}
          alt="Bayanihan One Window System in action"
          className="h-full w-full object-cover object-center"
        />
        <div className="absolute inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/60 to-[#003a63]/90"></div>
      </div>

      <div className="relative z-10 mx-auto w-full max-w-7xl px-4 md:px-8">
        <div className="mx-auto max-w-4xl text-center">
          <h1 className="mb-6 font-headline text-4xl font-extrabold leading-tight tracking-tight text-white drop-shadow-xl sm:text-5xl md:text-6xl lg:text-7xl">
            {title}
          </h1>
          <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-slate-200 drop-shadow-md md:text-xl">
            {description}
          </p>

          <div className="flex flex-wrap items-center justify-center gap-4">
            <AppButton
              variant="primary"
              icon="travel_explore"
              className="bg-white text-[#005288] px-8 py-4 text-base font-bold shadow-xl hover:bg-slate-100"
              onClick={onTrackAction}
            >
              Track Your Case
            </AppButton>
            <a href="#features" className="inline-flex items-center justify-center gap-2 border border-white/40 bg-white/10 px-8 py-4 text-base font-bold text-white backdrop-blur-md transition-all hover:-translate-y-0.5 hover:bg-white/20 active:scale-95">
              Learn More
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}
