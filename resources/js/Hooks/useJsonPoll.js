import { useCallback, useEffect, useState } from 'react';

export const JSON_HEADERS = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

/**
 * Fetch a JSON endpoint on mount and re-poll on an interval.
 * Powers the notification polling queries (list + unread count).
 * Returns { data, isLoading, error, reload }.
 */
export default function useJsonPoll(url, { interval = 60000 } = {}) {
    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);

    const reload = useCallback(async () => {
        try {
            const res = await fetch(url, { headers: JSON_HEADERS });
            if (!res.ok) throw new Error(`Failed: ${res.status}`);
            setData(await res.json());
            setError(null);
        } catch (err) {
            setError(err);
        } finally {
            setIsLoading(false);
        }
    }, [url]);

    useEffect(() => {
        // Re-enter loading when the URL changes so page switches show fresh
        // state instead of stale rows. Interval re-polls call reload()
        // directly and must not blank the list, so this lives here — not in
        // reload().
        setIsLoading(true);
        reload();
        const timer = setInterval(reload, interval);
        return () => clearInterval(timer);
    }, [reload, interval]);

    return { data, isLoading, error, reload };
}
