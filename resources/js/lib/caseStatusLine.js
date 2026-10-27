/**
 * One-sentence, client-facing summary of where a case stands.
 *
 * The backend (`App\Services\TrackingService::caseStatusKey`) maps the stored
 * case status to this vocabulary:
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
 * (Pages/OFW/CaseDetail.jsx). They must never disagree about the same case,
 * which is exactly what happened when each page kept its own copy of this
 * switch: a case in PENDING_REVIEW read "being reviewed" on the public page
 * and "Case status is currently unavailable" on the OFW page, because the
 * OFW copy was missing the BEING_REVIEWED branch.
 *
 * Copy rules: speak in the citizen's frame of reference, not the system's.
 * An unrecognized status is a quiet "we'll keep you posted", never an implied
 * outage — the portal is working fine, it simply does not know this value yet.
 */

/** Shown when the status is missing or not one this module recognizes. */
export const NEUTRAL_STATUS_LINE = 'Your case status will update as your case progresses.';

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
 * Build the status sentence shown in a case record header.
 *
 * @param {object} params
 * @param {string} [params.status] Client-facing status key from the backend.
 * @param {number} [params.totalAgencies] Partner offices attached to the case.
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
            return `This case has been resolved.${note}`;

        case 'BEING_REVIEWED':
            return 'Your case is being reviewed by a case manager.';

        case 'BEING_PREPARED':
            return 'This case is still being prepared by the One Window Bayanihan team.';

        case 'ARCHIVED':
            return 'This case has been archived.';

        case 'IN_PROGRESS':
            // No referrals yet: the case manager has the case but has not sent
            // it to any partner office, so progress bars would be meaningless.
            if (totalAgencies === 0) {
                return 'Your case is being reviewed. Referrals to partner offices will appear here.';
            }
            return `In progress — ${completedAgencies} of ${totalAgencies} partner ${totalAgencies === 1 ? 'office has' : 'offices have'} completed their part.${note}`;

        default:
            return NEUTRAL_STATUS_LINE;
    }
}
