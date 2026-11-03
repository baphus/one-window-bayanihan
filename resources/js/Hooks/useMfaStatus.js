import { useCallback, useEffect, useState } from 'react';
import { isFreshMfa } from '@/lib/mfa';

/**
 * Reads profile.mfa.status so pages can show required/optional policy and
 * skip re-prompting while the five-minute fresh-MFA window is open.
 */
export default function useMfaStatus() {
    const [status, setStatus] = useState(null);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        if (typeof fetch !== 'function' || typeof route !== 'function') {
            setLoading(false);
            return;
        }

        try {
            const response = await fetch(route('profile.mfa.status'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) setStatus(await response.json());
        } catch {
            // Leave the last known state in place; callers prompt for proof.
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        refresh();
    }, [refresh]);

    return {
        status,
        loading,
        refresh,
        isFresh: isFreshMfa(status?.confirmed_until),
    };
}
