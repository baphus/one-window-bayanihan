import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Bell, CheckCircle2, ExternalLink, Loader2 } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { normalizeNotification, getSeverityConfig, timeAgo } from '@/lib/notifications';

export default function NotificationPanel() {
  const [open, setOpen] = useState(false);
  const panelRef = useRef(null);
  const queryClient = useQueryClient();

  // ── Notifications query ──
  const { data: notifData, isLoading: notifLoading, error: notifError } = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => {
      const res = await fetch('/notifications?per_page=20', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    refetchInterval: 60000,
    staleTime: 30000,
  });

  // ── Notifications unread count ──
  const { data: notifUnreadData } = useQuery({
    queryKey: ['notifications', 'unread-count'],
    queryFn: async () => {
      const res = await fetch('/notifications/unread-count', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    refetchInterval: 60000,
    staleTime: 30000,
  });

  // Log errors for debugging but don't show error UI
  useEffect(() => {
    if (notifError) {
      console.error('[NotificationPanel] Notifications fetch failed:', notifError);
    }
  }, [notifError]);

  // ── Raw data ──
  const rawNotifications = notifData?.data ?? [];

  // Normalize Laravel notifications to alert format
  const notifications = rawNotifications.map(normalizeNotification);

  // ── Take top 10 ──
  const displayItems = notifications.slice(0, 10);

  // ── Unread count ──
  const unreadCount = notifError ? 0 : (notifUnreadData?.count ?? 0);

  // ── Mutations ──
  const markNotifReadMutation = useMutation({
    mutationFn: (rawId) =>
      fetch(`/notifications/${rawId}/read`, {
        method: 'PATCH',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      }).then((res) => {
        if (!res.ok) throw new Error(`Failed to mark as read: ${res.status}`);
        return res.json();
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'unread-count'] });
    },
  });

  // Close on click outside
  useEffect(() => {
    function handleClickOutside(event) {
      if (panelRef.current && !panelRef.current.contains(event.target)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Close on Escape key
  useEffect(() => {
    function handleEscape(event) {
      if (event.key === 'Escape') setOpen(false);
    }
    if (open) {
      document.addEventListener('keydown', handleEscape);
      return () => document.removeEventListener('keydown', handleEscape);
    }
  }, [open]);

  return (
    <div className="relative" ref={panelRef}>
      {/* Bell button */}
      <button
        onClick={() => setOpen((prev) => !prev)}
        className="relative p-1.5 rounded-lg hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        title="Notifications"
        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
        aria-expanded={open}
        aria-haspopup="true"
      >
        <Bell className="w-5 h-5 text-slate-600" />
        {unreadCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full shadow-sm">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {/* Dropdown panel */}
      {open && (
        <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-lg z-50 overflow-hidden">
          {/* Header */}
          <div className="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h4 className="text-[11px] font-bold uppercase tracking-widest text-slate-700">
              Notifications
            </h4>
            {unreadCount > 0 && (
              <span className="text-[10px] font-semibold text-slate-400">
                {unreadCount} unread
              </span>
            )}
          </div>

          {/* Body */}
          <div className="max-h-96 overflow-y-auto">
            {notifLoading && displayItems.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <Loader2 className="w-6 h-6 animate-spin text-slate-300 mx-auto" />
                <p className="mt-2 text-xs text-slate-400">Loading notifications...</p>
              </div>
            ) : displayItems.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <CheckCircle2 className="w-8 h-8 text-emerald-400 mx-auto" />
                <p className="mt-2 text-xs font-semibold text-slate-500">No new notifications</p>
                <p className="text-[10px] text-slate-400 mt-0.5">You're all caught up</p>
              </div>
            ) : (
              displayItems.map((item) => {
                const config = getSeverityConfig(item.severity);
                const Icon = config.icon;
                const isUnread = !item.is_read;

                return (
                  <div
                    key={item.id}
                    className={`px-4 py-3 border-b border-slate-50 last:border-b-0 hover:bg-slate-50 transition-colors ${
                      isUnread ? 'bg-blue-50/40' : ''
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      {/* Severity icon */}
                      <div className="mt-0.5 shrink-0">
                        <Icon className="w-4 h-4 text-slate-500" />
                      </div>

                      {/* Content */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                          <span className={`h-1.5 w-1.5 rounded-full shrink-0 ${config.dot}`} />
                          <span className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
                            {config.label}
                          </span>
                          <span className="text-[9px] font-semibold text-slate-300 uppercase tracking-wider">
                            System
                          </span>
                        </div>
                        <p className="mt-0.5 text-[12px] font-semibold text-slate-800 leading-snug line-clamp-2">
                          {item.title || 'Notification'}
                        </p>
                        {item.message && (
                          <p className="mt-0.5 text-[11px] text-slate-500 leading-relaxed line-clamp-2">
                            {item.message}
                          </p>
                        )}
                        <div className="mt-1.5 flex items-center justify-between">
                          <span className="text-[10px] text-slate-400">
                            {timeAgo(item.created_at)}
                          </span>
                          {isUnread && (
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                markNotifReadMutation.mutate(item._rawId);
                              }}
                              disabled={markNotifReadMutation.isPending}
                              className="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 hover:text-blue-500 transition-colors"
                              title="Mark as read"
                            >
                              <CheckCircle2 className="w-3 h-3" />
                              Read
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Footer */}
          <a
            href="/notifications/page"
            onClick={() => setOpen(false)}
            className="flex items-center justify-center gap-1.5 px-4 py-3 border-t border-slate-100 text-[11px] font-bold text-blue-900 hover:bg-blue-50/50 transition-colors"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            View all notifications
          </a>
        </div>
      )}
    </div>
  );
}
