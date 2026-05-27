import { useMemo, useState, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import { formatRelativeTime, formatDateGroup, formatTimeAgo } from '@/lib/relativeTime';

/**
 * @param {Object} props
 * @param {Array} props.logs - Array of log objects with id, description, action, module, user, timestamp, old_value, new_value
 * @param {boolean} [props.showFilters=true] - Whether to show filter bar
 * @param {Function} [props.onFilterChange] - Callback when filters change
 * @param {string[]} [props.availableActions=[]] - Available action types for filter dropdown
 * @param {Object[]} [props.availableModules=[]] - Available modules for filter dropdown
 * @param {Object} [props.filterValues={}] - Current filter state
 * @param {Object} [props.pagination] - Pagination info with total, currentPage, totalPages
 * @param {Function} [props.onPageChange] - Callback for page change
 */
export function AuditTimeline({
    logs = [],
    showFilters = true,
    onFilterChange = () => {},
    availableActions = [],
    availableModules = [],
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
            date: label,
            logs: groups[label]
        }));
    }, [logs]);

    const hasFiltersActive = Object.keys(filterValues).some(k => 
        Array.isArray(filterValues[k]) ? filterValues[k].length > 0 : !!filterValues[k]
    );

    return (
        <div className="audit-timeline-container w-full">
            {showFilters && (
                <FilterBar 
                    availableActions={availableActions}
                    availableModules={availableModules}
                    filterValues={filterValues}
                    onFilterChange={onFilterChange}
                />
            )}

            {logs.length > 0 ? (
                <div className="relative">
                    {/* Vertical line */}
                    <div className="absolute left-5 top-4 bottom-0 w-0.5 bg-slate-200" />
                    
                    {groupedLogs.map(group => (
                        <div key={group.date}>
                            <DateGroupHeader label={group.label} count={group.logs.length} />
                            
                            {group.logs.map(log => (
                                <TimelineEntry key={log.id} log={log} />
                            ))}
                        </div>
                    ))}
                </div>
            ) : (
                <div className="bg-slate-50 rounded-xl border border-dashed border-slate-300 p-12 text-center">
                    <span className="material-symbols-outlined text-[48px] text-slate-400 mb-4 block mx-auto">
                        history
                    </span>
                    <h3 className="text-lg font-medium text-slate-900 mb-1">No activity found</h3>
                    <p className="text-slate-500 text-sm mb-4">Try adjusting your filters or check back later.</p>
                    
                    {hasFiltersActive && (
                        <button
                            onClick={() => onFilterChange({})}
                            className="inline-flex items-center gap-1 px-4 py-2 bg-white border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
                        >
                            <span className="material-symbols-outlined text-[18px]">filter_alt_off</span>
                            Clear Filters
                        </button>
                    )}
                </div>
            )}

            {pagination && <Pagination pagination={pagination} onPageChange={onPageChange} />}
        </div>
    );
}

const ACTION_STYLES = {
    CREATE: { dot: 'bg-emerald-500', badge: 'bg-emerald-100 text-emerald-700', icon: 'add_circle' },
    UPDATE: { dot: 'bg-blue-500', badge: 'bg-blue-100 text-blue-700', icon: 'edit' },
    DELETE: { dot: 'bg-red-500', badge: 'bg-red-100 text-red-700', icon: 'delete' },
    VIEW: { dot: 'bg-purple-500', badge: 'bg-purple-100 text-purple-700', icon: 'visibility' },
    LOGIN: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'login' },
    LOGOUT: { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'logout' },
};

function TimelineEntry({ log }) {
    const [expanded, setExpanded] = useState(false);
    const style = ACTION_STYLES[log.action] || { dot: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700', icon: 'info' };

    const initials = log.user ? log.user.substring(0, 2).toUpperCase() : '??';

    // Parse diff if UPDATE
    const hasChanges = log.action === 'UPDATE' && log.old_value && log.new_value;
    
    let oldVals = {};
    let newVals = {};
    
    if (hasChanges) {
        try {
            oldVals = typeof log.old_value === 'string' ? JSON.parse(log.old_value) : log.old_value;
            newVals = typeof log.new_value === 'string' ? JSON.parse(log.new_value) : log.new_value;
        } catch(e) {
           // Ignore errors
        }
    }
    
    const changedKeys = Array.from(new Set([...Object.keys(oldVals || {}), ...Object.keys(newVals || {})]));

    return (
        <div className="relative pl-12 py-4">
            {/* Colored dot */}
            <div className={`absolute left-5 top-8 -translate-x-1/2 w-3 h-3 rounded-full ring-4 ring-white ${style.dot} z-10`} />
            
            {/* Card */}
            <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm transition-shadow hover:shadow-md">
                {/* Row 1 */}
                <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-2">
                    <div className="flex items-center gap-3 flex-grow">
                        <div className="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 text-blue-900 flex items-center justify-center text-xs font-bold">
                            {initials}
                        </div>
                        <div className="flex-grow text-sm text-slate-900 font-medium">
                            {log.description}
                        </div>
                    </div>
                    <div className="flex items-center gap-3 self-start sm:self-center ml-11 sm:ml-0">
                        <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${style.badge}`}>
                            <span className="material-symbols-outlined text-[14px]">{style.icon}</span>
                            {log.action}
                        </span>
                        <div className="text-xs text-slate-500 whitespace-nowrap">
                            {formatTimeAgo(log.timestamp)}
                        </div>
                    </div>
                </div>

                {/* Row 2 */}
                <div className="text-xs text-slate-500 ml-11">
                    {formatRelativeTime(log.timestamp)} <span className="mx-1">•</span> {log.module}
                </div>

                {/* Row 3: Expandable diff panel */}
                {hasChanges && changedKeys.length > 0 && (
                    <div className="ml-11 mt-3">
                        <button 
                            onClick={() => setExpanded(!expanded)}
                            className="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 transition-colors"
                        >
                            <span className="material-symbols-outlined text-[16px] transition-transform duration-200" style={{ transform: expanded ? 'rotate(180deg)' : 'none' }}>
                                expand_more
                            </span>
                            {expanded ? 'Hide changes' : 'Show changes'}
                        </button>
                        
                        <div 
                            className={`overflow-hidden transition-all duration-300 ease-in-out ${expanded ? 'max-h-[500px] mt-2 opacity-100' : 'max-h-0 opacity-0'}`}
                        >
                            <div className="border border-slate-200 rounded-md overflow-x-auto">
                                <table className="w-full text-left text-xs text-slate-600">
                                    <thead className="bg-slate-50 text-slate-700 uppercase">
                                        <tr>
                                            <th className="px-3 py-2 border-b border-slate-200">Field</th>
                                            <th className="px-3 py-2 border-b border-slate-200">Old Value</th>
                                            <th className="px-3 py-2 border-b border-slate-200">New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {changedKeys.map(key => {
                                            const oldV = oldVals[key] !== undefined && oldVals[key] !== null ? String(oldVals[key]) : '-';
                                            const newV = newVals[key] !== undefined && newVals[key] !== null ? String(newVals[key]) : '-';
                                            
                                            // Only show if different
                                            if (oldV === newV) return null;
                                            
                                            return (
                                                <tr key={key} className="hover:bg-slate-50">
                                                    <td className="px-3 py-2 font-mono text-[11px] font-medium text-slate-700">{key}</td>
                                                    <td className="px-3 py-2 text-red-600 break-words max-w-[200px] bg-red-50/30">{oldV}</td>
                                                    <td className="px-3 py-2 text-emerald-600 break-words max-w-[200px] bg-emerald-50/30">{newV}</td>
                                                </tr>
                                            );
                                        })}
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

function FilterBar({ availableActions, availableModules, filterValues, onFilterChange }) {
    const handleActionToggle = (action) => {
        const currentActions = filterValues.actions || [];
        const newActions = currentActions.includes(action)
            ? currentActions.filter(a => a !== action)
            : [...currentActions, action];
        onFilterChange({ ...filterValues, actions: newActions });
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
        <div className="bg-white p-4 rounded-lg border border-slate-200 shadow-sm mb-6 space-y-4">
            <div className="flex flex-wrap gap-4 items-center justify-between">
                {/* Search */}
                <div className="relative flex-grow max-w-sm">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">
                        search
                    </span>
                    <input
                        type="text"
                        placeholder="Search logs..."
                        value={filterValues.search || ''}
                        onChange={handleSearchChange}
                        className="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                
                <div className="flex flex-wrap gap-3 items-center">
                    {/* Module Select */}
                    <select
                        value={filterValues.module || ''}
                        onChange={handleModuleChange}
                        className="py-2 pl-3 pr-8 border border-slate-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                        <option value="">All Modules</option>
                        {availableModules.map(m => (
                            <option key={m} value={m}>{m}</option>
                        ))}
                    </select>
                    
                    {/* Date Range */}
                    <div className="flex items-center gap-2">
                        <input
                            type="date"
                            value={filterValues.dateFrom || ''}
                            onChange={(e) => handleDateChange('dateFrom', e.target.value)}
                            className="py-2 px-3 border border-slate-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                        <span className="text-slate-500 text-sm">to</span>
                        <input
                            type="date"
                            value={filterValues.dateTo || ''}
                            onChange={(e) => handleDateChange('dateTo', e.target.value)}
                            className="py-2 px-3 border border-slate-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>
            </div>
            
            {/* Actions multi-select */}
            <div className="flex flex-wrap gap-2 items-center">
                <span className="text-sm font-medium text-slate-700 mr-2">Actions:</span>
                {availableActions.map(action => {
                    const isActive = (filterValues.actions || []).includes(action);
                    return (
                        <button
                            key={action}
                            onClick={() => handleActionToggle(action)}
                            className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors ${
                                isActive 
                                ? 'bg-blue-100 border-blue-200 text-blue-800' 
                                : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                            }`}
                        >
                            {action}
                        </button>
                    );
                })}
            </div>
            
            {/* Active filters summary & Clear */}
            {hasFilters && (
                <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div className="flex flex-wrap gap-2">
                        <span className="text-xs text-slate-500 flex items-center gap-1">
                            <span className="material-symbols-outlined text-[16px]">filter_list</span>
                            Filters active
                        </span>
                    </div>
                    <button
                        onClick={clearFilters}
                        className="text-xs text-red-600 hover:text-red-800 font-medium flex items-center gap-1"
                    >
                        <span className="material-symbols-outlined text-[16px]">close</span>
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
    
    // Simple page array generation
    let pages = [];
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    return (
        <div className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-lg shadow-sm mt-6">
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-slate-700">
                        Showing <span className="font-medium">{total ? total : 'results'}</span> results
                    </p>
                </div>
                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <button
                            onClick={() => onPageChange(currentPage - 1)}
                            disabled={currentPage === 1}
                            className="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 focus:z-20 focus:outline-offset-0"
                        >
                            <span className="sr-only">Previous</span>
                            <span className="material-symbols-outlined text-[20px]">chevron_left</span>
                        </button>
                        
                        {pages.map((p, i) => (
                            p === '...' ? (
                                <span key={i} className="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 focus:outline-offset-0">
                                    ...
                                </span>
                            ) : (
                                <button
                                    key={i}
                                    onClick={() => onPageChange(p)}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 focus:outline-offset-0 ${
                                        p === currentPage
                                        ? 'z-10 bg-blue-900 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-900'
                                        : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                                    }`}
                                >
                                    {p}
                                </button>
                            )
                        ))}
                        
                        <button
                            onClick={() => onPageChange(currentPage + 1)}
                            disabled={currentPage === totalPages}
                            className="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 focus:z-20 focus:outline-offset-0"
                        >
                            <span className="sr-only">Next</span>
                            <span className="material-symbols-outlined text-[20px]">chevron_right</span>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    );
}

function DateGroupHeader({ label, count }) {
    return (
        <div className="relative pl-12 py-3 mt-4 first:mt-0">
            {/* The dot for header on the line */}
            <div className="absolute left-5 top-1/2 -translate-x-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-slate-300 ring-4 ring-slate-50 z-10" />
            
            <div className="flex items-center gap-3">
                <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider">{label}</h3>
                <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-medium">
                    {count}
                </span>
            </div>
        </div>
    );
}
