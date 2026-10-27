import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FlashMessageWatcher } from '@/Components/ToastProvider';
import OfwNotificationBell from '@/Components/OfwNotificationBell';
import safeRoute from '@/utils/safeRoute';
import { clearAllDraftBackups } from '@/Hooks/useLocalStorageDraft';

/**
 * OfwPortalLayout — the citizen portal shell.
 *
 * A left sidebar (program identity, portal nav, privacy note) beside a
 * topbar that carries the assigned office and the account menu. On narrow
 * screens the sidebar collapses into a slide-over drawer and the topbar
 * keeps the program mark, the office, and the account controls.
 */

const NAV_ITEMS = [
    { key: 'my-cases', label: 'My Cases', icon: 'folder_open', href: '/my-cases', route: 'ofw.dashboard' },
    { key: 'notifications', label: 'Notifications', icon: 'notifications', href: '/my-cases/notifications', route: 'ofw.notifications', badge: true },
    { key: 'profile', label: 'Profile', icon: 'person', href: '/my-cases/profile', route: 'ofw.profile.edit' },
    { key: 'help', label: 'Help Center', icon: 'help', href: '/help', route: 'helpdesk.index' },
];

function ProgramMark() {
    return (
        <div className="flex items-center gap-2.5">
            <img
                src="/logo.png"
                alt="One Window Bayanihan logo"
                className="h-9 w-9 shrink-0 object-contain"
            />
            <span className="min-w-0 leading-tight">
                <span className="block truncate font-headline text-[13px] font-extrabold tracking-tight text-blue-950">
                    One Window Bayanihan
                </span>
                <span className="block text-[8px] font-bold uppercase tracking-[0.14em] text-slate-500">
                    Assistance Program
                </span>
            </span>
        </div>
    );
}

function SidebarContent({ currentPath, unreadCount, onNavigate }) {
    return (
        <div className="flex h-full flex-col">
            <div className="px-4 pb-6 pt-5">
                <ProgramMark />
            </div>

            <p className="px-4 pb-2 text-[9px] font-bold uppercase tracking-[0.14em] text-slate-400">
                Citizen Portal
            </p>

            <nav aria-label="Citizen portal" className="grid gap-0.5 px-2">
                {NAV_ITEMS.map((item) => {
                    const href = item.route ? safeRoute(item.route, undefined, item.href) : item.href;
                    // Exact match for the dashboard; prefix match for the rest,
                    // so /my-cases/profile lights Profile and not My Cases.
                    const active = item.key === 'my-cases'
                        ? currentPath === item.href
                        : currentPath === item.href || currentPath.startsWith(`${item.href}/`);

                    return (
                        <Link
                            key={item.key}
                            href={href}
                            onClick={onNavigate}
                            aria-current={active ? 'page' : undefined}
                            className={`flex items-center gap-2.5 rounded-r-md py-2.5 pl-3 pr-2 text-[13px] transition-colors ${
                                active
                                    ? 'border-l-[3px] border-primary bg-surface-container-low font-bold text-primary'
                                    : 'border-l-[3px] border-transparent font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                        >
                            <span
                                aria-hidden="true"
                                className={`material-symbols-outlined text-[18px] leading-none ${active ? 'text-primary' : 'text-slate-400'}`}
                            >
                                {item.icon}
                            </span>
                            <span className="min-w-0 flex-1 truncate">{item.label}</span>
                            {item.badge && Number(unreadCount) > 0 ? (
                                <span className="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-primary px-1.5 text-[10px] font-bold text-white">
                                    {unreadCount}
                                </span>
                            ) : null}
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-auto border-t border-slate-200 px-4 py-5">
                <span
                    aria-hidden="true"
                    className="material-symbols-outlined text-[18px] leading-none text-slate-400"
                >
                    shield
                </span>
                <p className="mt-2 text-[11px] leading-relaxed text-slate-500">
                    <span className="block font-semibold text-slate-600">Your cases. Your information.</span>
                    Only records linked to your account are shown.
                </p>
            </div>
        </div>
    );
}

export default function OfwPortalLayout({ children, title }) {
    const { url, props } = usePage();
    const { auth } = props;
    const unreadCount = props.notifications?.unread_count ?? 0;
    const [drawerOpen, setDrawerOpen] = useState(false);

    /**
     * The account name for the header.
     *
     * The shared auth payload only carries `users.name` (a single string) and
     * no suffix, so a citizen whose profile holds the structured parts is
     * built from those as First · Middle · Last · Suffix. Everything else
     * falls back to the auth name.
     */
    const clientName = (() => {
        const client = props?.client;
        if (!client) return '';

        const parts = [client.first_name, client.middle_name, client.last_name, client.suffix]
            .map((part) => (part ?? '').toString().trim())
            .filter(Boolean);

        return parts.join(' ');
    })();

    const accountName = clientName || auth?.user?.name || '';

    useEffect(() => {
        setDrawerOpen(false);
    }, [url]);

    function handleLogout() {
        clearAllDraftBackups();
        router.post(route('logout'));
    }

    return (
        <div className="min-h-screen bg-slate-50">
            <FlashMessageWatcher />
            {title && <Head title={title} />}

            <div className="flex min-h-screen">
                {/* Desktop sidebar */}
                <aside className="hidden w-[216px] shrink-0 border-r border-slate-200 bg-white lg:block">
                    <div className="sticky top-0 h-screen overflow-y-auto">
                        <SidebarContent currentPath={url.split('?')[0]} unreadCount={unreadCount} />
                    </div>
                </aside>

                {/* Main column */}
                <div className="flex min-w-0 flex-1 flex-col">
                    {/* Topbar */}
                    <header className="sticky top-0 z-40 flex h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setDrawerOpen(true)}
                                aria-label="Open menu"
                                aria-expanded={drawerOpen}
                                className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:bg-slate-50 hover:text-primary lg:hidden"
                            >
                                <span className="material-symbols-outlined text-[20px]" aria-hidden="true">menu</span>
                            </button>

                            <div className="lg:hidden">
                                <ProgramMark />
                            </div>

                            <p className="hidden min-w-0 items-center gap-2 text-[13px] font-bold text-slate-700 sm:flex">
                                <span
                                    aria-hidden="true"
                                    className="material-symbols-outlined text-[18px] leading-none text-slate-400"
                                >
                                    account_balance
                                </span>
                                <span className="truncate">DMW Region VII</span>
                            </p>
                        </div>

                        <div className="flex shrink-0 items-center gap-3">
                            <OfwNotificationBell />

                            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary text-[12px] font-bold text-white">
                                {accountName ? accountName.trim().charAt(0).toUpperCase() : 'O'}
                            </span>

                            <div className="hidden min-w-0 flex-col sm:flex">
                                <span className="truncate text-[13px] font-semibold text-slate-700">
                                    {accountName}
                                </span>
                            </div>

                            <button
                                type="button"
                                onClick={handleLogout}
                                aria-label="Log out"
                                title="Log out"
                                className="shrink-0 text-slate-500 transition hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            >
                                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                                    logout
                                </span>
                            </button>
                        </div>
                    </header>

                    {/* Content */}
                    <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        <div className="mx-auto w-full max-w-6xl">{children}</div>
                    </main>
                </div>
            </div>

            {/* Mobile drawer */}
            {drawerOpen ? (
                <>
                    <button
                        type="button"
                        aria-label="Close menu"
                        className="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
                        onClick={() => setDrawerOpen(false)}
                    />
                    <aside
                        aria-label="Citizen portal"
                        className="fixed left-0 top-0 z-50 flex h-full w-[min(17rem,80vw)] flex-col bg-white shadow-2xl ring-1 ring-black/5 lg:hidden"
                    >
                        <div className="flex items-center justify-end border-b border-slate-100 px-3 py-2">
                            <button
                                type="button"
                                onClick={() => setDrawerOpen(false)}
                                aria-label="Close menu"
                                className="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-50 hover:text-primary"
                            >
                                <span className="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                            </button>
                        </div>
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            <SidebarContent
                                currentPath={url.split('?')[0]}
                                unreadCount={unreadCount}
                                onNavigate={() => setDrawerOpen(false)}
                            />
                        </div>
                    </aside>
                </>
            ) : null}
        </div>
    );
}
