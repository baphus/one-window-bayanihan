/**
 * ReferralStamp — the single client-safe vocabulary for referral states.
 *
 * Every client-facing surface (OFW portal + public tracking portal) renders
 * referral status through this module so the five referral states can never
 * drift apart again. Internal codes (PENDING, PROCESSING, FOR_COMPLIANCE,
 * COMPLETED, REJECTED) are branch keys only — what the eye sees is always
 * one of the humanized labels below.
 */

export const REFERRAL_STATUS_LABELS = {
    PENDING: 'Awaiting receipt',
    PROCESSING: 'In process',
    FOR_COMPLIANCE: 'Needs documents',
    COMPLETED: 'Completed',
    REJECTED: 'Unable to assist',
};

export const REFERRAL_STAMP = {
    PENDING: { label: 'Awaiting receipt', border: 'border-slate-300', text: 'text-slate-500' },
    PROCESSING: { label: 'In process', border: 'border-primary', text: 'text-primary' },
    FOR_COMPLIANCE: { label: 'Needs documents', border: 'border-amber-500', text: 'text-amber-600' },
    COMPLETED: { label: 'Completed', border: 'border-emerald-500', text: 'text-emerald-600' },
    REJECTED: { label: 'Unable to assist', border: 'border-red-400', text: 'text-red-500' },
};

/** Humanized label for a raw referral status. Unknown codes fall back to Pending. */
export function getReferralStatusLabel(status) {
    return REFERRAL_STATUS_LABELS[status] ?? REFERRAL_STATUS_LABELS.PENDING;
}

/** Stamp (label + chip classes) for a raw referral status. Unknown codes fall back to Pending. */
export function getReferralStamp(status) {
    return REFERRAL_STAMP[status] ?? REFERRAL_STAMP.PENDING;
}

/**
 * The bordered status stamp rendered on agency chapters and checklist rows.
 * Colour is never the only carrier — the humanized label is always present.
 */
export default function ReferralStamp({ status, className = '' }) {
    const stamp = getReferralStamp(status);

    return (
        <span className={`shrink-0 border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] ${stamp.border} ${stamp.text} ${className}`}>
            {stamp.label}
        </span>
    );
}
