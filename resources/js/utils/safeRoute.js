/**
 * Resolve a Ziggy named route with a plain-path fallback.
 *
 * Uses the global `route()` helper when Ziggy is ready; otherwise (tests,
 * early boot, missing route definition) returns the fallback path so links
 * and fetches keep working instead of throwing.
 *
 * @param {string} name Named route (e.g. 'cases.show').
 * @param {object|string|number|undefined} params Route params or query object.
 * @param {string} fallback Plain path used when Ziggy is unavailable.
 * @returns {string}
 */
export default function safeRoute(name, params, fallback) {
    try {
        if (typeof route === 'function') {
            return route(name, params);
        }
    } catch {
        // Ziggy not ready (tests / early boot) — fall through to plain path.
    }

    return fallback;
}
