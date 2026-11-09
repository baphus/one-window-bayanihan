import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import { EmptyState, SectionCard } from '@/Components/Dashboard/primitives';
import { JSON_HEADERS } from '@/Hooks/useJsonPoll';
import { sameOriginPath } from '@/lib/utils';

// Laravel paginator labels are server-escaped strings limited to page
// numbers plus &laquo; / &raquo; / &hellip; entities — decode them as text
// instead of injecting them with dangerouslySetInnerHTML.
function paginationLabel(label) {
    return String(label ?? '')
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&hellip;/g, '…')
        .replace(/<[^>]*>/g, '');
}

function formatTimestamp(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function patchRead(id) {
    return fetch(`/my-cases/notifications/${id}/read`, {
        method: 'PATCH',
        headers: {
            ...JSON_HEADERS,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
    }).then((res) => {
        if (!res.ok) throw new Error(`Failed: ${res.status}`);
        return res.json();
    });
}

function NotificationItem({ notification, isOptimisticallyRead, onOpen }) {
    const isUnread = !notification.read_at && !isOptimisticallyRead;

    return (
        <button
            type="button"
            onClick={() => onOpen(notification)}
            className={`flex w-full items-start gap-2.5 px-4 py-2.5 text-left transition-colors ${
                isUnread ? 'bg-primary/[0.06]' : 'bg-white'
            } ${notification.action_url ? 'cursor-pointer hover:bg-slate-50' : 'cursor-default'}`}
        >
            <span
                className={`mt-[7px] h-2 w-2 shrink-0 rounded-full ${
                    isUnread ? 'bg-primary' : 'bg-slate-200'
                }`}
            />
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-3">
                    <p className={`truncate text-[13px] ${isUnread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'}`}>
                        {notification.title || 'Notification'}
                    </p>
                    <span className="shrink-0 text-[10px] text-slate-400">
                        {formatTimestamp(notification.created_at)}
                    </span>
                </div>
                {notification.message && (
                    <p className="mt-0.5 line-clamp-1 text-[12px] leading-snug text-slate-500">
                        {notification.message}
                    </p>
                )}
            </div>
            {notification.action_url && (
                <span
                    className="material-symbols-outlined mt-1.5 shrink-0 text-[14px] text-slate-300"
                    aria-hidden="true"
                >
                    chevron_right
                </span>
            )}
        </button>
    );
}

export default function Notifications({ notifications }) {
    const notificationList = notifications?.data ?? [];
    // Unread rows clear the moment they are opened, without waiting for the
    // PATCH to round-trip (the server props still own the final state).
    const [optimisticallyRead, setOptimisticallyRead] = useState(() => new Set());

    const openNotification = (notification) => {
        if (!notification.read_at && !optimisticallyRead.has(notification.id)) {
            setOptimisticallyRead((prev) => new Set(prev).add(notification.id));
            patchRead(notification.id).catch(() => {});
        }
        const target = sameOriginPath(notification.action_url);
        if (target) {
            router.visit(target);
        }
    };

    return (
        <OfwPortalLayout title="Notifications">
            <p className="text-[12px] text-slate-500">Home / My Requests / Notifications</p>

            <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="mt-2 font-headline text-[24px] font-extrabold tracking-tight text-slate-900 sm:text-[28px]">
                        Notifications
                    </h1>
                    <p className="mt-1 text-[13px] text-slate-600">
                        Updates about your assistance requests.
                    </p>
                </div>
                <span className="text-[12px] text-slate-400">
                    {notifications?.meta ? `${notifications.meta.total} total` : ''}
                </span>
            </div>

            <div className="mt-5">
                {notificationList.length === 0 ? (
                    <SectionCard title="Updates">
                        <EmptyState message="You'll receive notifications here when there are updates to your assistance requests." />
                    </SectionCard>
                ) : (
                    <SectionCard title="Updates" bodyClassName="">
                        <div className="divide-y divide-slate-100">
                            {notificationList.map((notification) => (
                                <NotificationItem
                                    key={notification.id}
                                    notification={notification}
                                    isOptimisticallyRead={optimisticallyRead.has(notification.id)}
                                    onOpen={openNotification}
                                />
                            ))}
                        </div>
                    </SectionCard>
                )}
            </div>

            {/* Pagination */}
            {notifications?.links && notifications.links.length > 3 && (
                <nav className="mt-6 flex items-center justify-center gap-1" aria-label="Pagination">
                    {notifications.links.map((link) => {
                        // Paginator labels are unique per control (Previous /
                        // page numbers / Next), so the decoded text is a
                        // stable key — unlike the array index.
                        const label = paginationLabel(link.label);
                        if (!link.url || link.active) {
                            return (
                                <span
                                    key={label}
                                    className={`rounded px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'bg-primary font-semibold text-white'
                                            : 'text-slate-300'
                                    }`}
                                    aria-current={link.active ? 'page' : undefined}
                                    aria-disabled={!link.active}
                                >
                                    {label}
                                </span>
                            );
                        }

                        return (
                            <Link
                                key={label}
                                href={link.url}
                                className="rounded px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
                                preserveScroll
                            >
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            )}
        </OfwPortalLayout>
    );
}
