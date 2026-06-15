import AppSidebar from '@/Components/AppSidebar';
import { Head } from '@inertiajs/react';
import ChatBot from '@/Components/ChatBot';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import NotificationPanel from '@/Components/ui/NotificationPanel';
import { useRef, useEffect } from 'react';
import { router } from '@inertiajs/react';

// Module-level variables persist across AppLayout instances (which remount on every navigation)
let savedScrollTop = 0;
let navIdCounter = 0;

export default function AppLayout({ title, children }) {
  const mainRef = useRef(null);

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
        {/* Sticky header with NotificationPanel */}
        <header className="sticky top-0 z-30 flex items-center justify-between px-6 py-3 bg-white border-b border-slate-200">
          <div>
            {/* Subtle branding / breadcrumb placeholder */}
          </div>
          <NotificationPanel />
        </header>

        {/* Scrollable main content */}
        <main ref={mainRef} scroll-region="" className="flex-1 overflow-y-auto p-8">
          {children}
        </main>
      </div>
      <ChatBot />
    </div>
  );
}
