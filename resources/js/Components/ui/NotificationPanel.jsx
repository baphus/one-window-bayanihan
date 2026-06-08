import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Bell, X, AlertTriangle, ShieldAlert, Info, CheckCircle2, ExternalLink, Loader2 } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

const SEVERITY_CONFIG = {
  warning: { icon: AlertTriangle, dot: 'bg-amber-500', label: 'Warning' },
  critical: { icon: ShieldAlert, dot: 'bg-rose-500', label: 'Critical' },
  info: { icon: Info, dot: 'bg-blue-500', label: 'Info' },
  success: { icon: CheckCircle2, dot: 'bg-emerald-500', label: 'Success' },
};

function getSeverityConfig(severity) {
  return SEVERITY_CONFIG[severity?.toLowerCase()] || SEVERITY_CONFIG.info;
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const now = Date.now();
  const date = new Date(dateStr).getTime();
  const diffMs = now - date;
  if (diffMs < 0) return 'just now';
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 30) return `${diffDays}d ago`;
  const diffMonths = Math.floor(diffDays / 30);
  if (diffMonths < 12) return `${diffMonths}mo ago`;
  return `${Math.floor(diffMonths / 12)}y ago`;
}

export default function NotificationPanel() {
  const [open, setOpen] = useState(false);
  const panelRef = useRef(null);
  const queryClient = useQueryClient();

  // Use alert_count from Inertia props for initial badge value while query loads
  const { alert_count: initialCount } = usePage().props;

  const { data, isLoading, error } = useQuery({
    queryKey: ['alerts'],
    queryFn: async () => {
      const res = await fetch('/api/alerts', {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    refetchInterval: 60000,
    staleTime: 30000,
  });

  // Log errors for debugging but don't show error UI
  useEffect(() => {
    if (error) {
      console.error('[NotificationPanel] Alert fetch failed:', error.message);
    }
  }, [error]);

  const alerts = data?.data ?? [];
  const unreadCount = data?.unread_count ?? initialCount ?? 0;

  const dismissMutation = useMutation({
    mutationFn: (id) =>
      fetch(`/api/alerts/${id}/dismiss`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).then((res) => {
        if (!res.ok) throw new Error(`Failed to dismiss: ${res.status}`);
        return res.json();
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['alerts'] }),
  });

  const markReadMutation = useMutation({
    mutationFn: (id) =>
      fetch(`/api/alerts/${id}/read`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).then((res) => {
        if (!res.ok) throw new Error(`Failed to mark as read: ${res.status}`);
        return res.json();
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['alerts'] }),
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

  const recentAlerts = alerts.slice(0, 10);
  const displayCount = error ? 0 : unreadCount;

  return (
    <div className="relative" ref={panelRef}>
      {/* Bell button */}
      <button
        onClick={() => setOpen((prev) => !prev)}
        className="relative p-1.5 rounded-lg hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        title="Notifications"
        aria-label={`Notifications${displayCount > 0 ? `, ${displayCount} unread` : ''}`}
        aria-expanded={open}
        aria-haspopup="true"
      >
        <Bell className="w-5 h-5 text-slate-600" />
        {displayCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full shadow-sm">
            {displayCount > 99 ? '99+' : displayCount}
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
            {isLoading && alerts.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <Loader2 className="w-6 h-6 animate-spin text-slate-300 mx-auto" />
                <p className="mt-2 text-xs text-slate-400">Loading notifications...</p>
              </div>
            ) : recentAlerts.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <CheckCircle2 className="w-8 h-8 text-emerald-400 mx-auto" />
                <p className="mt-2 text-xs font-semibold text-slate-500">No new notifications</p>
                <p className="text-[10px] text-slate-400 mt-0.5">You're all caught up</p>
              </div>
            ) : (
              recentAlerts.map((alert) => {
                const config = getSeverityConfig(alert.severity || alert.type);
                const Icon = config.icon;
                const isUnread = !alert.read_at && !alert.is_read;

                return (
                  <div
                    key={alert.id}
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
                        </div>
                        <p className="mt-0.5 text-[12px] font-semibold text-slate-800 leading-snug line-clamp-2">
                          {alert.title || alert.subject || 'Alert'}
                        </p>
                        {alert.message && (
                          <p className="mt-0.5 text-[11px] text-slate-500 leading-relaxed line-clamp-2">
                            {alert.message}
                          </p>
                        )}
                        <div className="mt-1.5 flex items-center justify-between">
                          <span className="text-[10px] text-slate-400">
                            {timeAgo(alert.created_at)}
                          </span>
                          <div className="flex items-center gap-2">
                            {isUnread && !markReadMutation.isPending && (
                              <button
                                type="button"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  markReadMutation.mutate(alert.id);
                                }}
                                className="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 hover:text-blue-500 transition-colors"
                                title="Mark as read"
                              >
                                <CheckCircle2 className="w-3 h-3" />
                                Read
                              </button>
                            )}
                            {!dismissMutation.isPending && (
                              <button
                                type="button"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  dismissMutation.mutate(alert.id);
                                }}
                                className="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 hover:text-rose-500 transition-colors"
                                title="Dismiss"
                              >
                                <X className="w-3 h-3" />
                                Dismiss
                              </button>
                            )}
                          </div>
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
            href="/insights?tab=operational"
            onClick={() => setOpen(false)}
            className="flex items-center justify-center gap-1.5 px-4 py-3 border-t border-slate-100 text-[11px] font-bold text-blue-900 hover:bg-blue-50/50 transition-colors"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            View all alerts
          </a>
        </div>
      )}
    </div>
  );
}
