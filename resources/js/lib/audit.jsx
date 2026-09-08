import { formatDisplayDateTime } from '@/lib/utils';

/**
 * Shared audit-log presentation helpers.
 *
 * Single source of truth for how audit entries are rendered across every
 * surface (the AuditLog/Index timeline, the case/referral modal, and the
 * client-detail feed). Consolidates what were three drifting copies of
 * ACTION_STYLES, getActivityType() and ChangesList.
 *
 * Backend contract (App\Services\AuditLogFormatter::formatForAuditResponse):
 *   action           raw verb, e.g. "UPDATE"        (AuditAction value)
 *   module           raw module, e.g. "case"        (AuditModule value)
 *   formatted_module human label, e.g. "Case"
 *   message          human-readable description
 *   changes          [{ field, fieldLabel, new }]   (after-only, no `old`)
 *   actor            actor name (or "System")
 *   timestamp        ISO-8601 string
 *   hasChanges       boolean
 * The changes payload is deliberately after-only and carries the safe-response
 * posture: free-text fields never arrive; each `new` value is already a
 * display-formatted, controlled transformation (statuses, booleans, resolved
 * names, etc.).
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
 * Normalise a safe audit row (Inertia prop or API JSON) to the display shape
 * the components consume.
 */
export function normalizeAuditLog(log) {
    return {
        id: log.id,
        action: log.action,
        module: log.module,
        moduleLabel: log.formatted_module || log.module || '',
        message: log.message ?? '',
        changes: Array.isArray(log.changes) ? log.changes : [],
        actor: log.actor || 'System',
        timestamp: log.timestamp,
    };
}

/**
 * Field-level change list, shared by all audit surfaces.
 *
 * Each changed field renders as a label/value line. Deliberately NOT a
 * table — the audit timeline must not read as tabular data.
 *
 * @param {Array}  props.changes  [{ field, fieldLabel, new }]  (after-only)
 * @param {number} [props.maxRows] cap the visible rows; excess collapses to "+N more"
 * @param {'full'|'compact'} [props.variant='full']
 *        full    – bordered container (Index timeline)
 *        compact – borderless, tighter (AuditLogCard feeds)
 */
export function ChangesList({ changes, maxRows = null, variant = 'full' }) {
    if (!changes || changes.length === 0) return null;

    const visible = maxRows ? changes.slice(0, maxRows) : changes;
    const remaining = maxRows ? changes.length - maxRows : 0;

    return (
        <div className={variant === 'full'
            ? 'border border-slate-200 rounded-md overflow-hidden bg-white/60'
            : 'mt-2'}>
            <dl className={variant === 'full' ? 'divide-y divide-slate-100' : ''}>
                {visible.map((change, idx) => (
                    <div key={idx} className="flex gap-3 px-3 py-1.5">
                        <dt className="w-32 shrink-0 truncate text-[11px] font-medium capitalize text-slate-500">
                            {change.fieldLabel || change.field}
                        </dt>
                        <dd className="min-w-0 flex-1 break-words text-xs text-emerald-700">
                            {change.new ?? '—'}
                        </dd>
                    </div>
                ))}
            </dl>
            {remaining > 0 && (
                <p className={variant === 'full'
                    ? 'border-t border-slate-100 px-3 py-1.5 text-[11px] text-slate-400'
                    : 'px-1 pt-[3px] text-[10px] text-slate-400'}>
                    +{remaining} more
                </p>
            )}
        </div>
    );
}
