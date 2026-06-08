import AppSidebar from '@/Components/AppSidebar';
import { Head } from '@inertiajs/react';
import ChatBot from '@/Components/ChatBot';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import NotificationPanel from '@/Components/ui/NotificationPanel';

export default function AppLayout({ title, children }) {
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
        <main className="flex-1 overflow-y-auto p-8">
          {children}
        </main>
      </div>
      <ChatBot />
    </div>
  );
}
