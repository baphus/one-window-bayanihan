import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import useJsonPoll, { JSON_HEADERS } from '@/Hooks/useJsonPoll';
import { useState } from 'react';
import {
  Bell,
  AlertTriangle,
  CheckCircle2,
  Loader2,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
} from 'lucide-react';
import {
  normalizeNotification,
  getSeverityConfig,
} from '@/lib/notifications';
import { formatDisplayDateTime, sameOriginPath } from '@/lib/utils';

// ─── Notifications Tab ───────────────────────────────────────────────────────

function NotificationsTab({ data, isLoading, error, page, onPageChange, onRetry, markReadMutation, markAllReadMutation }) {
  const items = data?.data ?? [];
  const normalized = items.map(normalizeNotification);
  const unreadItems = normalized.filter((n) => !n.is_read);
  const hasUnread = unreadItems.length > 0;
  const total = data?.meta?.total ?? 0;
  const perPage = data?.meta?.per_page ?? 20;
  const lastPage = data?.meta?.last_page ?? 1;
  const currentPage = data?.meta?.current_page ?? 1;
  const from = total > 0 ? (currentPage - 1) * perPage + 1 : 0;
  const to = total > 0 ? Math.min(currentPage * perPage, total) : 0;

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <Loader2 className="w-8 h-8 animate-spin text-slate-300" />
        <p className="mt-3 text-sm text-slate-400">Loading notifications...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <div className="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-3">
          <AlertTriangle className="w-6 h-6 text-red-400" />
        </div>
        <p className="text-sm font-semibold text-slate-700">Failed to load notifications</p>
        <p className="text-xs text-slate-400 mt-1 mb-4">{error.message}</p>
        <button
          onClick={onRetry}
          className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
        >
          <Loader2 className="w-3.5 h-3.5" />
          Try Again
        </button>
      </div>
    );
  }

  if (normalized.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-3">
          <Bell className="w-7 h-7 text-slate-300" />
        </div>
        <p className="text-sm font-semibold text-slate-600">No notifications yet</p>
        <p className="text-xs text-slate-400 mt-1">
          Notifications about your cases and referrals will appear here.
        </p>
      </div>
    );
  }

  return (
    <div>
      {/* Mark All Read */}
      {hasUnread && (
        <div data-tour="notifications-mark-all" className="flex items-center justify-end mb-3">
          <button
            onClick={() => markAllReadMutation.mutate()}
            disabled={markAllReadMutation.isPending}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors disabled:opacity-50"
          >
            <CheckCircle2 className="w-3.5 h-3.5" />
            {markAllReadMutation.isPending ? 'Marking...' : 'Mark All Read'}
          </button>
        </div>
      )}

      {/* Notification rows */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {normalized.map((item) => {
          const config = getSeverityConfig(item.severity);
          const Icon = config.icon;
          const isUnread = !item.is_read;

          return (
            <div
              key={item.id}
              onClick={() => {
                const target = sameOriginPath(item.action_url);
                if (!target) return;
                // Same trade-off as the bell dropdown: opening clears the
                // unread state, even if the user backs out immediately.
                if (!item.is_read) markReadMutation.mutate(item._rawId);
                router.visit(target);
              }}
              className={`border-b border-slate-100 last:border-b-0 transition-colors ${isUnread ? 'bg-blue-50/40' : 'bg-white'} ${item.action_url ? 'cursor-pointer hover:bg-slate-50/60' : ''}`}
            >
              <div className="flex items-start gap-3 px-3.5 py-2.5">
                <div className={`mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg ${config.chip}`}>
                  <Icon className="h-4 w-4" />
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-baseline justify-between gap-3">
                    <p className={`truncate text-[13px] ${isUnread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'}`}>
                      {item.title || 'Update'}
                    </p>
                    <span className="shrink-0 text-[11px] text-slate-400">
                      {formatDisplayDateTime(item.created_at) || 'Recently'}
                    </span>
                  </div>
                  {item.message ? (
                    <p className="mt-0.5 line-clamp-1 text-[12px] text-slate-500 leading-snug">{item.message}</p>
                  ) : null}
                  <div className="mt-1 flex flex-wrap items-center gap-1.5">
                    {item.case_number && (
                      <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-600">
                        <span className="material-symbols-outlined text-[11px] leading-none">folder</span>
                        {item.case_number}
                      </span>
                    )}
                    {item.client_name && (
                      <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">
                        <span className="material-symbols-outlined text-[11px] leading-none">account_circle</span>
                        {item.client_name}
                      </span>
                    )}
                    {item.actor_name && (
                      <span className="inline-flex items-center gap-1 text-[10px] text-slate-500">
                        <span className="material-symbols-outlined text-[11px] leading-none">person</span>
                        {item.actor_name}
                      </span>
                    )}
                    <div className="ml-auto flex items-center gap-2">
                      {isUnread && (
                        <span className="h-1.5 w-1.5 rounded-full bg-blue-500" aria-hidden="true" />
                      )}
                      {item.action_url && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            const target = sameOriginPath(item.action_url);
                            if (target) {
                              router.visit(target);
                            }
                          }}
                          className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 hover:text-blue-900 transition-colors"
                        >
                          <ExternalLink className="w-3 h-3" />
                          View
                        </button>
                      )}
                      {isUnread && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            markReadMutation.mutate(item._rawId);
                          }}
                          disabled={markReadMutation.pendingId === item._rawId}
                          className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 hover:text-blue-600 transition-colors disabled:opacity-50"
                        >
                          <CheckCircle2 className="w-3 h-3" />
                          Mark as Read
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="mt-6 flex items-center justify-between text-xs text-slate-500">
          <span>
            Showing {from}–{to} of {total}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => onPageChange(page - 1)}
              disabled={page <= 1}
              className="inline-flex items-center gap-1 px-3 py-1.5 font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <ChevronLeft className="w-3.5 h-3.5" />
              Prev
            </button>
            <span className="px-2 text-slate-400">
              Page {page} of {lastPage}
            </span>
            <button
              onClick={() => onPageChange(page + 1)}
              disabled={page >= lastPage}
              className="inline-flex items-center gap-1 px-3 py-1.5 font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Next
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}



// ─── Main Page ───────────────────────────────────────────────────────────────

export default function NotificationsIndex() {
  const [notifPage, setNotifPage] = useState(1);

  // ── Data fetching (polls every 60s, refetches on page change) ──
  const { data: notifData, isLoading: notifLoading, error: notifError, reload } = useJsonPoll(
    route('notifications.index', { per_page: 20, page: notifPage }),
  );

  // ── Mutations ──
  const [markingId, setMarkingId] = useState(null);
  const [markingAll, setMarkingAll] = useState(false);

  const patchNotification = (url) =>
    fetch(url, {
      method: 'PATCH',
      headers: {
        ...JSON_HEADERS,
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
    }).then((r) => { if (!r.ok) throw new Error(); return r.json(); });

  const markRead = async (rawId) => {
    setMarkingId(rawId);
    try {
      await patchNotification(route('notifications.mark-as-read', rawId));
      await reload();
    } finally {
      setMarkingId((current) => (current === rawId ? null : current));
    }
  };

  const markAllRead = async () => {
    setMarkingAll(true);
    try {
      await patchNotification(route('notifications.mark-all-read'));
      await reload();
    } finally {
      setMarkingAll(false);
    }
  };

  const markReadMutation = { mutate: markRead, isPending: markingId !== null, pendingId: markingId };
  const markAllReadMutation = { mutate: markAllRead, isPending: markingAll };

  // ── Handlers ──
  const goToPage = (page) => {
    setNotifPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // ── Render ──
  return (
    <AppLayout title="Notifications">
      <Head title="Notifications" />
      <div className="mx-auto max-w-5xl">
        {/* Page Header */}
        <div data-tour="notifications-header" className="mb-8">
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Notifications</h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">
            Stay updated on cases, referrals, and updates.
          </p>
        </div>

        <div data-tour="notifications-list">
          <NotificationsTab
            data={notifData}
            isLoading={notifLoading}
            error={notifError}
            page={notifPage}
            onPageChange={goToPage}
            onRetry={reload}
            markReadMutation={markReadMutation}
            markAllReadMutation={markAllReadMutation}
          />
        </div>
      </div>
    </AppLayout>
  );
}
