import * as Sentry from '@sentry/react';

/**
 * Frontend (browser) Sentry integration.
 *
 * Uses its own public DSN (VITE_SENTRY_DSN_PUBLIC) so the backend's secret-keyed
 * DSN never reaches the browser bundle. The same release name is used as the
 * backend so issues can be cross-referenced.
 *
 * Only initializes when the environment explicitly opts in; otherwise this is a
 * no-op and the app behaves exactly as it did before.
 */
export function initSentry(): void {
    const dsn = import.meta.env.VITE_SENTRY_DSN_PUBLIC;

    if (!dsn) {
        return;
    }

    const release = import.meta.env.VITE_SENTRY_RELEASE;
    const environment = import.meta.env.VITE_APP_ENV || 'development';

    Sentry.init({
        dsn,
        release: release || undefined,
        environment,
        // Browser render errors are the goal here; a modest sample keeps cost down.
        tracesSampleRate: 0.2,
        // Only send reported events, never raw user input or case data.
        sendDefaultPii: false,
        beforeSend(event) {
            if (event.request?.url) {
                try {
                    const parsed = new URL(event.request.url);
                    parsed.search = '';
                    parsed.hash = '';
                    event.request.url = parsed.toString();
                } catch {
                    // Leave the URL untouched if it is not parseable.
                }
            }
            return event;
        },
    });

    // Link browser errors to the authenticated user and the backend request they
    // came from, mirroring the tags the backend attaches.
    const requestId = import.meta.env.VITE_INITIAL_REQUEST_ID;
    if (requestId) {
        Sentry.setTag('request_id', requestId);
    }
}

/**
 * Read the backend request ID for the current Inertia page, if one was shared.
 */
export function pageRequestId(requestId: unknown): string | undefined {
    return typeof requestId === 'string' && requestId.length > 0 ? requestId : undefined;
}
