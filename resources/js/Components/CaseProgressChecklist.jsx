import ReferralStamp, { getReferralStatusLabel } from './ReferralStamp';
import { formatClientLongDate as defaultFormatLongDate } from './clientDates';

/**
 * OfficeChecklist (B1) — the whole-case progress view for client-facing
 * surfaces (OFW portal + public tracking portal).
 *
 * One row per referral: office name + humanized stamp + a "waiting since"
 * line where dates exist, then the existing "% of processing complete"
 * caption and a one-line legend. No time axis, no convergence concept, no
 * raw codes rendered — it reads cleanly at 360px with zero interaction.
 *
 * "Waiting since" prefers the referral's `sentAt` from the
 * `clientSwimlaneTimeline` prop (the truthful send instant, code-free by
 * backend design) and falls back to the earliest timeline event recorded
 * for that referral when the prop is absent or a row is unmatched. Rows
 * without derivable dates simply omit the line — no crash, no layout
 * shift.
 */

const ACTIVE_STATUSES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

function eventTimeMs(item) {
    const raw = item?.date ?? item?.timestamp ?? item?.created_at ?? item?.createdAt;
    if (!raw) return NaN;
    return new Date(raw).getTime();
}

function toIsoOrNull(value) {
    if (!value) return null;
    const time = new Date(value).getTime();
    return Number.isNaN(time) ? null : new Date(time).toISOString();
}

/**
 * Index swimlane `sentAt` instants by agency name.
 *
 * Join key: agency NAME — the only key present in both payloads. Agency
 * cards carry `referralId` but swimlane lanes are id-free by backend design
 * (code-free shape), so there is no stronger key. Each name maps to a queue
 * consumed in order, so a repeated office name pairs off stably instead of
 * handing every duplicate row the same instant. Only rows that need a date
 * (active referrals) consume from the queue.
 */
export function indexSwimlaneSentAt(clientSwimlaneTimeline) {
    const queues = new Map();
    const lanes = clientSwimlaneTimeline?.referrals;
    if (!Array.isArray(lanes)) return queues;
    for (const lane of lanes) {
        if (!lane?.agency) continue;
        const iso = toIsoOrNull(lane.sentAt);
        if (!iso) continue;
        if (!queues.has(lane.agency)) queues.set(lane.agency, []);
        queues.get(lane.agency).push(iso);
    }
    return queues;
}

/**
 * Earliest timeline-event instant for one referral, as an ISO string.
 * Returns null when no dated event exists for the referral.
 */
export function getWaitingSinceIso(referralId, milestoneTimeline = []) {
    if (!referralId || !Array.isArray(milestoneTimeline)) return null;
    let earliest = null;
    for (const item of milestoneTimeline) {
        if (item?.referralId !== referralId) continue;
        const time = eventTimeMs(item);
        if (Number.isNaN(time)) continue;
        if (earliest === null || time < earliest) earliest = time;
    }
    return earliest === null ? null : new Date(earliest).toISOString();
}

/**
 * Derive one checklist row per agency card. Pure — exported for tests.
 * `waitingSinceIso` is set only for active (non-terminal) referrals with
 * derivable dates — swimlane `sentAt` first, earliest timeline event as
 * fallback; terminal referrals (COMPLETED/REJECTED) simply end.
 */
export function buildChecklistRows(trackingAgencies = [], milestoneTimeline = [], clientSwimlaneTimeline = null) {
    if (!Array.isArray(trackingAgencies)) return [];
    const sentAtByAgency = indexSwimlaneSentAt(clientSwimlaneTimeline);
    return trackingAgencies.map((agency) => {
        let waitingSinceIso = null;
        if (ACTIVE_STATUSES.includes(agency?.status)) {
            const queue = sentAtByAgency.get(agency?.name);
            const sentAt = queue?.length ? queue.shift() : null;
            waitingSinceIso = sentAt ?? getWaitingSinceIso(agency?.referralId, milestoneTimeline);
        }
        return {
            referralId: agency?.referralId ?? null,
            name: agency?.name ?? 'Partner office',
            status: agency?.status ?? 'PENDING',
            statusLabel: getReferralStatusLabel(agency?.status),
            waitingSinceIso,
        };
    });
}

export default function OfficeChecklist({
    agencies = [],
    milestoneTimeline = [],
    clientSwimlaneTimeline = null,
    caption = null,
    formatDate = defaultFormatLongDate,
}) {
    const rows = buildChecklistRows(agencies, milestoneTimeline, clientSwimlaneTimeline);
    if (rows.length === 0) return null;

    const summary = rows
        .map((row) => `${row.name}: ${row.statusLabel}`)
        .join('; ');

    return (
        <div className="mt-5">
            <ul aria-label="Progress by office" className="space-y-2">
                {rows.map((row) => (
                    <li
                        key={row.referralId ?? row.name}
                        className="flex items-start justify-between gap-3 rounded border border-white/25 bg-white/10 px-3 py-2"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-[13px] font-bold leading-snug text-white" title={row.name}>
                                {row.name}
                            </p>
                            {row.waitingSinceIso && (
                                <p className="mt-0.5 text-[11px] text-primary-fixed-dim/80">
                                    Waiting since {formatDate(row.waitingSinceIso)}
                                </p>
                            )}
                        </div>
                        <span className="shrink-0 border border-white/40 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white">
                            {row.statusLabel}
                        </span>
                    </li>
                ))}
            </ul>
            <p className="sr-only">{summary}</p>
            <p className="mt-2 text-[11px] text-primary-fixed-dim/80">
                One row per office — statuses update as offices act.
            </p>
            {caption && (
                <p className="mt-1 text-[11px] text-primary-fixed-dim/80">
                    {caption}
                </p>
            )}
        </div>
    );
}

// Re-exported so chapter headers share the exact chip without importing twice.
export { ReferralStamp };
