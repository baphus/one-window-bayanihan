import { useMemo, useState } from 'react';
import { formatRelativeTime, formatDateGroup } from '@/lib/relativeTime';
import { formatDisplayDateTime } from '@/lib/utils';

/**
 * Human-friendly field name mapping (mirrors PHP AuditLogFormatter::formatFieldName).
 */
const FIELD_LABELS = {
    case_number: 'Case Number',
    tracker_number: 'Tracker Number',
    first_name: 'First Name',
    last_name: 'Last Name',
    middle_name: 'Middle Name',
    name: 'Name',
    email: 'Email Address',
    role: 'Role',
    status: 'Status',
    required_services: 'Service Type',
    description: 'Description',
    title: 'Title',
    summary: 'Summary',
    notes: 'Notes',
    is_deleted: 'Deleted',
    is_active: 'Active',
    is_verified: 'Verified',
    deleted_at: 'Deletion Date',
    agcy_id: 'Agency',
    user_id: 'Assigned User',
    client_type: 'Client Type',
    sex: 'Gender',
    date_of_birth: 'Date of Birth',
    phone_number: 'Phone Number',
    contact_number: 'Contact Number',
    address: 'Address',
    last_country: 'Last Country of Work',
    last_position: 'Last Position',
    country: 'Country',
    position: 'Position',
    street: 'Street Address',
    city_municipality: 'City/Municipality',
    province: 'Province',
    barangay: 'Barangay',
    region: 'Region',
    password: 'Password',
    remember_token: 'Session Token',
    vulnerability_indicator: 'Vulnerability Level',
    consent_given_at: 'Consent Date',
    employer_name: 'Employer',
    start_date: 'Start Date',
    end_date: 'End Date',
    date_of_arrival: 'Date of Arrival',
    suffix: 'Suffix',
    avatar_url: 'Profile Picture',
    category_id: 'Category',
    client_id: 'Client',
    draft_client_data: 'Draft Data',
    relationship: 'Relationship',
    full_address: 'Full Address',
    middle_initial: 'Middle Initial',
    is_primary: 'Primary Contact',
};

const NOISE_FIELDS = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'email_verified_at', 'timestamp', 'remember_token'];

function humanizeFieldName(field) {
    return FIELD_LABELS[field] || field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function humanizeValue(field, value) {
    if (value === null || value === undefined || value === '') return 'Not set';
    if (typeof value === 'boolean' || value === 1 || value === 0 || value === '1' || value === '0') {
        return [true, 1, '1'].includes(value) ? 'Yes' : 'No';
    }
    if (typeof value === 'object') return JSON.stringify(value);

    const str = String(value);
    const upper = str.toUpperCase();

    // Status values
    const statusMap = { OPEN: 'Open', CLOSED: 'Closed', DRAFT: 'Draft', ARCHIVED: 'Archived', PENDING: 'Pending', PROCESSING: 'Processing', COMPLETED: 'Completed', REJECTED: 'Rejected', FOR_COMPLIANCE: 'For Compliance' };
    if (statusMap[upper]) return statusMap[upper];

    // Role values
    if (field === 'role') {
        const roleMap = { CASE_MANAGER: 'Case Manager', AGENCY: 'Agency Focal', ADMIN: 'System Admin' };
        return roleMap[upper] || str;
    }

    // Client type
    if (field === 'client_type') {
        if (upper === 'OFW') return 'OFW';
        if (upper === 'NEXT_OF_KIN') return 'Next of Kin';
    }

    // Password — never show actual value
    if (field === 'password') return '••••••••';

    return str;
}

/**
 * Module labels for display.
 */
const MODULE_LABELS = {
    case_files: 'Case',
    cases: 'Case',
    clients: 'Client',
    client_addresses: 'Address',
    client_employments: 'Employment',
    referrals: 'Referral',
    milestones: 'Milestone',
    referral_attachments: 'Attachment',
    agencies: 'Agency',
    users: 'User',
    services: 'Service',
    helpdesk_articles: 'Article',
};

function getModuleLabel(module) {
    return MODULE_LABELS[module] || module?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Record';
}

/**
 * Action color + ring colors matching the client page timeline.
 */
const ACTION_COLORS = {
    CREATE: { dot: 'bg-emerald-500', ring: 'ring-emerald-100', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    UPDATE: { dot: 'bg-blue-500', ring: 'ring-blue-100', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    DELETE: { dot: 'bg-red-500', ring: 'ring-red-100', badge: 'bg-red-50 text-red-700 border-red-200' },
    LOGIN: { dot: 'bg-slate-400', ring: 'ring-slate-100', badge: 'bg-slate-50 text-slate-600 border-slate-200' },
    LOGOUT: { dot: 'bg-slate-400', ring: 'ring-slate-100', badge: 'bg-slate-50 text-slate-600 border-slate-200' },
    PUBLISH: { dot: 'bg-indigo-500', ring: 'ring-indigo-100', badge: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    ARCHIVE: { dot: 'bg-amber-500', ring: 'ring-amber-100', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    UNARCHIVE: { dot: 'bg-amber-500', ring: 'ring-amber-100', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
};

function getActionStyle(action) {
    return ACTION_COLORS[action] || { dot: 'bg-slate-400', ring: 'ring-slate-100', badge: 'bg-slate-50 text-slate-600 border-slate-200' };
}

/**
 * Human-friendly action labels.
 */
function getActionLabel(action) {
    const map = { CREATE: 'Created', UPDATE: 'Updated', DELETE: 'Deleted', LOGIN: 'Signed In', LOGOUT: 'Signed Out', PUBLISH: 'Published', ARCHIVE: 'Archived', UNARCHIVE: 'Unarchived' };
    return map[action] || action;
}

/**
 * Module-specific icon SVGs.
 */
function ModuleIcon({ module }) {
    const mod = (module || '').toLowerCase();

    if (['case_files', 'cases'].includes(mod)) {
        return (
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
            </svg>
        );
    }
    if (mod === 'referrals') {
        return (
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M7 17l9.2-9.2M17 17V7H7" />
            </svg>
        );
    }
    if (mod === 'clients') {
        return (
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
            </svg>
        );
    }
    if (mod === 'milestones') {
        return (
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="9 11 12 14 22 4" />
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
        );
    }
    if (mod === 'users') {
        return (
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="8.5" cy="7" r="4" />
                <line x1="20" y1="8" x2="20" y2="14" />
                <line x1="23" y1="11" x2="17" y2="11" />
            </svg>
        );
    }
    if (mod === 'agencies') {
        return (
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
            </svg>
        );
    }
    // Default: clock
    return (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
    );
}

/**
 * @param {Object} props
 * @param {Array} props.logs - Array of log objects
 * @param {boolean} [props.showFilters=true]
 * @param {Function} [props.onFilterChange]
 * @param {string[]} [props.availableActions=[]]
 * @param {Object[]} [props.availableModules=[]]
 * @param {Object} [props.availableModulesLabels={}]
 * @param {Object} [props.filterValues={}]
 * @param {Object} [props.pagination]
 * @param {Function} [props.onPageChange]
 */
export function AuditTimeline({
    logs = [],
    showFilters = true,
    onFilterChange = () => {},
    availableActions = [],
    availableModules = [],
    availableModulesLabels = {},
    filterValues = {},
    pagination,
    onPageChange = () => {},
}) {
    const groupedLogs = useMemo(() => {
        const groups = {};
        const groupOrder = [];

        logs.forEach(log => {
            const groupLabel = formatDateGroup(log.timestamp);
            if (!groups[groupLabel]) {
                groups[groupLabel] = [];
                groupOrder.push(groupLabel);
            }
            groups[groupLabel].push(log);
        });

        return groupOrder.map(label => ({
            label,
            logs: groups[label],
        }));
    }, [logs]);

    const hasFiltersActive = Object.keys(filterValues).some(k =>
        Array.isArray(filterValues[k]) ? filterValues[k].length > 0 : !!filterValues[k]
    );

    return (
        <div className="w-full">
            {showFilters && (
                <FilterBar
                    availableActions={availableActions}
                    availableModules={availableModules}
                    availableModulesLabels={availableModulesLabels}
                    filterValues={filterValues}
                    onFilterChange={onFilterChange}
                />
            )}

            {logs.length > 0 ? (
                <div className="space-y-6">
                    {groupedLogs.map(group => (
                        <div key={group.label}>
                            {/* Date group header */}
                            <div className="flex items-center gap-3 mb-4">
                                <span className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-400">
                                    {group.label}
                                </span>
                                <div className="flex-1 h-px bg-slate-200" />
                                <span className="text-[10px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                                    {group.logs.length} {group.logs.length === 1 ? 'event' : 'events'}
                                </span>
                            </div>

                            {/* Timeline */}
                            <div className="relative ml-4">
                                {/* Vertical line */}
                                <div className="absolute left-[9px] top-3 bottom-3 w-px bg-slate-200" />

                                <div className="space-y-0">
                                    {group.logs.map((log) => (
                                        <TimelineEntry key={log.id} log={log} />
                                    ))}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rounded-[3px] border border-dashed border-slate-300 bg-slate-50/50 py-12 px-6 text-center">
                    <svg className="mx-auto mb-3 h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <h3 className="text-sm font-semibold text-slate-700 mb-1">No activity found</h3>
                    <p className="text-[12px] text-slate-500 mb-4">Try adjusting your filters or check back later.</p>

                    {hasFiltersActive && (
                        <button
                            onClick={() => onFilterChange({})}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-[3px] text-[11px] font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            Clear Filters
                        </button>
                    )}
                </div>
            )}

            {pagination && <Pagination pagination={pagination} onPageChange={onPageChange} />}
        </div>
    );
}

function TimelineEntry({ log }) {
    const [expanded, setExpanded] = useState(false);
    const style = getActionStyle(log.action);
    const actorName = log.actor || log.user?.name || 'System';
    const displayMessage = log.message ?? log.description ?? '';
    const displayDetail = log.detail;
    const moduleLabel = getModuleLabel(log.module);

    // Parse changes for expandable diff
    const changes = useMemo(() => {
        if (!log.old_value && !log.new_value) return [];
        let oldVals = {};
        let newVals = {};
        try {
            oldVals = typeof log.old_value === 'string' ? JSON.parse(log.old_value) : (log.old_value || {});
            newVals = typeof log.new_value === 'string' ? JSON.parse(log.new_value) : (log.new_value || {});
        } catch (e) {
            return [];
        }

        const allKeys = Array.from(new Set([...Object.keys(oldVals), ...Object.keys(newVals)]));
        return allKeys
            .filter(key => !NOISE_FIELDS.includes(key))
            .map(key => {
                const oldV = oldVals[key];
                const newV = newVals[key];
                if (oldV == newV) return null;
                return { field: key, oldValue: oldV, newValue: newV };
            })
            .filter(Boolean);
    }, [log.old_value, log.new_value]);

    const hasExpandableChanges = changes.length > 0 && log.action === 'UPDATE';

    return (
        <div className="relative flex gap-4 pb-5 last:pb-0">
            {/* Timeline node */}
            <div className="relative z-10 flex-shrink-0 mt-1">
                <div className={`h-[19px] w-[19px] rounded-full ${style.dot} ring-[3px] ${style.ring} flex items-center justify-center`}>
                    <div className="h-[7px] w-[7px] rounded-full bg-white" />
                </div>
            </div>

            {/* Content card */}
            <div className="flex-1 min-w-0 rounded-[3px] border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition-shadow">
                {/* Header row */}
                <div className="flex items-start justify-between gap-3 mb-2">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        <span className="text-slate-400 flex-shrink-0">
                            <ModuleIcon module={log.module} />
                        </span>
                        <span className="text-[13px] font-semibold text-slate-900 leading-snug">
                            {displayMessage}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded-[3px] text-[10px] font-bold uppercase tracking-wide border ${style.badge}`}>
                            {getActionLabel(log.action)}
                        </span>
                    </div>
                </div>

                {/* Detail text — full, no clamp */}
                {displayDetail && (
                    <p className="text-[12px] text-slate-600 leading-relaxed mb-2 ml-6">
                        {displayDetail}
                    </p>
                )}

                {/* Metadata row */}
                <div className="flex items-center gap-2 text-[11px] text-slate-400 ml-6">
                    <span className="font-medium text-slate-500">{actorName}</span>
                    <span>&middot;</span>
                    <span>{moduleLabel}</span>
                    <span>&middot;</span>
                    <span>{formatRelativeTime(log.timestamp)}</span>
                    <span>&middot;</span>
                    <span>{formatDisplayDateTime(log.timestamp)}</span>
                </div>

                {/* Expandable changes */}
                {hasExpandableChanges && (
                    <div className="mt-3 ml-6">
                        <button
                            onClick={() => setExpanded(!expanded)}
                            className="text-[11px] text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors"
                        >
                            <svg
                                className={`h-3.5 w-3.5 transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`}
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                            >
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                            {expanded ? 'Hide details' : `View ${changes.length} ${changes.length === 1 ? 'change' : 'changes'}`}
                        </button>

                        <div className={`overflow-hidden transition-all duration-300 ease-in-out ${expanded ? 'max-h-[600px] mt-2 opacity-100' : 'max-h-0 opacity-0'}`}>
                            <div className="border border-slate-200 rounded-[3px] overflow-hidden">
                                <table className="w-full text-left text-[11px]">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-3 py-2 font-semibold text-slate-600 border-b border-slate-200 w-1/4">Field</th>
                                            <th className="px-3 py-2 font-semibold text-slate-600 border-b border-slate-200 w-[37.5%]">Previous</th>
                                            <th className="px-3 py-2 font-semibold text-slate-600 border-b border-slate-200 w-[37.5%]">Updated To</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {changes.map(({ field, oldValue, newValue }) => (
                                            <tr key={field} className="hover:bg-slate-50/50">
                                                <td className="px-3 py-2 font-medium text-slate-700">{humanizeFieldName(field)}</td>
                                                <td className="px-3 py-2 text-red-600 bg-red-50/30 break-words">{humanizeValue(field, oldValue)}</td>
                                                <td className="px-3 py-2 text-emerald-600 bg-emerald-50/30 break-words">{humanizeValue(field, newValue)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function FilterBar({ availableActions, availableModules, availableModulesLabels, filterValues, onFilterChange }) {
    const handleActionToggle = (action) => {
        const currentActions = (filterValues.action || '').split(',').filter(Boolean);
        const newActions = currentActions.includes(action)
            ? currentActions.filter(a => a !== action)
            : [...currentActions, action];
        onFilterChange({ ...filterValues, action: newActions.join(',') });
    };

    const handleModuleChange = (e) => {
        onFilterChange({ ...filterValues, module: e.target.value });
    };

    const handleSearchChange = (e) => {
        onFilterChange({ ...filterValues, search: e.target.value });
    };

    const handleDateChange = (field, value) => {
        onFilterChange({ ...filterValues, [field]: value });
    };

    const clearFilters = () => {
        onFilterChange({});
    };

    const hasFilters = Object.keys(filterValues).some(k =>
        (Array.isArray(filterValues[k]) ? filterValues[k].length > 0 : !!filterValues[k])
    );

    return (
        <div className="bg-white p-4 rounded-[3px] border border-slate-200 shadow-sm mb-6 space-y-4">
            <div className="flex flex-wrap gap-4 items-center justify-between">
                {/* Search */}
                <div className="relative flex-grow max-w-sm">
                    <svg className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input
                        type="text"
                        placeholder="Search activity logs..."
                        value={filterValues.search || ''}
                        onChange={handleSearchChange}
                        className="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-[3px] text-sm focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div className="flex flex-wrap gap-3 items-center">
                    {/* Module Select */}
                    <select
                        value={filterValues.module || ''}
                        onChange={handleModuleChange}
                        className="py-2 pl-3 pr-8 border border-slate-300 rounded-[3px] text-sm focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                        <option value="">All Modules</option>
                        {availableModules.map(m => (
                            <option key={m} value={m}>{availableModulesLabels[m] || m}</option>
                        ))}
                    </select>

                    {/* Date Range */}
                    <div className="flex items-center gap-2">
                        <input
                            type="date"
                            value={filterValues.date_from || ''}
                            onChange={(e) => handleDateChange('date_from', e.target.value)}
                            className="py-2 px-3 border border-slate-300 rounded-[3px] text-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                        <span className="text-slate-500 text-sm">to</span>
                        <input
                            type="date"
                            value={filterValues.date_to || ''}
                            onChange={(e) => handleDateChange('date_to', e.target.value)}
                            className="py-2 px-3 border border-slate-300 rounded-[3px] text-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>
            </div>

            {/* Actions multi-select */}
            <div className="flex flex-wrap gap-2 items-center">
                <span className="text-[11px] font-semibold text-slate-600 mr-2">Filter by action:</span>
                {availableActions.map(action => {
                    const isActive = (filterValues.action || '').split(',').includes(action);
                    const actionStyle = getActionStyle(action);
                    return (
                        <button
                            key={action}
                            onClick={() => handleActionToggle(action)}
                            className={`px-2.5 py-1 rounded-[3px] text-[10px] font-bold uppercase tracking-wide border transition-colors ${
                                isActive
                                    ? actionStyle.badge
                                    : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'
                            }`}
                        >
                            {getActionLabel(action)}
                        </button>
                    );
                })}
            </div>

            {/* Clear filters */}
            {hasFilters && (
                <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span className="text-[10px] text-slate-400 font-medium">Filters active</span>
                    <button
                        onClick={clearFilters}
                        className="text-[11px] text-red-600 hover:text-red-800 font-medium"
                    >
                        Clear All
                    </button>
                </div>
            )}
        </div>
    );
}

function Pagination({ pagination, onPageChange }) {
    if (!pagination || pagination.totalPages <= 1) return null;

    const { currentPage, totalPages, total } = pagination;

    let pages = [];
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    return (
        <div className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 rounded-[3px] shadow-sm mt-6">
            <p className="text-[12px] text-slate-600">
                <span className="font-semibold">{total}</span> total results
            </p>
            <nav className="inline-flex -space-x-px rounded-[3px] shadow-sm" aria-label="Pagination">
                <button
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                    className="relative inline-flex items-center rounded-l-[3px] px-2 py-1.5 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50"
                >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>

                {pages.map((p, i) => (
                    p === '...' ? (
                        <span key={i} className="relative inline-flex items-center px-3 py-1.5 text-[12px] font-medium text-slate-500 ring-1 ring-inset ring-slate-300">
                            ...
                        </span>
                    ) : (
                        <button
                            key={i}
                            onClick={() => onPageChange(p)}
                            className={`relative inline-flex items-center px-3 py-1.5 text-[12px] font-semibold ${
                                p === currentPage
                                    ? 'z-10 bg-[#0b5384] text-white'
                                    : 'text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                            }`}
                        >
                            {p}
                        </button>
                    )
                ))}

                <button
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage === totalPages}
                    className="relative inline-flex items-center rounded-r-[3px] px-2 py-1.5 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50"
                >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </nav>
        </div>
    );
}
