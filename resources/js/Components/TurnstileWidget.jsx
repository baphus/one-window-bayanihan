import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Cloudflare Turnstile CAPTCHA widget (CDN-loaded, no npm package required).
 *
 * Reads `turnstile.enabled` and `turnstile.site_key` from Inertia shared props.
 * Returns null when disabled or when no site key is configured.
 *
 * @param {{ onToken: (token: string) => void, onExpire: () => void }} props
 */
export default function TurnstileWidget({ onToken, onExpire }) {
    const { turnstile } = usePage().props;
    const containerRef = useRef(null);
    const widgetIdRef = useRef(null);

    const enabled = turnstile?.enabled ?? false;
    const siteKey = turnstile?.site_key ?? '';

    useEffect(() => {
        if (!enabled || !siteKey) {
            return;
        }

        const SCRIPT_ID = 'cf-turnstile-script';
        const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

        const renderWidget = () => {
            if (!containerRef.current) return;
            if (widgetIdRef.current !== null) return;

            widgetIdRef.current = window.turnstile.render(containerRef.current, {
                sitekey: siteKey,
                callback: (token) => {
                    onToken?.(token);
                },
                'expired-callback': () => {
                    onExpire?.();
                },
                'error-callback': () => {
                    onExpire?.();
                },
            });
        };

        if (window.turnstile) {
            renderWidget();
            return;
        }

        if (!document.getElementById(SCRIPT_ID)) {
            const script = document.createElement('script');
            script.id = SCRIPT_ID;
            script.src = SCRIPT_SRC;
            script.async = true;
            script.defer = true;
            script.onload = renderWidget;
            document.head.appendChild(script);
        } else {
            // Script tag exists but turnstile not yet ready — poll briefly
            const poll = setInterval(() => {
                if (window.turnstile) {
                    clearInterval(poll);
                    renderWidget();
                }
            }, 100);
            return () => clearInterval(poll);
        }

        return () => {
            if (widgetIdRef.current !== null && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current);
                widgetIdRef.current = null;
            }
        };
    }, [enabled, siteKey]);

    if (!enabled || !siteKey) {
        return null;
    }

    return <div ref={containerRef} className="mt-2" />;
}
