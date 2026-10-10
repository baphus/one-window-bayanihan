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
import { formatDisplayDateTime } from '@/lib/utils';

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
        <div data-tour="notifications-mark-all" className="flex items-center justify-end mb-4">
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

      {/* Notification cards */}
      <div className="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        {normalized.map((item) => {
          const config = getSeverityConfig(item.severity);
          const Icon = config.icon;
          const isUnread = !item.is_read;

          return (
            <div
              key={item.id}
              onClick={() => {
                if (item.action_url && item.action_url.startsWith('/')) {
                  router.visit(item.action_url);
                }
              }}
              className={`px-5 py-4 border-b border-slate-100 last:border-b-0 ${isUnread ? 'bg-blue-50/30' : ''} ${item.action_url ? 'cursor-pointer hover:bg-slate-50/50' : ''} transition-colors`}
            >
              <div className="flex items-start gap-3">
                <div className="mt-0.5 shrink-0">
                  <Icon className="w-5 h-5 text-slate-500" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className={`h-2 w-2 rounded-full shrink-0 ${config.dot}`} />
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
                      {config.label}
                    </span>
                  </div>
                  <p className="mt-1 text-[13px] font-semibold text-slate-800">{item.title || 'Update'}</p>
                  {item.message ? (
                    <p className="mt-1 text-[12px] text-slate-500 leading-relaxed">{item.message}</p>
                  ) : null}
                  {(item.case_number || item.actor_name) && (
                    <div className="mt-1.5 flex flex-wrap items-center gap-2">
                      {item.case_number && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-600">
                          <span className="material-symbols-outlined text-[12px] leading-none">folder</span>
                          {item.case_number}
                        </span>
                      )}
                      {item.actor_name && (
                        <span className="inline-flex items-center gap-1 text-[11px] text-slate-500">
                          <span className="material-symbols-outlined text-[12px] leading-none">person</span>
                          {item.actor_name}
                        </span>
                      )}
                    </div>
                  )}
                  <div className="mt-2 flex items-center justify-between">
                    <span className="text-[11px] text-slate-400">
                      {formatDisplayDateTime(item.created_at) || 'Recently'}
                    </span>
                    <div className="flex items-center gap-2">
                      {item.action_url && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            if (item.action_url && item.action_url.startsWith('/')) {
                              router.visit(item.action_url);
                            }
                          }}
                          className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 hover:text-blue-900 transition-colors"
                        >
                          <ExternalLink className="w-3.5 h-3.5" />
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
                          <CheckCircle2 className="w-3.5 h-3.5" />
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
