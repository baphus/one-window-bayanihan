import { Link } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';
import { EmptyState, PageHeader, SectionCard } from '@/Components/Dashboard/primitives';

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
            className={`px-5 py-3 transition-colors ${
                isUnread
                    ? 'bg-primary/5'
                    : 'bg-white'
            }`}
        >
            <div className="flex items-start gap-3">
                {/* Unread indicator */}
                <div className="mt-1.5 shrink-0">
                    {isUnread ? (
                        <span className="block h-2.5 w-2.5 rounded-full bg-primary" />
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

            <PageHeader
                eyebrow="My cases"
                title="Notifications"
                subtitle="Updates about your cases"
            />

            <div className="mt-6">
                {notificationList.length === 0 ? (
                    <SectionCard title="Updates">
                        <EmptyState message="You'll receive notifications here when there are updates to your cases." />
                    </SectionCard>
                ) : (
                    <SectionCard title="Updates" bodyClassName="">
                        <div className="max-h-[calc(100vh-18rem)] divide-y divide-slate-100 overflow-y-auto owb-scroll-wide">
                            {notificationList.map((notification) => (
                                <NotificationItem
                                    key={notification.id}
                                    notification={notification}
                                />
                            ))}
                        </div>
                    </SectionCard>
                )}
            </div>

            {/* Pagination */}
            {notifications?.links && notifications.links.length > 3 && (
                <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
                    {notifications.links.map((link) => {
                        // Paginator labels are unique per control (Previous /
                        // page numbers / Next), so the decoded text is a
                        // stable key — unlike the array index.
                        const label = paginationLabel(link.label);
                        return (
                            <Link
                                key={label}
                                href={link.url ?? '#'}
                                className={`rounded px-3 py-1.5 text-sm ${
                                    link.active
                                        ? 'bg-primary font-semibold text-white'
                                        : link.url
                                          ? 'text-slate-600 hover:bg-slate-100'
                                          : 'cursor-not-allowed text-slate-300'
                                }`}
                                preserveScroll
                            >
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            )}
        </OfwLayout>
    );
}
