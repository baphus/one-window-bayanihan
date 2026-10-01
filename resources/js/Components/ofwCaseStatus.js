/**
 * ofwCaseStatus — client-safe case vocabulary for the OFW dashboard cards.
 *
 * The dashboard receives RAW case statuses (DRAFT, OPEN, CLOSED, …) while the
 * case detail header speaks the client-safe sentence language
 * ("Under review…", "In progress — X of Y…", "has been resolved"). This
 * module maps the raw codes onto that same vocabulary so the card badge
 * never leaks title-cased internal lifecycle words ("Open", "Closed",
 * "Being Prepared") to workers.
 *
 * `lib/utils.ts:formatStatusLabel` is intentionally NOT touched — internal
 * pages depend on its raw behaviour.
 */

const DONE_STATUSES = ['CLOSED', 'COMPLETED', 'RESOLVED'];
const PROGRESS_STATUSES = ['OPEN', 'PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'IN_PROGRESS', 'BEING_PREPARED'];

/**
 * Coarse client-facing group for a case. Pure — exported for tests.
 * 'review' (still with the office) · 'progress' (moving) · 'done'
 * (resolved) · 'archived'. Unknown codes degrade to 'progress'.
 */
export function getOfwCaseGroup(status, source) {
    if (status === 'DRAFT') return 'review';
    if (status === 'ARCHIVED') return 'archived';
    if (DONE_STATUSES.includes(status)) return 'done';
    if (PROGRESS_STATUSES.includes(status)) return 'progress';
    // Unknown codes: assume the case is moving rather than stuck in review.
    return 'progress';
}

/** Client-safe badge label. Pure — exported for tests. */
export function getOfwCaseStatusLabel(status, source) {
    const group = getOfwCaseGroup(status, source);
    if (group === 'review') return 'Under Review';
    if (group === 'done') return 'Resolved';
    if (group === 'archived') return 'Archived';
    return 'In Progress';
}
