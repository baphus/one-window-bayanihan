/**
 * clientDates — the single date vocabulary for client-facing surfaces
 * (OFW portal + public tracking portal).
 *
 * One en-PH formatter per shape so adjacent pages can never render the same
 * instant two ways again. All three degrade to an em dash on missing input
 * instead of "Invalid Date". Internal staff surfaces keep using
 * `lib/utils.ts` (en-US) — this module is client-only by design.
 *
 * AgencyMilestones keeps its own "02 Jan 2026 at 3:04 PM" shape: it is a
 * one-off composite used once, not a shared shape.
 */

/** "10 March 2026" — record headers, captions, waiting-since lines. */
export function formatClientLongDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

/** "10 Mar 2026" — compact meta rows (dashboard cards). */
export function formatClientShortDate(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/** "10 Mar 2026, 3:04 PM" — notification timestamps. */
export function formatClientDateTime(dateStr) {
    if (!dateStr) return '—';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}
