import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import OfwNotificationBell from '@/Components/OfwNotificationBell';

export default function OfwLayout({ children, title }) {
    const { url, props } = usePage();
    const { auth } = props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        setMobileMenuOpen(false);
    }, [url]);

    function handleLogout() {
        router.post(route('logout'));
    }

    return (
        <div className="min-h-screen bg-slate-50">
            <FlashMessageWatcher />
            {title && <Head title={title} />}

            {/* Fixed top navigation — styled like the landing page / tracking portal header */}
            <nav className="fixed top-0 left-0 right-0 z-50 border-b border-outline-variant bg-white">
                <div className="mx-auto flex h-[76px] w-full max-w-5xl items-stretch justify-between px-4 sm:px-6">
                    {/* Logo / App name */}
                    <Link href="/" className="flex min-w-0 items-center gap-3 self-center">
                        <div className="flex h-[44px] w-[44px] items-center justify-center overflow-hidden bg-white">
                            <img
                                src="/logo.png"
                                alt="One Window Bayanihan Logo"
                                className="h-full w-full object-contain"
                            />
                        </div>
                        <div className="flex flex-col">
                            <span className="font-headline text-[18px] font-bold leading-tight tracking-tight text-blue-950">
                                One Window Bayanihan
                            </span>
                            <span className="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">
                                Assistance Program
                            </span>
                        </div>
                    </Link>

                    {/* Right-side actions */}
                    <div className="flex items-center gap-2 self-center">
                        {/* Notification bell — visible at all breakpoints */}
                        <OfwNotificationBell />

                        {/* Desktop actions */}
                        <div className="hidden items-center gap-3 md:flex">
                            <div className="flex items-center gap-4 border-l border-gray-200 pl-5">
                                <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-circle bg-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-3/5 w-3/5 text-white/50">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                    </svg>
                                </span>
                                <div className="hidden flex-col lg:flex">
                                    <span className="text-sm font-semibold text-slate-800">
                                        {auth?.user?.name}
                                    </span>
                                    <span className="text-xs text-slate-500">OFW</span>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    aria-label="Log out"
                                    title="Log out"
                                    className="flex-shrink-0 text-slate-700 transition hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                                        logout
                                    </span>
                                </button>
                            </div>
                        </div>

                        {/* Mobile menu button */}
                        <button
                            type="button"
                            className="inline-flex items-center justify-center self-center rounded-md border border-gray-200 p-2 text-slate-600 transition hover:bg-slate-50 hover:text-primary md:hidden"
                            aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={mobileMenuOpen}
                            aria-controls="ofw-mobile-navigation"
                            onClick={() => setMobileMenuOpen((prev) => !prev)}
                        >
                            {mobileMenuOpen ? (
                                <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M6 6l12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                    <path d="M18 6L6 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                </svg>
                            ) : (
                                <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 6h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                    <path d="M4 12h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                    <path d="M4 18h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                </svg>
                            )}
                        </button>
                    </div>
                </div>

                {/* Mobile navigation drawer */}
                {mobileMenuOpen && (
                    <>
                        <button
                            type="button"
                            aria-label="Close mobile menu"
                            className="fixed inset-0 z-40 bg-slate-950/40 md:hidden"
                            onClick={() => setMobileMenuOpen(false)}
                        />
                        <aside
                            id="ofw-mobile-navigation"
                            className="fixed right-0 top-0 z-50 flex h-full w-[min(19rem,85vw)] flex-col bg-white shadow-2xl ring-1 ring-black/5 md:hidden"
                            aria-label="Mobile navigation"
                        >
                            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-4">
                                <div className="min-w-0">
                                    <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">
                                        Menu
                                    </p>
                                    <p className="mt-1 truncate text-sm font-semibold text-slate-900">
                                        {auth?.user?.name}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    aria-label="Close menu"
                                    className="inline-flex h-10 w-10 items-center justify-center rounded-circle border border-gray-200 text-slate-600 transition hover:bg-slate-50 hover:text-primary"
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M6 6l12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                        <path d="M18 6L6 18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                    </svg>
                                </button>
                            </div>

                            <div className="flex-1 overflow-y-auto px-4 py-4">
                                <div className="grid gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setMobileMenuOpen(false);
                                            handleLogout();
                                        }}
                                        className="rounded-md border border-gray-200 px-4 py-3 text-left text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-red-600"
                                    >
                                        Logout
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </>
                )}
            </nav>

            {/* Main content area — offset for fixed nav */}
            <main className="mx-auto max-w-5xl px-4 pt-20 pb-12 sm:px-6">
                {children}
            </main>
        </div>
    );
}
