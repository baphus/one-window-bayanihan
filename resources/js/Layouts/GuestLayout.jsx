import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import ChatBot from '@/Components/ChatBot';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import safeRoute from '@/utils/safeRoute';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-surface pt-6 sm:justify-center sm:pt-0">
            <ChatBot />
            <FlashMessageWatcher />
            <div>
                <Link href={safeRoute('home', undefined, '/')}>
                    <ApplicationLogo className="h-20 w-20 fill-current text-on-surface-variant" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
