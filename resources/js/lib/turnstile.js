/**
 * Turnstile client-side status vocabulary and user-facing messages.
 * A token is only available once the user completes the challenge; until then
 * the widget is loading, idle, or has hit an error — each needs a distinct message.
 */

export const TURNSTILE_STATUS = Object.freeze({
    LOADING: 'loading',   // script/widget still loading
    IDLE: 'idle',         // rendered, waiting for the user to complete the check
    READY: 'ready',       // a valid token is in hand
    EXPIRED: 'expired',   // token expired; widget is re-challenging
    ERROR: 'error',       // widget could not verify (network/JS blocked)
});

export const TURNSTILE_MESSAGES = Object.freeze({
    loading: 'The security check is still loading. Please wait a moment.',
    idle: 'Please complete the security check to continue.',
    expired: 'Your security check expired. Please complete it again.',
    error: 'Unable to load the security check. Please refresh the page and try again.',
});

/**
 * Returns a validation error message when turnstile is required but not ready,
 * or null when a token is already present.
 *
 * @param {{ token: string, status?: string }} opts
 * @returns {string | null}
 */
export function getTurnstileError({ token, status = TURNSTILE_STATUS.IDLE }) {
    if (token) return null;
    return TURNSTILE_MESSAGES[status] || TURNSTILE_MESSAGES[TURNSTILE_STATUS.IDLE];
}