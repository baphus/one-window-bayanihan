import { useMemo } from 'react';
import { formatDisplayDateTime } from '@/lib/utils';
import { getActivityType, getEntityLabel } from '@/lib/audit';
import AuditLogCard from '@/Components/AuditLogCard';
import UnifiedTimeline from '@/Components/Timeline';

/**
 * Calculate days elapsed since a given timestamp.
 */
function daysAgo(timestamp) {
    const now = new Date();
    const then = new Date(timestamp);
    const diffMs = now.getTime() - then.getTime();
    return Math.max(1, Math.round(diffMs / (1000 * 60 * 60 * 24)));
}

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
            const moduleLabel = log.formatted_module || log.module;
            const timestamp = log.timestamp;

            return {
                id: log.id,
                timestamp,
                type: getActivityType(log.action, log.module),
                entityType: getEntityLabel(moduleLabel),
                details: log.message || '',
                changes: Array.isArray(log.changes) ? log.changes : [],
                actorName: log.actor || '',
                caseNo: client?.caseFile?.case_number || null,
                daysSince: daysAgo(timestamp),
            };
        });
    }, [logs, client]);

    return (
        <UnifiedTimeline
            variant="plain"
            items={entries}
            emptyTitle="No activity recorded yet."
            renderItem={({ item }) => (
                <AuditLogCard
                    type={item.type}
                    details={item.details}
                    changes={item.changes}
                    maxRows={3}
                    meta={[
                        item.caseNo && `Case ${item.caseNo}`,
                        item.entityType,
                        formatDisplayDateTime(item.timestamp),
                        item.actorName || 'System',
                        `${item.daysSince} day${item.daysSince > 1 ? 's' : ''}`,
                    ]}
                />
            )}
        />
    );
}
