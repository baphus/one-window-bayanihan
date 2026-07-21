import AppSidebar from '@/Components/AppSidebar';
import { Head, usePage } from '@inertiajs/react';
import ChatBot from '@/Components/ChatBot';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import { useRef, useEffect } from 'react';
import { router } from '@inertiajs/react';
import WelcomeModal from '@/Components/WelcomeModal';
import TourManager from '@/Onboarding/TourManager';
import { useOnboarding } from '@/Onboarding/OnboardingProvider';
import { skipOnboarding } from '@/Onboarding/api';
import { getTourConfig } from '@/Onboarding/index';
import { parseStepKey } from '@/Onboarding/types';
import useChecklistVisitTracking from '@/Onboarding/useChecklistVisitTracking';
import useAutoPageGuide from '@/Onboarding/useAutoPageGuide';
import { route } from 'ziggy-js';

// Module-level variables persist across AppLayout instances (which remount on every navigation)
let savedScrollTop = 0;
let navIdCounter = 0;

export default function AppLayout({ title, children }) {
  const mainRef = useRef(null);
  const { auth, onboarding } = usePage().props;
  const { phase, startTour, endTour, dismissRemindLater } = useOnboarding();

  // Saved welcome-tour position ("<pageIndex>:<stepIndex>"), validated
  // against the role's config bounds. Corrupt or legacy keys resolve to
  // null and the modal falls back to a fresh Start Tour.
  const tourConfig = getTourConfig(auth.user?.role);
  const savedPosition = parseStepKey(onboarding?.step, tourConfig);

  useChecklistVisitTracking();
  useAutoPageGuide();

  // Start (or resume) the welcome tour. The tour only renders an overlay on
  // pages in its config, so launching from anywhere else must navigate to
  // the target page first — otherwise nothing visible happens and the app
  // is stuck in a silent 'touring' state.
  const launchTour = (at) => {
    if (!tourConfig) return;
    const targetRoute = tourConfig.pages[at?.page ?? 0].route;
    let targetPath;
    try {
      targetPath = new URL(route(targetRoute), window.location.origin).pathname;
    } catch {
      return;
    }
    if (window.location.pathname === targetPath) {
      startTour(tourConfig, at ?? undefined);
    } else {
      router.visit(targetPath, {
        onSuccess: () => startTour(tourConfig, at ?? undefined),
      });
    }
  };

  useEffect(() => {
    const onBefore = () => {
      navIdCounter += 1;
      if (mainRef.current) {
        savedScrollTop = mainRef.current.scrollTop;
      }
    };

    const onFinish = (event) => {
      const currentNavId = navIdCounter;
      if (event.detail.visit.completed && mainRef.current && savedScrollTop > 0) {
        requestAnimationFrame(() => {
          if (navIdCounter === currentNavId && mainRef.current) {
            mainRef.current.scrollTop = savedScrollTop;
          }
        });
      }
    };

    const removeBefore = router.on('before', onBefore);
    const removeFinish = router.on('finish', onFinish);

    return () => {
      if (typeof removeBefore === 'function') removeBefore();
      if (typeof removeFinish === 'function') removeFinish();
    };
  }, []);

  return (
    <div className="flex h-screen overflow-hidden bg-gray-100">
      <Head title={title} />
      <FlashMessageWatcher />
      <AppSidebar />
      <div className="flex-1 flex flex-col min-w-0">
        {/* Scrollable main content */}
        <main ref={mainRef} scroll-region="" className="flex-1 overflow-y-auto p-8 owb-scroll owb-scroll-wide">
          {children}
        </main>
      </div>
      {/* Hide ChatBot only while the welcome modal is up; during tours it
          stays mounted so tour steps can highlight its launcher (the tour
          overlay renders above it). */}
      {phase !== 'welcome' && <ChatBot />}

      {/* Onboarding UI — use show prop so Headless UI Dialog properly cleans up on close */}
      <WelcomeModal
        show={phase === 'welcome'}
        onStartTour={() => launchTour(null)}
        onSkipTour={() => {
          skipOnboarding().then(() => endTour()).catch(() => endTour());
        }}
        onRemindLater={dismissRemindLater}
        {...(savedPosition !== null
          ? { canResume: true, onResumeTour: () => launchTour(savedPosition) }
          : { canResume: false })}
      />
      <TourManager />
    </div>
  );
}
