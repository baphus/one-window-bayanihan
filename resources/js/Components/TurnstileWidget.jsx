import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { TURNSTILE_STATUS } from '@/lib/turnstile';

/**
 * Cloudflare Turnstile CAPTCHA widget (CDN-loaded, no npm package required).
 *
 * Reads `turnstile.enabled` and `turnstile.site_key` from Inertia shared props.
 * Returns null when disabled or when no site key is configured.
 *
 * @param {{ onToken: (token: string) => void, onExpire: () => void, onStatusChange?: (status: string) => void, className?: string }} props
 */
export default function TurnstileWidget({ onToken, onExpire, onStatusChange, className = '' }) {
    const { turnstile } = usePage().props;
    const containerRef = useRef(null);
    const widgetIdRef = useRef(null);

    const onTokenRef = useRef(onToken);
    const onExpireRef = useRef(onExpire);
    const onStatusChangeRef = useRef(onStatusChange);
    useEffect(() => {
        onTokenRef.current = onToken;
        onExpireRef.current = onExpire;
        onStatusChangeRef.current = onStatusChange;
    });

    const enabled = turnstile?.enabled ?? false;
    const siteKey = turnstile?.site_key ?? '';

    const reportStatus = (status) => {
        onStatusChangeRef.current?.(status);
    };

    useEffect(() => {
        if (!enabled || !siteKey) {
            return;
        }

        reportStatus(TURNSTILE_STATUS.LOADING);

        const SCRIPT_ID = 'cf-turnstile-script';
        const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

        let poll = null;

        // Loose != null, deliberately. turnstile.render() returns UNDEFINED
        // when it cannot render (bad sitekey, container already occupied). A
        // strict !== null check treats that undefined as a live widget id and
        // hands turnstile.remove(undefined) to Cloudflare on unmount —
        // producing the very "Cannot find Widget" console error this component
        // was fixed to stop.
        const renderWidget = () => {
            if (!containerRef.current) return;
            if (widgetIdRef.current != null) return;

            widgetIdRef.current = window.turnstile.render(containerRef.current, {
                sitekey: siteKey,
                callback: (token) => {
                    reportStatus(TURNSTILE_STATUS.READY);
                    onTokenRef.current?.(token);
                },
                'expired-callback': () => {
                    reportStatus(TURNSTILE_STATUS.EXPIRED);
                    onExpireRef.current?.();
                },
                'error-callback': () => {
                    reportStatus(TURNSTILE_STATUS.ERROR);
                    onExpireRef.current?.();
                },
            });

            if (widgetIdRef.current != null) {
                reportStatus(TURNSTILE_STATUS.IDLE);
            } else {
                reportStatus(TURNSTILE_STATUS.ERROR);
                onExpireRef.current?.();
            }
        };

        if (window.turnstile) {
            renderWidget();
        } else if (!document.getElementById(SCRIPT_ID)) {
            const script = document.createElement('script');
            script.id = SCRIPT_ID;
            script.src = SCRIPT_SRC;
            script.async = true;
            script.defer = true;
            script.onload = renderWidget;
            document.head.appendChild(script);
        } else {
            // Script tag exists but turnstile not yet ready — poll briefly
            poll = setInterval(() => {
                if (window.turnstile) {
                    clearInterval(poll);
                    poll = null;
                    renderWidget();
                }
            }, 100);
        }

        // One cleanup for every branch above. Three of the four exit paths used
        // to return before reaching it, so a widget rendered against an
        // already-loaded API — the common case, since the script is cached
        // after the first mount — was never unregistered. Cloudflare then logs
        // "Cannot find Widget <id>, consider using turnstile.remove()" and the
        // orphan keeps its slot in the widget registry. EmailStep unmounts this
        // component every time `otpSent` flips, so it leaked on the happy path.
        return () => {
            if (poll !== null) {
                clearInterval(poll);
                poll = null;
            }

            if (widgetIdRef.current != null && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
            }

            // Cleared unconditionally, outside the window.turnstile check. If
            // the API object has gone (script removed, SPA teardown) there is
            // nothing to unregister, but keeping the id would make the next
            // effect run early-return at the guard above and latch the widget
            // off for good.
            widgetIdRef.current = null;
        };
    }, [enabled, siteKey]);

    if (!enabled || !siteKey) {
        return null;
    }

    return <div ref={containerRef} className={`mt-2 flex justify-center [&>iframe]:max-w-full ${className}`.trim()} />;
}
