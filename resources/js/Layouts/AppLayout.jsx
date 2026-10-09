import AppSidebar from '@/Components/AppSidebar';
import { Head } from '@inertiajs/react';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import { useRef, useEffect } from 'react';
import { router } from '@inertiajs/react';
import useChecklistVisitTracking from '@/Onboarding/useChecklistVisitTracking';

// Module-level variables persist across AppLayout instances (which remount on every navigation)
let savedScrollTop = 0;
let navIdCounter = 0;

export default function AppLayout({ title, children }) {
  const mainRef = useRef(null);

  useChecklistVisitTracking();

  // Lock body scroll — only the inner <main> should scroll.
  useEffect(() => {
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = ''; };
  }, []);

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
    <div className="flex h-screen overflow-hidden bg-surface">
      <Head title={title} />
      <FlashMessageWatcher />
      <AppSidebar />
      <div className="flex-1 flex flex-col min-w-0">
        {/* Scrollable main content */}
        <main ref={mainRef} scroll-region="" className="flex-1 overflow-y-auto p-8 owb-scroll owb-scroll-wide">
          {children}
        </main>
      </div>
    </div>
  );
}
