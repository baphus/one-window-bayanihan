import { formatDisplayDateTime } from '@/lib/utils';

/**
 * Shared audit-log presentation helpers.
 *
 * Single source of truth for how audit entries are rendered across every
 * surface (the AuditLog/Index timeline, the case/referral modal, and the
 * client-detail feed). Consolidates what were three drifting copies of
 * ACTION_STYLES, getActivityType() and ChangesTable.
 *
 * Backend contract (App\Services\AuditLogFormatter::formatForDisplay, attached
 * to each row by AuditLogController):
 *   action           raw verb, e.g. "UPDATE"        (AuditAction value)
 *   module           raw module, e.g. "case"        (AuditModule value)
 *   formatted_module human label, e.g. "Case"
 *   message          human-readable description
 *   changes          [{ field, fieldLabel, old, new }]
 *   actor            actor name (or "System")
 *   timestamp        ISO-8601 string
 *   hasChanges       boolean
 * There are NO camelCase `formatted*` keys — always read the snake_case
 * contract above (older code read `formattedModule` and silently fell back to
 * the raw lower-cased module string).
 */

export const ACTION_STYLES = {
    CREATE: { dot: 'bg-emerald-500', badge: 'bg-emerald-100 text-emerald-700', icon: 'add_circle' },
    UPDATE: { dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700', icon: 'edit' },
    DELETE: { dot: 'bg-red-500', badge: 'bg-red-100 text-red-700', icon: 'delete' },
    LOGIN: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'login' },
    LOGOUT: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'logout' },
    LOGIN_FAILED: { dot: 'bg-amber-500', badge: 'bg-amber-100 text-amber-800', icon: 'gpp_maybe' },
    EXPORT: { dot: 'bg-violet-500', badge: 'bg-violet-100 text-violet-700', icon: 'download' },
    PUBLISH: { dot: 'bg-emerald-500', badge: 'bg-emerald-100 text-emerald-700', icon: 'publish' },
    ARCHIVE: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'archive' },
    UNARCHIVE: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'unarchive' },
};

export const DEFAULT_ACTION_STYLE = { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'info' };

export function actionStyle(action) {
    return ACTION_STYLES[(action || '').toUpperCase()] || DEFAULT_ACTION_STYLE;
}

export const CATEGORY_LABELS = {
    security: 'Security',
    data: 'Data',
    admin: 'Admin',
    system: 'System',
};

/**
 * Map an action + module to a concise uppercase activity label.
 * Accepts either a raw module ("case_files") or a display label ("Case").
 */
export function getActivityType(action, module) {
    const act = (action || '').toUpperCase();
    const mod = (module || '').toUpperCase();

    // Action-only mappings
    if (act === 'LOGIN') return 'USER LOGIN';
    if (act === 'LOGOUT') return 'USER LOGOUT';
    if (act === 'LOGIN_FAILED') return 'SIGN-IN FAILED';
    if (act === 'EXPORT') return 'EXPORTED';
    if (act === 'PUBLISH') return 'PUBLISHED';
    if (act === 'ARCHIVE') return 'ARCHIVED';
    if (act === 'UNARCHIVE') return 'UNARCHIVED';

    if (['CASE', 'CASES', 'CASE_FILES'].includes(mod)) {
        if (act === 'CREATE') return 'CASE OPENED';
        if (act === 'UPDATE') return 'CASE UPDATED';
        if (act === 'DELETE') return 'CASE DELETED';
    }

    if (['REFERRAL', 'REFERRALS'].includes(mod)) {
        if (act === 'CREATE') return 'REFERRAL CREATED';
        if (act === 'UPDATE') return 'REFERRAL UPDATED';
        if (act === 'DELETE') return 'REFERRAL DELETED';
    }

    if (['CLIENT', 'CLIENTS'].includes(mod)) {
        if (act === 'CREATE') return 'CLIENT REGISTERED';
        if (act === 'UPDATE') return 'CLIENT UPDATED';
        if (act === 'DELETE') return 'CLIENT DELETED';
    }

    if (['MILESTONE', 'MILESTONES'].includes(mod)) {
        if (act === 'CREATE') return 'MILESTONE ACHIEVED';
        if (act === 'UPDATE') return 'MILESTONE UPDATED';
    }

    if (['USER', 'USERS'].includes(mod) && act === 'CREATE') {
        return 'USER REGISTERED';
    }

    return act || 'ACTIVITY';
}

/** Map a module (raw or label) to a concise entity-type noun. */
export function getEntityLabel(module) {
    const mod = (module || '').toUpperCase();

    if (['CASE', 'CASES', 'CASE_FILES'].includes(mod)) return 'Case';
    if (['REFERRAL', 'REFERRALS'].includes(mod)) return 'Referral';
    if (['CLIENT', 'CLIENTS'].includes(mod)) return 'Client';
    if (['MILESTONE', 'MILESTONES'].includes(mod)) return 'Milestone';
    if (['USER', 'USERS'].includes(mod)) return 'User';
    if (['AGENCY', 'AGENCIES'].includes(mod)) return 'Agency';

    return module || 'Record';
}

/**
 * Normalise a raw audit row (Inertia prop or API JSON) to the display shape
 * the components consume, reading the correct backend contract keys.
 */
export function normalizeAuditLog(log) {
    return {
        id: log.id,
        action: log.action,
        module: log.module,
        moduleLabel: log.formatted_module || log.module || '',
        message: log.message ?? log.description ?? '',
        changes: Array.isArray(log.changes) ? log.changes : [],
        actor: log.actor || log.user?.name || 'System',
        timestamp: log.timestamp,
    };
}

/**
 * Field-level changes table, shared by all audit surfaces.
 *
 * @param {Array}  props.changes  [{ field, fieldLabel, old, new }]
 * @param {number} [props.maxRows] cap the visible rows; excess collapses to "+N more"
 * @param {'full'|'compact'} [props.variant='full']
 *        full    – bordered, column headers, red/emerald old→new (Index timeline)
 *        compact – headerless, line-through old (modals, client feed)
 */
export function ChangesTable({ changes, maxRows = null, variant = 'full' }) {
    if (!changes || changes.length === 0) return null;

    const visible = maxRows ? changes.slice(0, maxRows) : changes;
    const remaining = maxRows ? changes.length - maxRows : 0;

    if (variant === 'compact') {
        return (
            <div className="mt-2 overflow-hidden rounded-[2px] border border-slate-200 bg-white/60">
                <table className="w-full border-collapse text-[11px] leading-tight">
                    <tbody>
                        {visible.map((change, idx) => (
                            <tr key={idx} className={idx > 0 ? 'border-t border-slate-100' : ''}>
                                <td className="min-w-[80px] max-w-[100px] truncate px-2 py-[3px] font-medium capitalize text-slate-500">
                                    {change.fieldLabel || change.field}
                                </td>
                                <td className="px-1 py-[3px] text-slate-400 line-through">
                                    {change.old ?? '—'}
                                </td>
                                <td className="px-1 py-[3px] text-slate-700">
                                    {change.new ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {remaining > 0 && (
                    <p className="border-t border-slate-100 px-2 py-[3px] text-[10px] text-slate-400">
                        +{remaining} more
                    </p>
                )}
            </div>
        );
    }

    return (
        <div className="border border-slate-200 rounded-md overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full text-xs text-slate-600">
                    <thead className="bg-slate-50 text-slate-700 uppercase text-[11px]">
                        <tr>
                            <th className="px-3 py-1.5 border-b border-slate-200 text-left">Field</th>
                            <th className="px-3 py-1.5 border-b border-slate-200 text-left">Old Value</th>
                            <th className="px-3 py-1.5 border-b border-slate-200 text-left">New Value</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {visible.map((c, i) => (
                            <tr key={i} className="hover:bg-slate-50">
                                <td className="px-3 py-1.5 text-[11px] font-medium capitalize text-slate-700">{c.fieldLabel || c.field}</td>
                                <td className="px-3 py-1.5 text-red-600 break-words max-w-[200px] bg-red-50/30">{c.old || '-'}</td>
                                <td className="px-3 py-1.5 text-emerald-600 break-words max-w-[200px] bg-emerald-50/30">{c.new || '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {remaining > 0 && (
                <p className="border-t border-slate-100 px-3 py-1.5 text-[11px] text-slate-400">
                    +{remaining} more
                </p>
            )}
        </div>
    );
}
