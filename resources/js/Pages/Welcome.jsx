import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import HeroSection from '@/Components/landing/HeroSection';
import LogoMarquee from '@/Components/landing/LogoMarquee';
import FeaturesSection from '@/Components/landing/FeaturesSection';
import PartnersSection from '@/Components/landing/PartnersSection';
import FaqSection from '@/Components/landing/FaqSection';
import AboutSection from '@/Components/landing/AboutSection';
import TrackerSection from '@/Components/landing/TrackerSection';
import AppFooter from '@/Components/landing/AppFooter';

export default function Welcome({ agencies }) {
  return (
    <div className="bg-surface font-body text-on-surface">
      <Head title="Welcome" />

      <AppHeader onTrackCaseClick={() => router.get(route('track.index'))} />

      <main>
        <HeroSection
          title="Connecting Government Services Through One Window"
          description="A unified platform for inter-agency referrals, ensuring secure, transparent, and efficient assistance for migrant workers and their families."
          onTrackAction={() => router.get(route('track.index'))}
        />

        <section className="bg-surface py-12">
          <div className="container mx-auto px-8">
            <h3 className="mb-8 text-center font-headline text-sm font-bold uppercase tracking-widest text-on-surface-variant/70">
              Trusted by Partner Agencies & Stakeholders
            </h3>
            <LogoMarquee agencies={agencies} />
          </div>
        </section>

        <FeaturesSection />
        <PartnersSection agencies={agencies} />
        <FaqSection />
        <AboutSection />
        <TrackerSection />
      </main>

      <AppFooter />

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
        }
        .animate-marquee2 {
          animation: marquee 60s linear infinite;
        }
      `}</style>
    </div>
  );
}
