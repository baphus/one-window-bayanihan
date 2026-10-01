/**
 * ActionNeededBanner (B2) — the case-level "needs your action" banner for
 * client-facing surfaces (OFW portal + public tracking portal).
 *
 * Shown when at least one referral sits in FOR_COMPLIANCE (the office asked
 * for documents) or when the page already knows about an open client
 * request (`hasOpenRequest`). Links anchor-scroll to the blocking chapter
 * (`#agency-{referralId}`), which the detail pages always render with a
 * matching id and open by default for FOR_COMPLIANCE referrals.
 *
 * Only data the page already receives is consumed — no new queries.
 * No raw status codes are rendered; the vocabulary stays humanized.
 */

/** Referrals currently asking the client for documents. Pure — exported for tests. */
export function getBlockingReferrals(trackingAgencies = []) {
    if (!Array.isArray(trackingAgencies)) return [];
    return trackingAgencies.filter((agency) => agency?.status === 'FOR_COMPLIANCE');
}

/** Banner visibility rule. Pure — exported for tests. */
export function shouldShowActionBanner({ trackingAgencies = [], hasOpenRequest = false } = {}) {
    if (hasOpenRequest) return true;
    return getBlockingReferrals(trackingAgencies).length > 0;
}

export default function ActionNeededBanner({ agencies = [], hasOpenRequest = false }) {
    const blocking = getBlockingReferrals(agencies);
    if (!shouldShowActionBanner({ trackingAgencies: agencies, hasOpenRequest })) return null;

    const names = blocking.map((agency) => agency?.name).filter(Boolean);
    const nameList = names.length > 0 ? names.join(', ') : 'A partner office';

    return (
        <section
            role="status"
            aria-live="polite"
            className="mt-6 flex items-start gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3.5 shadow-sm"
        >
            <span aria-hidden="true" className="material-symbols-outlined mt-px shrink-0 text-[20px] text-amber-600">
                fact_check
            </span>
            <div className="min-w-0">
                <p className="text-sm font-bold text-amber-900">Action needed — documents requested</p>
                <p className="mt-0.5 text-[13px] leading-relaxed text-amber-800">
                    {blocking.length > 0
                        ? `${nameList} ${blocking.length === 1 ? 'needs' : 'need'} more documents to continue. Open the section below to see what is being asked.`
                        : 'You have an open request waiting for your reply. See the details below.'}
                </p>
                {blocking.length > 0 && (
                    <ul className="mt-2 space-y-1">
                        {blocking.map((agency) => (
                            <li key={agency?.referralId ?? agency?.name}>
                                {agency?.referralId ? (
                                    <a
                                        href={`#agency-${agency.referralId}`}
                                        className="inline-flex items-center gap-1 text-[13px] font-bold text-amber-900 underline decoration-amber-400 underline-offset-2 hover:text-amber-700"
                                    >
                                        <span aria-hidden="true" className="material-symbols-outlined text-[15px]">arrow_downward</span>
                                        Jump to {agency?.name ?? 'partner office'}
                                    </a>
                                ) : (
                                    <span className="text-[13px] font-bold text-amber-900">{agency?.name ?? 'Partner office'}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
