import { useState, useRef, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
  Bell,
  X,
  AlertTriangle,
  ShieldAlert,
  Info,
  CheckCircle2,
  ExternalLink,
  Loader2,
} from 'lucide-react';
import useAlerts from '@/Hooks/useAlerts';

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

export default function AlertBell() {
  const { alerts, unreadCount, dismiss, loading } = useAlerts();
  const [open, setOpen] = useState(false);
  const dropdownRef = useRef(null);
  const sharedAlertCount = usePage().props.alert_count ?? 0;

  // Close on click outside
  useEffect(() => {
    function handleClickOutside(event) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Use shared prop as initial badge value while loading
  const displayCount = loading ? sharedAlertCount : unreadCount;
  const recentAlerts = alerts.slice(0, 10);

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setOpen((prev) => !prev)}
        className="relative p-1.5 rounded-lg hover:bg-slate-100 transition-colors focus:outline-none"
        title="Alerts"
        aria-label={`Alerts${displayCount > 0 ? `, ${displayCount} unread` : ''}`}
      >
        <Bell className="w-5 h-5 text-slate-600" />
        {displayCount > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full shadow-sm">
            {displayCount > 99 ? '99+' : displayCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute left-0 top-full mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-lg z-50 overflow-hidden">
          {/* Header */}
          <div className="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h4 className="text-[11px] font-bold uppercase tracking-widest text-slate-700">
              Alerts
            </h4>
            {unreadCount > 0 && (
              <span className="text-[10px] font-semibold text-slate-400">
                {unreadCount} unread
              </span>
            )}
          </div>

          {/* Body */}
          <div className="max-h-80 overflow-y-auto">
            {loading && alerts.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <Loader2 className="w-6 h-6 animate-spin text-slate-300 mx-auto" />
                <p className="mt-2 text-xs text-slate-400">Loading alerts...</p>
              </div>
            ) : recentAlerts.length === 0 ? (
              <div className="px-4 py-8 text-center">
                <CheckCircle2 className="w-8 h-8 text-emerald-400 mx-auto" />
                <p className="mt-2 text-xs font-semibold text-slate-500">All clear</p>
                <p className="text-[10px] text-slate-400 mt-0.5">No active alerts</p>
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
                          <span
                            className={`h-1.5 w-1.5 rounded-full shrink-0 ${config.dot}`}
                          />
                          <span className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
                            {config.label}
                          </span>
                        </div>
                        <p className="mt-0.5 text-[12px] font-semibold text-slate-800 leading-snug line-clamp-2">
                          {alert.title || alert.subject || 'Alert'}
                        </p>
                        <div className="mt-1.5 flex items-center justify-between">
                          <span className="text-[10px] text-slate-400">
                            {timeAgo(alert.created_at)}
                          </span>
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              dismiss(alert.id);
                            }}
                            className="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 hover:text-rose-500 transition-colors"
                            title="Dismiss"
                          >
                            <X className="w-3 h-3" />
                            Dismiss
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Footer / View all */}
          <Link
            href="/insights?tab=operational"
            onClick={() => setOpen(false)}
            className="flex items-center justify-center gap-1.5 px-4 py-3 border-t border-slate-100 text-[11px] font-bold text-blue-900 hover:bg-blue-50/50 transition-colors"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            View all alerts
          </Link>
        </div>
      )}
    </div>
  );
}
