import AppSidebar from '@/Components/AppSidebar';
import { Head } from '@inertiajs/react';
import ChatBot from '@/Components/ChatBot';

export default function AppLayout({ title, children }) {
  return (
    <div className="flex min-h-screen bg-gray-100">
      <Head title={title} />
      <AppSidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <main className="flex-1 p-8">
          {children}
        </main>
      </div>
      <ChatBot />
    </div>
  );
}
