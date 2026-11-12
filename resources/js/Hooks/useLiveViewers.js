import { useEffect, useState } from 'react';
import axios from 'axios';

export function normalizeViewers(viewers) {
    if (!Array.isArray(viewers)) return [];
    const seen = new Set();
    return viewers.filter((viewer) => {
        if (!viewer?.id || !viewer?.name || seen.has(String(viewer.id))) return false;
        seen.add(String(viewer.id));
        return true;
    });
}

export default function useLiveViewers(url, enabled = true) {
    const [roster, setRoster] = useState({ key: null, viewers: [] });
    const viewers = roster.key === `${url ?? ''}:${enabled}` ? roster.viewers : [];

    useEffect(() => {
        const key = `${url ?? ''}:${enabled}`;
        setRoster({ key, viewers: [] });
        if (!url || !enabled) {
            setRoster({ key, viewers: [] });
            return undefined;
        }

        let active = true;
        let controller = null;
        let timer = null;
        let leaseTimer = null;
        let inFlight = false;
        let stopped = false;
        let lastSuccess = 0;
        const ttl = 60_000;

        const schedule = (delay) => {
            if (!active || stopped || document.hidden) return;
            timer = window.setTimeout(poll, delay);
        };

        const poll = async () => {
            if (!active || stopped || document.hidden || inFlight) return;
            inFlight = true;
            controller = new AbortController();
            try {
                const response = await axios.post(url, {}, { signal: controller.signal });
                if (!active) return;
                lastSuccess = Date.now();
                window.clearTimeout(leaseTimer);
                leaseTimer = window.setTimeout(() => {
                    if (active && Date.now() - lastSuccess >= ttl) setRoster({ key, viewers: [] });
                }, ttl);
                setRoster({ key, viewers: normalizeViewers(response.data?.viewers) });
            } catch (error) {
                if (!active || axios.isCancel?.(error) || error?.name === 'CanceledError') return;
                const status = error?.response?.status;
                if (status === 403 || status === 404) {
                    stopped = true;
                    setRoster({ key, viewers: [] });
                    return;
                }
                if (lastSuccess && Date.now() - lastSuccess >= ttl) setRoster({ key, viewers: [] });
            } finally {
                inFlight = false;
                controller = null;
                if (active && !stopped) schedule(20_000);
            }
        };

        const onVisibilityChange = () => {
            if (document.hidden) {
                window.clearTimeout(timer);
                if (lastSuccess && Date.now() - lastSuccess >= ttl) setRoster({ key, viewers: [] });
                return;
            }
            if (lastSuccess && Date.now() - lastSuccess >= ttl) setRoster({ key, viewers: [] });
            window.clearTimeout(timer);
            poll();
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        if (!document.hidden) poll();
        return () => {
            active = false;
            window.clearTimeout(timer);
            window.clearTimeout(leaseTimer);
            controller?.abort();
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    }, [url, enabled]);

    return viewers;
}
