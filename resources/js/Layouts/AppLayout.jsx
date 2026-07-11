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

// Module-level variables persist across AppLayout instances (which remount on every navigation)
let savedScrollTop = 0;
let navIdCounter = 0;

export default function AppLayout({ title, children }) {
  const mainRef = useRef(null);
  const { auth } = usePage().props;
  const { isOpen, phase, startTour, endTour, dismissRemindLater } = useOnboarding();

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
        <main ref={mainRef} scroll-region="" className="flex-1 overflow-y-auto p-8">
          {children}
        </main>
      </div>
      {/* Hide ChatBot during welcome/tour to avoid z-index conflicts */}
      {!isOpen && <ChatBot />}

      {/* Onboarding UI — use show prop so Headless UI Dialog properly cleans up on close */}
      <WelcomeModal
        show={phase === 'welcome'}
        onStartTour={() => {
          const role = auth.user?.role;
          const config = getTourConfig(role);
          if (config) startTour(config);
        }}
        onSkipTour={() => {
          skipOnboarding().then(() => endTour());
        }}
        onRemindLater={dismissRemindLater}
      />
      <TourManager />
    </div>
  );
}
