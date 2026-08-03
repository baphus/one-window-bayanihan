import { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

const NOTIFICATIONS_KEYS = ['ofw-notifications'];

async function fetchJson(url, options = {}) {
    const { headers: optionHeaders = {}, ...rest } = options;

    const res = await fetch(url, {
        ...rest,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...optionHeaders,
        },
    });
    if (!res.ok) throw new Error(`Failed: ${res.status}`);
    return res.json();
}

function formatTimestamp(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

export default function OfwNotificationBell() {
    const [open, setOpen] = useState(false);
    const wrapperRef = useRef(null);
    const queryClient = useQueryClient();

    // One query — the list endpoint also reports the unread count in meta.
    const { data: listData, isLoading, error } = useQuery({
        queryKey: NOTIFICATIONS_KEYS,
        queryFn: () => fetchJson('/my-cases/notifications'),
        refetchInterval: 60000,
        staleTime: 30000,
    });

    const notifications = listData?.data ?? [];
    const unreadCount = error ? 0 : (listData?.meta?.unread ?? 0);

    const markReadMutation = useMutation({
        mutationFn: (id) =>
            fetchJson(`/my-cases/notifications/${id}/read`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEYS });
        },
    });

    // Keep the badge fresh after every Inertia navigation (the layout persists
    // between OFW pages, so a mount-only fetch would go stale).
    useEffect(() => {
        const off = router.on('success', () => {
            queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEYS });
        });
        return off;
    }, [queryClient]);

    // Close on outside click
    useEffect(() => {
        function handleClickOutside(event) {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Close on Escape
    useEffect(() => {
        function handleEscape(event) {
            if (event.key === 'Escape') setOpen(false);
        }
        if (open) {
            document.addEventListener('keydown', handleEscape);
            return () => document.removeEventListener('keydown', handleEscape);
        }
    }, [open]);

    function handleItemClick(item) {
        setOpen(false);
        if (!item.action_url) return;
        if (!item.read_at) {
            markReadMutation.mutate(item.id);
        }
        router.visit(item.action_url);
    }

    const badgeLabel = unreadCount > 99 ? '99+' : unreadCount;

    return (
        <div className="relative" ref={wrapperRef}>
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                aria-label={unreadCount > 0 ? `Notifications, ${unreadCount} unread` : 'Notifications'}
                aria-expanded={open}
                aria-haspopup="true"
                className="relative inline-flex items-center justify-center rounded-lg p-2 text-slate-700 transition-colors hover:bg-slate-100 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            >
                <span className="material-symbols-outlined text-[20px]" aria-hidden="true">
                    notifications
                </span>
                {unreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white shadow-sm">
                        {badgeLabel}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-md border border-slate-200 bg-white shadow-xl">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h4 className="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-700">
                            Notifications
                        </h4>
                        {unreadCount > 0 && (
                            <span className="text-[10px] font-semibold text-slate-400">
                                {unreadCount} unread
                            </span>
                        )}
                    </div>

                    <div className="max-h-[min(24rem,calc(100vh-8rem))] overflow-y-auto owb-scroll-wide">
                        {isLoading && notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <span
                                    className="material-symbols-outlined animate-spin text-2xl text-slate-300"
                                    aria-hidden="true"
                                >
                                    progress_activity
                                </span>
                                <p className="mt-2 text-xs text-slate-400">Loading notifications...</p>
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <span
                                    className="material-symbols-outlined text-3xl text-slate-300"
                                    aria-hidden="true"
                                >
                                    notifications_off
                                </span>
                                <p className="mt-2 text-sm font-semibold text-slate-600">No notifications yet</p>
                                <p className="text-xs text-slate-400">
                                    We&apos;ll let you know when there&apos;s an update to your cases.
                                </p>
                            </div>
                        ) : (
                            notifications.map((item) => {
                                const isUnread = !item.read_at;
                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => handleItemClick(item)}
                                        className={`block w-full px-4 py-3 text-left transition-colors ${
                                            isUnread ? 'bg-blue-50/40' : ''
                                        } ${item.action_url ? 'cursor-pointer hover:bg-slate-100' : 'cursor-default'}`}
                                    >
                                        <div className="flex items-start gap-3">
                                            <span
                                                className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                                                    isUnread ? 'bg-blue-500' : 'bg-slate-200'
                                                }`}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p
                                                    className={`line-clamp-2 text-[12px] leading-snug ${
                                                        isUnread
                                                            ? 'font-semibold text-slate-900'
                                                            : 'font-medium text-slate-700'
                                                    }`}
                                                >
                                                    {item.title || 'Notification'}
                                                </p>
                                                {item.message && (
                                                    <p className="mt-0.5 line-clamp-2 text-[11px] leading-relaxed text-slate-500">
                                                        {item.message}
                                                    </p>
                                                )}
                                                <p className="mt-1 text-[10px] text-slate-400">
                                                    {formatTimestamp(item.created_at)}
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })
                        )}
                    </div>

                    <Link
                        href="/my-cases/notifications"
                        onClick={() => setOpen(false)}
                        className="flex items-center justify-center gap-1.5 border-t border-slate-100 px-4 py-3 text-[11px] font-bold text-primary transition-colors hover:bg-slate-50"
                    >
                        <span className="material-symbols-outlined text-[14px]" aria-hidden="true">
                            list_alt
                        </span>
                        View all
                    </Link>
                </div>
            )}
        </div>
    );
}
