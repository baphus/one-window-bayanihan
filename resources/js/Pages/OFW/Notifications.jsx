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
                    : 'border-gray-200 bg-white'
            }`}
        >
            <div className="flex items-start gap-3">
                {/* Unread indicator */}
                <div className="mt-1.5 shrink-0">
                    {isUnread ? (
                        <span className="block h-2.5 w-2.5 rounded-full bg-blue-500" />
                    ) : (
                        <span className="block h-2.5 w-2.5 rounded-full bg-gray-200" />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <p className={`text-sm ${isUnread ? 'font-semibold text-gray-900' : 'font-medium text-gray-700'}`}>
                        {notification.title}
                    </p>
                    {notification.message && (
                        <p className="mt-1 text-sm text-gray-500">
                            {notification.message}
                        </p>
                    )}
                    <p className="mt-2 text-xs text-gray-400">
                        {formatDate(notification.created_at)}
                    </p>
                </div>
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-lg border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center">
            <span className="material-symbols-outlined text-5xl text-gray-300">notifications_off</span>
            <h2 className="mt-4 text-lg font-semibold text-gray-800">No notifications</h2>
            <p className="mx-auto mt-2 max-w-sm text-sm text-gray-500">
                You'll receive notifications here when there are updates to your cases.
            </p>
        </div>
    );
}

export default function Notifications({ notifications }) {
    const notificationList = notifications?.data ?? [];

    return (
        <OfwLayout title="Notifications">
            <div>
                <h1 className="text-xl font-bold text-gray-900">Notifications</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Updates about your cases
                </p>
            </div>

            <div className="mt-6">
                {notificationList.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="space-y-3">
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
                                    ? 'bg-blue-600 font-semibold text-white'
                                    : link.url
                                      ? 'text-gray-600 hover:bg-gray-100'
                                      : 'cursor-not-allowed text-gray-300'
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
