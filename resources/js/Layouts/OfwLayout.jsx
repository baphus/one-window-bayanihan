import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FlashMessageWatcher } from '@/Components/ToastProvider';

export default function OfwLayout({ children, title }) {
    const { auth } = usePage().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    function handleLogout() {
        router.post(route('logout'));
    }

    return (
        <div className="min-h-screen bg-gray-50">
            <FlashMessageWatcher />
            {title && <Head title={title} />}

            {/* Fixed top navigation */}
            <nav className="fixed top-0 left-0 right-0 z-50 border-b border-gray-200 bg-white shadow-sm">
                <div className="mx-auto max-w-5xl px-4 sm:px-6">
                    <div className="flex h-16 items-center justify-between">
                        {/* Logo / App name */}
                        <Link href="/" className="flex items-center gap-2">
                            <img
                                src="/logo.png"
                                alt="One Window Bayanihan"
                                className="h-8 w-8"
                            />
                            <span className="hidden text-sm font-bold text-gray-800 sm:block">
                                Bayanihan
                            </span>
                        </Link>

                        {/* Desktop nav links */}
                        <div className="hidden items-center gap-1 sm:flex">
                            <Link
                                href={route('ofw.dashboard')}
                                className={`inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    route().current('ofw.dashboard')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[18px]">folder_open</span>
                                My Cases
                            </Link>
                            <Link
                                href={route('ofw.notifications')}
                                className={`inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    route().current('ofw.notifications')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[18px]">notifications</span>
                                Notifications
                            </Link>
                            <Link
                                href={route('ofw.profile.edit')}
                                className={`inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    route().current('ofw.profile.*')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[18px]">person</span>
                                Profile
                            </Link>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="ml-2 inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
                            >
                                <span className="material-symbols-outlined text-[18px]">logout</span>
                                Logout
                            </button>
                        </div>

                        {/* Mobile menu button */}
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen((prev) => !prev)}
                            className="inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 sm:hidden"
                            aria-label="Toggle menu"
                        >
                            <span className="material-symbols-outlined text-[24px]">
                                {mobileMenuOpen ? 'close' : 'menu'}
                            </span>
                        </button>
                    </div>
                </div>

                {/* Mobile navigation */}
                {mobileMenuOpen && (
                    <div className="border-t border-gray-200 bg-white px-4 pb-4 pt-2 sm:hidden">
                        <div className="space-y-1">
                            <Link
                                href={route('ofw.dashboard')}
                                className={`flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium ${
                                    route().current('ofw.dashboard')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[18px]">folder_open</span>
                                My Cases
                            </Link>
                            <Link
                                href={route('ofw.notifications')}
                                className={`flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium ${
                                    route().current('ofw.notifications')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[18px]">notifications</span>
                                Notifications
                            </Link>
                            <Link
                                href={route('ofw.profile.edit')}
                                className={`flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium ${
                                    route().current('ofw.profile.*')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                <span className="material-symbols-outlined text-[18px]">person</span>
                                Profile
                            </Link>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-100"
                            >
                                <span className="material-symbols-outlined text-[18px]">logout</span>
                                Logout
                            </button>
                        </div>
                    </div>
                )}
            </nav>

            {/* Main content area — offset for fixed nav */}
            <main className="mx-auto max-w-5xl px-4 pt-20 pb-12 sm:px-6">
                {children}
            </main>
        </div>
    );
}
