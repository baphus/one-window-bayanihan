/**
 * One-sentence, client-facing summary of where a request stands.
 *
 * The backend (`App\Services\TrackingService::caseStatusKey`) maps the stored
 * request status to this vocabulary:
 *
 *   OPEN           -> IN_PROGRESS
 *   PENDING_REVIEW -> BEING_REVIEWED
 *   CLOSED         -> RESOLVED
 *   ARCHIVED       -> ARCHIVED
 *   DRAFT          -> BEING_PREPARED
 *   anything else  -> UNKNOWN
 *
 * Both client-facing pages consume this module — the public tracking page
 * (Pages/Tracking/Show.jsx) and the signed-in OFW portal
 * (Pages/OFW/CaseDetail.jsx). They must never disagree about the same request.
 *
 * Copy rules: speak in the citizen's frame of reference, not the system's.
 * An unrecognized status is a quiet "we'll keep you posted", never an implied
 * outage — the portal is working fine, it simply does not know this value yet.
 */

/** Shown when the status is missing or not one this module recognizes. */
export const NEUTRAL_STATUS_LINE = 'Your request status will update as your request progresses.';

/**
 * Sentence fragment noting how many partner offices could not assist.
 *
 * @param {number} rejectedCount
 * @returns {string} Empty string when nothing was rejected, else a leading-space sentence.
 */
function rejectedNote(rejectedCount) {
    if (!rejectedCount || rejectedCount < 1) {
        return '';
    }
    const noun = rejectedCount === 1 ? 'office was' : 'offices were';
    return ` ${rejectedCount} ${noun} unable to assist.`;
}

/**
 * Build the status sentence shown in a request record header.
 *
 * @param {object} params
 * @param {string} [params.status] Client-facing status key from the backend.
 * @param {number} [params.totalAgencies] Partner offices attached to the request.
 * @param {number} [params.completedAgencies] Of those, how many finished.
 * @param {number} [params.rejectedCount] Partner offices unable to assist.
 * @returns {string} A complete sentence, never empty.
 */
export function buildCaseStatusLine({
    status,
    totalAgencies = 0,
    completedAgencies = 0,
    rejectedCount = 0,
} = {}) {
    const note = rejectedNote(rejectedCount);

    switch (status) {
        case 'RESOLVED':
            return `This request has been resolved.${note}`;

        case 'BEING_REVIEWED':
            return 'Your request is being reviewed by a case manager.';

        case 'BEING_PREPARED':
            return 'This request is still being prepared by the One Window Bayanihan team.';

        case 'ARCHIVED':
            return 'This request has been archived.';

        case 'IN_PROGRESS':
            // No referrals yet: the case manager has the request but has not sent
            // it to any partner office, so progress bars would be meaningless.
            if (totalAgencies === 0) {
                return 'Your request is being reviewed. Referrals to partner offices will appear here.';
            }
            return `In progress — ${completedAgencies} of ${totalAgencies} partner ${totalAgencies === 1 ? 'office has' : 'offices have'} completed their part.${note}`;

        default:
            return NEUTRAL_STATUS_LINE;
    }
}
