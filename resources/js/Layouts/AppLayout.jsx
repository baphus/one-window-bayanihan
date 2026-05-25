import AppSidebar from '@/Components/AppSidebar';
import { Head } from '@inertiajs/react';
import ChatBot from '@/Components/ChatBot';
import { FlashMessageWatcher } from '@/Components/ToastProvider';

export default function AppLayout({ title, children }) {
  return (
    <div className="flex h-screen overflow-hidden bg-gray-100">
      <Head title={title} />
      <FlashMessageWatcher />
      <AppSidebar />
      <div className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <main className="flex-1 p-8">
          {children}
        </main>
      </div>
      <ChatBot />
    </div>
  );
}
