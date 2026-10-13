import { useMemo } from 'react';
import { getCaseAgeInDays } from '@/lib/utils';
import { AuditLogRow } from '@/lib/audit';
import UnifiedTimeline from '@/Components/Timeline';

/**
 * AuditLogTimeline — flat card-based activity feed for client detail pages.
 *
 * Thin wrapper over UnifiedTimeline: keeps the 50-entry slice and the audit
 * card styling, while ordering (newest first) and row rendering run through
 * the shared component.
 *
 * @param {Object}  props
 * @param {Array}   props.logs  - Audit log entries (server-limited, sliced to 50 client-side)
 * @param {Object|null} props.client - Full client object; used for case_file.case_number in metadata
 */
export default function AuditLogTimeline({ logs = [], client = null }) {
    const entries = useMemo(() => {
        return logs.slice(0, 50).map((log) => {
            const timestamp = log.timestamp;

            return {
                id: log.id,
                timestamp,
                source: log,
                caseNo: client?.caseFile?.case_number || null,
                daysSince: Math.max(1, getCaseAgeInDays(timestamp)),
            };
        });
    }, [logs, client]);

    return (
        <UnifiedTimeline
            variant="plain"
            items={entries}
            emptyTitle="No activity recorded yet."
            renderItem={({ item }) => {
                const extra = [
                    item.caseNo && `Case ${item.caseNo}`,
                    `${item.daysSince} day${item.daysSince > 1 ? 's' : ''}`,
                ].filter(Boolean);
                return (
                    <div className="border-b border-slate-100 pb-2.5 last:border-b-0 last:pb-0">
                        <AuditLogRow log={item.source} maxRows={3} variant="compact" extra={extra} />
                    </div>
                );
            }}
        />
    );
}
