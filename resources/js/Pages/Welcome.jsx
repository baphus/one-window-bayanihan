import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AppHeader from '@/Components/landing/AppHeader';
import HeroSection from '@/Components/landing/HeroSection';
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
          agencies={agencies}
        />

        <div className="leading-none -mt-px">
          <svg viewBox="0 0 1440 80" preserveAspectRatio="none" className="block w-full h-24 md:h-32">
            <path fill="#ebeef4" d="M0,45 C160,100 320,-10 480,45 C640,100 800,-10 960,45 C1120,100 1280,-10 1440,45 L1440,80 L0,80 Z" />
          </svg>
        </div>

        <FeaturesSection />
        <PartnersSection agencies={agencies} />
        <FaqSection />
        <AboutSection />
        <TrackerSection />
      </main>

      <AppFooter />

    </div>
  );
}
