import { ChangesTable } from '@/lib/audit';

/**
 * AuditLogCard — compact single-entry audit card shared by the client-detail
 * activity feed (AuditLogTimeline) and the case/referral audit modal
 * (AuditLogModal), so every audit surface renders identical entry markup.
 *
 * @param {string}   type       - Uppercase activity label (e.g. "CASE UPDATED")
 * @param {string}   [details]  - Human-readable narrative
 * @param {Array}    [changes]  - [{ field, fieldLabel, old, new }]
 * @param {string[]} [meta]     - Metadata segments joined with bullets
 * @param {number}   [maxRows]  - Max change rows shown (excess collapses)
 */
export default function AuditLogCard({ type, details = '', changes = [], meta = [], maxRows = 3 }) {
    const metaSegments = meta.filter(Boolean);

    return (
        <div className="rounded-[3px] border border-slate-200 bg-slate-50 p-3">
            {/* Activity type — uppercase blue badge */}
            <p className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-blue-900">
                {type}
            </p>

            {/* Details narrative */}
            {details && (
                <p className="mt-1 text-[12px] text-slate-700">{details}</p>
            )}

            {/* Changes table */}
            <ChangesTable changes={changes} variant="compact" maxRows={maxRows} />

            {/* Metadata line */}
            {metaSegments.length > 0 && (
                <p className="mt-1 text-[10px] text-slate-500">
                    {metaSegments.join(' \u2022 ')}
                </p>
            )}
        </div>
    );
}
