import { Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function NotificationItem({ notification }) {
    const isUnread = !notification.read_at;

    return (
        <div
            className={`rounded-lg border p-4 transition-colors ${
                isUnread
                    ? 'border-blue-200 bg-blue-50/50'
                    : 'border-slate-200 bg-white'
            }`}
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
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-lg border-2 border-dashed border-slate-200 bg-white px-6 py-16 text-center">
            <span className="material-symbols-outlined text-5xl text-slate-300">notifications_off</span>
            <h2 className="mt-4 text-lg font-semibold text-slate-800">No notifications</h2>
            <p className="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                You'll receive notifications here when there are updates to your cases.
            </p>
        </div>
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
