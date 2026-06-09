import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState, useEffect, useRef } from 'react';
import {
  Bell,
  AlertTriangle,
  ShieldAlert,
  Info,
  CheckCircle2,
  X,
  Loader2,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react';
import {
  extractNotificationTypeName,
  normalizeNotification,
  SEVERITY_CONFIG,
  getSeverityConfig,
  timeAgo,
  formatDisplayDate,
} from '@/lib/notifications';

// ─── Notifications Tab ───────────────────────────────────────────────────────

function NotificationsTab({ data, isLoading, error, page, onPageChange, queryClient, markReadMutation, markAllReadMutation }) {
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
          onClick={() => queryClient.invalidateQueries({ queryKey: ['notifications-page'] })}
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
        <div className="flex items-center justify-end mb-4">
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
              className={`px-5 py-4 border-b border-slate-100 last:border-b-0 ${isUnread ? 'bg-blue-50/30' : ''}`}
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
                  <p className="mt-1 text-[13px] font-semibold text-slate-800">{item.title}</p>
                  {item.message && (
                    <p className="mt-1 text-[12px] text-slate-500 leading-relaxed">{item.message}</p>
                  )}
                  <div className="mt-2 flex items-center justify-between">
                    <span className="text-[11px] text-slate-400" title={formatDisplayDate(item.created_at)}>
                      {timeAgo(item.created_at)}
                    </span>
                    <div className="flex items-center gap-2">
                      {isUnread && (
                        <button
                          onClick={() => markReadMutation.mutate(item._rawId)}
                          disabled={markReadMutation.isPending}
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

// ─── Alerts Tab ──────────────────────────────────────────────────────────────

function AlertsTab({ data, isLoading, error, queryClient, markReadMutation, markAllReadMutation, dismissMutation }) {
  const items = data?.data ?? [];
  const hasUnread = (data?.unread_count ?? 0) > 0;

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <Loader2 className="w-8 h-8 animate-spin text-slate-300" />
        <p className="mt-3 text-sm text-slate-400">Loading alerts...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <div className="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-3">
          <AlertTriangle className="w-6 h-6 text-red-400" />
        </div>
        <p className="text-sm font-semibold text-slate-700">Failed to load alerts</p>
        <p className="text-xs text-slate-400 mt-1 mb-4">{error.message}</p>
        <button
          onClick={() => queryClient.invalidateQueries({ queryKey: ['alerts-page'] })}
          className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
        >
          <Loader2 className="w-3.5 h-3.5" />
          Try Again
        </button>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-3">
          <ShieldAlert className="w-7 h-7 text-slate-300" />
        </div>
        <p className="text-sm font-semibold text-slate-600">No active alerts</p>
        <p className="text-xs text-slate-400 mt-1">
          System alerts and important updates will appear here.
        </p>
      </div>
    );
  }

  return (
    <div>
      {/* Mark All Read */}
      {hasUnread && (
        <div className="flex items-center justify-end mb-4">
          <button
            onClick={() => markAllReadMutation.mutate()}
            disabled={markAllReadMutation.isPending}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors disabled:opacity-50"
          >
            <CheckCircle2 className="w-3.5 h-3.5" />
            {markAllReadMutation.isPending ? 'Marking...' : 'Mark All Read'}
          </button>
        </div>
      )}

      {/* Alert cards */}
      <div className="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
        {items.map((item) => {
          const config = getSeverityConfig(item.severity || item.type);
          const Icon = config.icon;
          const isUnread = !item.read_at && !item.is_read;
          const isDismissed = !!item.dismissed_at;

          return (
            <div
              key={item.id}
              className={`px-5 py-4 border-b border-slate-100 last:border-b-0 ${isUnread ? 'bg-amber-50/30' : ''}`}
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
                  <p className="mt-1 text-[13px] font-semibold text-slate-800">{item.title || item.subject || 'Alert'}</p>
                  {item.message && (
                    <p className="mt-1 text-[12px] text-slate-500 leading-relaxed">{item.message}</p>
                  )}
                  <div className="mt-2 flex items-center justify-between">
                    <span className="text-[11px] text-slate-400" title={formatDisplayDate(item.created_at)}>
                      {timeAgo(item.created_at)}
                    </span>
                    <div className="flex items-center gap-2">
                      {isUnread && !isDismissed && (
                        <button
                          onClick={() => markReadMutation.mutate(item.id)}
                          disabled={markReadMutation.isPending}
                          className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 hover:text-blue-600 transition-colors disabled:opacity-50"
                        >
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          Mark as Read
                        </button>
                      )}
                      {!isDismissed && (
                        <button
                          onClick={() => dismissMutation.mutate(item.id)}
                          disabled={dismissMutation.isPending}
                          className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 hover:text-rose-500 transition-colors disabled:opacity-50"
                        >
                          <X className="w-3.5 h-3.5" />
                          Dismiss
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
    </div>
  );
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export default function NotificationsIndex() {
  const queryClient = useQueryClient();

  // ── Tab state with URL sync (silent replaceState) ──
  const [activeTab, setActiveTab] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    return tab === 'alerts' ? 'alerts' : 'notifications';
  });

  const [notifPage, setNotifPage] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('page') || '1', 10);
  });

  const isInitialRender = useRef(true);

  useEffect(() => {
    if (isInitialRender.current) {
      isInitialRender.current = false;
      return;
    }
    const params = new URLSearchParams(window.location.search);
    if (activeTab !== 'notifications') {
      params.set('tab', activeTab);
    } else {
      params.delete('tab');
    }
    if (notifPage > 1) {
      params.set('page', notifPage.toString());
    } else {
      params.delete('page');
    }
    const qs = params.toString();
    const current = window.location.search.slice(1);
    if (current !== qs) {
      window.history.replaceState(null, '', '/notifications/page' + (qs ? '?' + qs : ''));
    }
  }, [activeTab, notifPage]);

  // ── Data fetching ──
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useQuery({
    queryKey: ['alerts-page'],
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

  const { data: notifData, isLoading: notifLoading, error: notifError, refetch: refetchNotifs } = useQuery({
    queryKey: ['notifications-page', notifPage],
    queryFn: async () => {
      const res = await fetch(`/notifications?per_page=20&page=${notifPage}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    refetchInterval: 60000,
    staleTime: 30000,
  });

  const { data: unreadCountData } = useQuery({
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

  // ── Mutations ──
  const markReadMutation = useMutation({
    mutationFn: (rawId) =>
      fetch(`/notifications/${rawId}/read`, {
        method: 'PATCH',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      }).then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications-page'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'unread-count'] });
    },
  });

  const markAllReadMutation = useMutation({
    mutationFn: () =>
      fetch('/notifications/mark-all-read', {
        method: 'PATCH',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      }).then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications-page'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'unread-count'] });
    },
  });

  const markAlertReadMutation = useMutation({
    mutationFn: (id) =>
      fetch(`/api/alerts/${id}/read`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts-page'] });
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
    },
  });

  const markAllAlertsReadMutation = useMutation({
    mutationFn: () =>
      fetch('/api/alerts/mark-all-read', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts-page'] });
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
    },
  });

  const dismissAlertMutation = useMutation({
    mutationFn: (id) =>
      fetch(`/api/alerts/${id}/dismiss`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      }).then((r) => { if (!r.ok) throw new Error(); return r.json(); }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts-page'] });
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
    },
  });

  // ── Handlers ──
  const handleTabChange = (tab) => {
    setActiveTab(tab);
    setNotifPage(1);
  };

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
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Notifications</h1>
          <p className="text-sm text-slate-500 mt-1">
            Stay updated on cases, referrals, and system alerts.
          </p>
        </div>

        {/* Tab Navigation */}
        <div className="flex items-center border-b border-slate-200 mb-6">
          <button
            onClick={() => handleTabChange('notifications')}
            className={`px-6 py-3 text-[13px] font-bold transition-colors border-b-2 ${
              activeTab === 'notifications'
                ? 'text-blue-900 border-blue-900'
                : 'text-slate-500 border-transparent hover:text-slate-700 hover:border-slate-300'
            }`}
          >
            Notifications
            {(unreadCountData?.count ?? 0) > 0 && (
              <span className="ml-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full">
                {unreadCountData.count}
              </span>
            )}
          </button>
          <button
            onClick={() => handleTabChange('alerts')}
            className={`px-6 py-3 text-[13px] font-bold transition-colors border-b-2 ${
              activeTab === 'alerts'
                ? 'text-blue-900 border-blue-900'
                : 'text-slate-500 border-transparent hover:text-slate-700 hover:border-slate-300'
            }`}
          >
            Alerts
            {(alertsData?.unread_count ?? 0) > 0 && (
              <span className="ml-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-amber-500 rounded-full">
                {alertsData.unread_count}
              </span>
            )}
          </button>
        </div>

        {/* Tab Content */}
        {activeTab === 'notifications' && (
          <NotificationsTab
            data={notifData}
            isLoading={notifLoading}
            error={notifError}
            page={notifPage}
            onPageChange={goToPage}
            queryClient={queryClient}
            markReadMutation={markReadMutation}
            markAllReadMutation={markAllReadMutation}
          />
        )}
        {activeTab === 'alerts' && (
          <AlertsTab
            data={alertsData}
            isLoading={alertsLoading}
            error={alertsError}
            queryClient={queryClient}
            markReadMutation={markAlertReadMutation}
            markAllReadMutation={markAllAlertsReadMutation}
            dismissMutation={dismissAlertMutation}
          />
        )}
      </div>
    </AppLayout>
  );
}
