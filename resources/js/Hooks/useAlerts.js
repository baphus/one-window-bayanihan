import { useState, useEffect, useCallback, useRef } from 'react';

const POLL_INTERVAL = 60000; // 60 seconds

/**
 * Fetch and manage alerts from /api/alerts.
 *
 * @returns {{ alerts: Array, unreadCount: number, loading: boolean, error: string|null, dismiss: (id: string) => Promise<void>, markRead: (id: string) => Promise<void>, refresh: () => Promise<void> }}
 */
export default function useAlerts() {
  const [alerts, setAlerts] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const intervalRef = useRef(null);
  const abortRef = useRef(null);

  const fetchAlerts = useCallback(async () => {
    // Abort any in-flight request
    if (abortRef.current) {
      abortRef.current.abort();
    }
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setError(null);
      const res = await fetch('/api/alerts', {
        signal: controller.signal,
        headers: { Accept: 'application/json' },
      });

      if (!res.ok) {
        throw new Error(`Failed to fetch alerts: ${res.status}`);
      }

      const data = await res.json();
      setAlerts(data.data ?? []);
      setUnreadCount(data.unread_count ?? 0);
    } catch (err) {
      if (err.name === 'AbortError') return;
      console.error('[useAlerts] fetch failed:', err);
      setError('Unable to load alerts.');
    } finally {
      setLoading(false);
    }
  }, []);

  const dismiss = useCallback(async (id) => {
    try {
      const res = await fetch(`/api/alerts/${id}/dismiss`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      });

      if (!res.ok) {
        throw new Error(`Failed to dismiss alert: ${res.status}`);
      }

      setAlerts((prev) => prev.filter((a) => a.id !== id));

      setUnreadCount((prev) => {
        const dismissed = alerts.find((a) => a.id === id);
        return dismissed && !dismissed.read_at ? Math.max(0, prev - 1) : prev;
      });
    } catch (err) {
      console.error('[useAlerts] dismiss failed:', err);
      setError('Unable to dismiss alert.');
    }
  }, [alerts]);

  const markRead = useCallback(async (id) => {
    try {
      const res = await fetch(`/api/alerts/${id}/read`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
      });

      if (!res.ok) {
        throw new Error(`Failed to mark alert as read: ${res.status}`);
      }

      setAlerts((prev) =>
        prev.map((a) => (a.id === id ? { ...a, read_at: new Date().toISOString() } : a)),
      );

      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (err) {
      console.error('[useAlerts] markRead failed:', err);
      setError('Unable to update alert status.');
    }
  }, []);

  const refresh = useCallback(async () => {
    setLoading(true);
    await fetchAlerts();
  }, [fetchAlerts]);

  // Fetch on mount
  useEffect(() => {
    fetchAlerts();
  }, [fetchAlerts]);

  // Poll every 60s
  useEffect(() => {
    intervalRef.current = setInterval(fetchAlerts, POLL_INTERVAL);
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [fetchAlerts]);

  return { alerts, unreadCount, loading, error, dismiss, markRead, refresh };
}
