import { Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';
import { TimelineEmptyState } from '@/Components/Timeline';
import { formatClientDateTime as formatDate } from '@/Components/clientDates';

function NotificationItem({ notification }) {
    const isUnread = !notification.read_at;
    // Same destination the header bell uses: the per-item action URL when the
    // payload carries one, otherwise the case detail page for the
    // notification's case. Items without a destination stay static text.
    const href = notification.action_url
        ?? (notification.case_id ? route('ofw.case.show', notification.case_id) : null);
    const Wrapper = href ? Link : 'div';

    return (
        <Wrapper
            {...(href ? { href } : {})}
            className={`block rounded-md border p-4 transition-colors ${
                isUnread
                    ? 'border-blue-200 bg-blue-50/50'
                    : 'border-slate-200 bg-white'
            } ${href ? 'hover:border-primary/40 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' : ''}`}
        >
            <div className="flex items-start gap-3">
                {/* Unread indicator */}
                <div className="mt-1.5 shrink-0">
                    {isUnread ? (
                        <span className="block h-2.5 w-2.5 rounded-full bg-blue-500" />
                    ) : (
                        <span className="block h-2.5 w-2.5 rounded-full bg-slate-200" />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <p className={`text-sm ${isUnread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'}`}>
                        {notification.title}
                    </p>
                    {notification.message && (
                        <p className="mt-1 text-sm text-slate-500">
                            {notification.message}
                        </p>
                    )}
                    <p className="mt-2 text-xs text-slate-400">
                        {formatDate(notification.created_at)}
                    </p>
                </div>
                {href && (
                    <span aria-hidden="true" className="material-symbols-outlined mt-1 shrink-0 text-[18px] text-slate-300">
                        chevron_right
                    </span>
                )}
            </div>
        </Wrapper>
    );
}

function EmptyState() {
    return (
        <TimelineEmptyState
            icon="notifications_off"
            title="No notifications"
            action={
                <p className="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    You&apos;ll receive notifications here when there are updates to your cases.
                </p>
            }
        />
    );
}

export default function Notifications({ notifications }) {
    const notificationList = notifications?.data ?? [];

    return (
        <OfwLayout title="Notifications">
            {/* Back to My Cases */}
            <Link
                href={route('ofw.dashboard')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to My Cases
            </Link>

            <div className="mt-4">
                <h1 className="text-xl font-extrabold font-headline tracking-tight text-slate-900">Notifications</h1>
                <p className="mt-1 text-sm text-slate-400 font-body">
                    Updates about your cases
                </p>
            </div>

            <div className="mt-6">
                {notificationList.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="max-h-[calc(100vh-18rem)] space-y-3 overflow-y-auto pr-1 owb-scroll-wide">
                        {notificationList.map((notification) => (
                            <NotificationItem
                                key={notification.id}
                                notification={notification}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Pagination */}
            {notifications?.links && notifications.links.length > 3 && (
                <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
                    {notifications.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            className={`rounded px-3 py-1.5 text-sm ${
                                link.active
                                    ? 'bg-primary font-semibold text-white'
                                    : link.url
                                      ? 'text-slate-600 hover:bg-slate-100'
                                      : 'cursor-not-allowed text-slate-300'
                            }`}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </OfwLayout>
    );
}
