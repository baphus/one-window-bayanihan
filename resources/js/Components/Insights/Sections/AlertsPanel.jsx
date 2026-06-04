import { useState, useEffect, useCallback } from 'react';
import { Bell, CheckCircle2, XCircle, RefreshCw, Loader2, AlertTriangle, Info, ShieldAlert } from 'lucide-react';

const TYPE_CONFIG = {
  warning: { icon: AlertTriangle, bg: 'bg-amber-50 border-amber-200', dot: 'bg-amber-500', label: 'Warning' },
  critical: { icon: ShieldAlert, bg: 'bg-rose-50 border-rose-200', dot: 'bg-rose-500', label: 'Critical' },
  info: { icon: Info, bg: 'bg-blue-50 border-blue-200', dot: 'bg-blue-500', label: 'Info' },
  success: { icon: CheckCircle2, bg: 'bg-emerald-50 border-emerald-200', dot: 'bg-emerald-500', label: 'Success' },
};

function getTypeConfig(type) {
  return TYPE_CONFIG[type?.toLowerCase()] || TYPE_CONFIG.info;
}

function AlertCard({ alert, onDismiss, onMarkRead }) {
  const config = getTypeConfig(alert.type);
  const Icon = config.icon;

  return (
    <div className={`rounded border p-3 ${config.bg} transition-shadow hover:shadow-sm`}>
      <div className="flex items-start gap-3">
        <div className="mt-0.5 shrink-0">
          <Icon className="h-4 w-4 text-slate-600" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <span className={`h-1.5 w-1.5 rounded-full ${config.dot}`} />
            <span className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
              {config.label}
            </span>
            {alert.created_at && (
              <span className="text-[10px] text-slate-400">
                {new Date(alert.created_at).toLocaleString()}
              </span>
            )}
          </div>
          <p className="mt-1 text-[12px] font-semibold text-slate-800">{alert.title || alert.subject || 'Alert'}</p>
          {alert.description && (
            <p className="mt-0.5 text-[11px] text-slate-600">{alert.description}</p>
          )}
          {alert.message && !alert.description && (
            <p className="mt-0.5 text-[11px] text-slate-600">{alert.message}</p>
          )}
          <div className="mt-2 flex items-center gap-2">
            {!alert.read_at && !alert.is_read && (
              <button
                type="button"
                onClick={() => onMarkRead(alert.id)}
                className="inline-flex items-center gap-1 rounded bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-[0.06em] text-slate-600 shadow-sm border border-[#cbd5e1] hover:bg-slate-50 transition-colors"
              >
                <CheckCircle2 className="h-3 w-3" />
                Mark Read
              </button>
            )}
            <button
              type="button"
              onClick={() => onDismiss(alert.id)}
              className="inline-flex items-center gap-1 rounded bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-[0.06em] text-slate-400 shadow-sm border border-[#cbd5e1] hover:text-rose-600 hover:border-rose-200 transition-colors"
            >
              <XCircle className="h-3 w-3" />
              Dismiss
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function AlertsPanel() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchAlerts = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch('/api/alerts');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      setAlerts(Array.isArray(json) ? json : json.data || json.alerts || []);
    } catch (err) {
      setError(err.message || 'Unable to load alerts');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchAlerts();
  }, [fetchAlerts]);

  const handleDismiss = useCallback(async (id) => {
    try {
      const res = await fetch(`/api/alerts/${id}/dismiss`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setAlerts((prev) => prev.filter((a) => a.id !== id));
    } catch (err) {
      // Silently fail — alerts will reappear on next fetch
      fetchAlerts();
    }
  }, [fetchAlerts]);

  const handleMarkRead = useCallback(async (id) => {
    try {
      const res = await fetch(`/api/alerts/${id}/read`, { method: 'POST' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setAlerts((prev) =>
        prev.map((a) => (a.id === id ? { ...a, read_at: new Date().toISOString(), is_read: true } : a))
      );
    } catch (err) {
      // Silently fail
    }
  }, []);

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center py-16">
        <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
        <p className="mt-3 text-[13px] text-slate-500">Loading alerts...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-16">
        <Bell className="h-8 w-8 text-rose-400" />
        <p className="mt-3 text-[13px] text-rose-600">{error}</p>
        <button
          type="button"
          onClick={fetchAlerts}
          className="mt-3 inline-flex items-center gap-1.5 rounded border border-[#cbd5e1] bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
        >
          <RefreshCw className="h-3.5 w-3.5" />
          Retry
        </button>
      </div>
    );
  }

  if (alerts.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16">
        <CheckCircle2 className="h-10 w-10 text-emerald-400" />
        <p className="mt-3 text-[13px] font-semibold text-slate-500">No active alerts</p>
        <p className="text-[11px] text-slate-400">All systems operating normally.</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-[11px] font-semibold text-slate-500">
          {alerts.length} active alert{alerts.length !== 1 ? 's' : ''}
        </p>
        <button
          type="button"
          onClick={fetchAlerts}
          className="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.08em] text-[#0b5a8c] hover:text-[#094a73] transition-colors"
        >
          <RefreshCw className="h-3 w-3" />
          Refresh
        </button>
      </div>
      {alerts.map((alert) => (
        <AlertCard
          key={alert.id}
          alert={alert}
          onDismiss={handleDismiss}
          onMarkRead={handleMarkRead}
        />
      ))}
    </div>
  );
}
