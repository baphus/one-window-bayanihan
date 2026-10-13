import { useMemo, useCallback, useState, useEffect, useRef, useId } from 'react';
import { Link, router } from '@inertiajs/react';
import { formatDateGroup } from '@/lib/relativeTime';
import { AuditLogRow, CATEGORY_LABELS, actionStyle } from '@/lib/audit';
import { formatCount } from '@/Components/Dashboard/primitives';
import { sortTimelineItems } from '@/Components/Timeline';
import TablePagination from '@/Components/ui/TablePagination';

const ROLE_OPTIONS = ['ADMIN', 'CASE_MANAGER', 'AGENCY', 'OFW'];
const ROLE_LABELS = { ADMIN: 'Admin', CASE_MANAGER: 'Case Manager', AGENCY: 'Agency', OFW: 'OFW' };

// Presets are inclusive windows ending today, so "Last 7d" spans 7 calendar days.
const DATE_PRESETS = [
    { key: 'today', label: 'Today', days: 1 },
    { key: '7d', label: 'Last 7d', days: 7 },
    { key: '30d', label: 'Last 30d', days: 30 },
];

/** ISO Y-m-d in the viewer's local timezone (date inputs and URL params are local). */
function toLocalISO(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * @param {Object} props
 * @param {Array} props.logs - Safe audit response objects with event metadata and approved display fields
 * @param {boolean} [props.showFilters=true] - Whether to show filter bar
 * @param {Function} [props.onFilterChange] - Callback when filters change
 * @param {string[]} [props.availableActions=[]] - Available action types for filter dropdown
 * @param {Object[]} [props.availableModules=[]] - Available modules for filter dropdown
 * @param {Object} [props.availableModulesLabels={}] - Maps module -> human label for filter dropdown
 * @param {Object} [props.filterValues={}] - Current filter state
 * @param {boolean} [props.isScoped=false] - Viewer only sees their slice of the trail; hides admin-only filters
 * @param {Object} [props.pagination] - Pagination info: total, currentPage, totalPages, from, to, perPage
 * @param {Function} [props.onPageChange] - Callback for page change
 */
export function AuditTimeline({
    logs = [],
    showFilters = true,
    onFilterChange = () => {},
    availableActions = [],
    availableModules = [],
    availableModulesLabels = {},
    availableCategories = [],
    activeCategories = [],
    filterValues = {},
    isScoped = false,
    pagination,
    onPageChange = () => {},
}) {
    const groupedLogs = useMemo(() => {
        const groups = {};
        const groupOrder = [];

        // Newest-first ordering enforced with the shared timeline sorter.
        sortTimelineItems(logs).forEach(log => {
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
                    availableModulesLabels={availableModulesLabels}
                    availableCategories={availableCategories}
                    activeCategories={activeCategories}
                    filterValues={filterValues}
                    onFilterChange={onFilterChange}
                    isScoped={isScoped}
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

            {pagination && (
                <PaginationFooter
                    pagination={pagination}
                    onPageChange={onPageChange}
                    perPage={filterValues?.per_page ?? pagination.perPage}
                    onPerPageChange={(n) => onFilterChange({ ...filterValues, per_page: n })}
                />
            )}
        </div>
    );
}

function TimelineEntry({ log }) {
    const style = actionStyle(log.action);
    return (
        <div className="relative pl-12 py-3">
            <div className={`absolute left-5 top-4 -translate-x-1/2 w-2.5 h-2.5 rounded-full ring-4 ring-white ${style.dot} z-10`} />
            {/* Dense row: hairline separator, no card */}
            <div className="border-b border-slate-100 pb-3 hover:bg-slate-50/60 -mx-2 px-2 rounded transition-colors">
                <AuditLogRow log={log} maxRows={3} variant="full" />
            </div>
        </div>
    );
}

function FilterBar({ availableActions, availableModules, availableModulesLabels, availableCategories, activeCategories, filterValues, onFilterChange, isScoped = false }) {
    /* ---------- local state for debounced search + Apply-gated dates ---------- */
    const [localSearch, setLocalSearch] = useState(() => filterValues.search || '');
    const [localDateFrom, setLocalDateFrom] = useState(() => filterValues.date_from || '');
    const [localDateTo, setLocalDateTo] = useState(() => filterValues.date_to || '');
    const debounceRef = useRef(null);
    const dateFromRef = useRef(null);
    const filterValuesRef = useRef(filterValues);
    const onFilterChangeRef = useRef(onFilterChange);
    filterValuesRef.current = filterValues;
    onFilterChangeRef.current = onFilterChange;

    // Sync local search from external changes (e.g. browser back)
    useEffect(() => {
        setLocalSearch(filterValues.search || '');
    }, [filterValues.search]);

    // Sync local dates from external changes
    useEffect(() => {
        setLocalDateFrom(filterValues.date_from || '');
        setLocalDateTo(filterValues.date_to || '');
    }, [filterValues.date_from, filterValues.date_to]);

    // Cleanup debounce timer on unmount
    useEffect(() => {
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
    }, []);

    /* ---------- handlers ---------- */
    const handleSearchChange = useCallback((e) => {
        const value = e.target.value;
        setLocalSearch(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            onFilterChangeRef.current({ ...filterValuesRef.current, search: value });
        }, 400);
    }, []);

    const handleActionToggle = (action) => {
        const currentActions = (filterValues.action || '').split(',').filter(Boolean);
        const newActions = currentActions.includes(action)
            ? currentActions.filter(a => a !== action)
            : [...currentActions, action];
        onFilterChange({ ...filterValues, action: newActions.join(',') });
    };

    const handleCategoryToggle = (category) => {
        const current = activeCategories || [];
        const next = current.includes(category)
            ? current.filter(c => c !== category)
            : [...current, category];
        // Never allow an empty selection — fall back to the server default.
        onFilterChange({ ...filterValues, category: next.length > 0 ? next.join(',') : '' });
    };

    const handleModuleToggle = (module) => {
        const currentModules = (filterValues.module || '').split(',').filter(Boolean);
        const newModules = currentModules.includes(module)
            ? currentModules.filter(m => m !== module)
            : [...currentModules, module];
        onFilterChange({ ...filterValues, module: newModules.join(',') });
    };

    const handleRoleToggle = (role) => {
        const currentRoles = (filterValues.role || '').split(',').filter(Boolean);
        const newRoles = currentRoles.includes(role)
            ? currentRoles.filter(r => r !== role)
            : [...currentRoles, role];
        onFilterChange({ ...filterValues, role: newRoles.join(',') });
    };

    // Preset windows are computed per render so the active highlight follows
    // "today" and whatever range is currently applied.
    const presets = DATE_PRESETS.map(preset => {
        const start = new Date();
        start.setDate(start.getDate() - (preset.days - 1));
        return { ...preset, from: toLocalISO(start), to: toLocalISO(new Date()) };
    });

    const activePresetKey = (() => {
        const from = filterValues.date_from;
        const to = filterValues.date_to;
        if (!from && !to) return null;
        return presets.find(p => p.from === from && p.to === to)?.key ?? 'custom';
    })();

    const applyPreset = (preset) => {
        setLocalDateFrom(preset.from);
        setLocalDateTo(preset.to);
        onFilterChange({ ...filterValues, date_from: preset.from, date_to: preset.to });
    };

    const hasChangesOnly = String(filterValues.has_changes ?? '') === '1';

    const applyDateFilter = () => {
        onFilterChange({ ...filterValues, date_from: localDateFrom, date_to: localDateTo });
    };

    const resetDateFilter = () => {
        setLocalDateFrom('');
        setLocalDateTo('');
        onFilterChange({ ...filterValues, date_from: '', date_to: '' });
    };

    const clearFilters = () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        setLocalSearch('');
        setLocalDateFrom('');
        setLocalDateTo('');
        onFilterChange({});
    };

    const hasFilters = Object.keys(filterValues).some(k => 
        (Array.isArray(filterValues[k]) ? filterValues[k].length > 0 : !!filterValues[k])
    );

    return (
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 space-y-4">
            <div className="flex flex-wrap gap-4 items-center justify-between">
                {/* Search */}
                <div className="relative flex-grow max-w-sm">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">
                        search
                    </span>
                    <input
                        type="text"
                        placeholder="Search action, module, actor, or ID..."
                        value={localSearch}
                        onChange={handleSearchChange}
                        className="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-md text-sm focus:ring-primary focus:border-primary"
                    />
                </div>

                {/* Only-with-changes toggle */}
                <label className="inline-flex items-center gap-2 select-none cursor-pointer" title="Hide events that recorded no field changes">
                    <input
                        type="checkbox"
                        checked={hasChangesOnly}
                        onChange={(e) => onFilterChange({ ...filterValues, has_changes: e.target.checked ? '1' : '' })}
                        className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary"
                    />
                    <span className="text-sm text-slate-600">Only with changes</span>
                </label>
            </div>

            {/* Admin-only: actor picker + current-role chips */}
            {!isScoped && (
                <div className="flex flex-wrap gap-x-6 gap-y-3 items-center">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-slate-700">Actor:</span>
                        <ActorPicker filterValues={filterValues} onFilterChange={onFilterChange} />
                    </div>
                    <div className="flex flex-wrap gap-2 items-center">
                        <span
                            className="text-sm font-medium text-slate-700 mr-2"
                            title="Reflects the user's current role, not necessarily the role they held when the event was recorded."
                        >
                            Current role:
                        </span>
                        {ROLE_OPTIONS.map(role => {
                            const isActive = (filterValues.role || '').split(',').includes(role);
                            return (
                                <button
                                    key={role}
                                    onClick={() => handleRoleToggle(role)}
                                    className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors ${
                                        isActive
                                        ? 'bg-primary-fixed border-primary/20 text-primary'
                                        : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'
                                    }`}
                                >
                                    {ROLE_LABELS[role] || role}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Date presets + Apply/Reset range */}
            <div className="flex flex-wrap gap-3 items-center">
                <span className="text-sm font-medium text-slate-700">Date:</span>
                {presets.map(preset => (
                    <button
                        key={preset.key}
                        onClick={() => applyPreset(preset)}
                        className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors ${
                            activePresetKey === preset.key
                            ? 'bg-primary-fixed border-primary/20 text-primary'
                            : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'
                        }`}
                    >
                        {preset.label}
                    </button>
                ))}
                <button
                    onClick={() => dateFromRef.current?.focus()}
                    title="Set a custom range with the date inputs"
                    className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors ${
                        activePresetKey === 'custom'
                        ? 'bg-primary-fixed border-primary/20 text-primary'
                        : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'
                    }`}
                >
                    Custom
                </button>

                <div className="flex items-center gap-2">
                    {/* Date Range — Apply/Reset pattern */}
                    <input
                        ref={dateFromRef}
                        type="date"
                        value={localDateFrom}
                        onChange={(e) => setLocalDateFrom(e.target.value)}
                        className="py-2 px-3 border border-slate-300 rounded-md text-sm focus:ring-primary focus:border-primary"
                    />
                    <span className="text-slate-500 text-sm">to</span>
                    <input
                        type="date"
                        value={localDateTo}
                        onChange={(e) => setLocalDateTo(e.target.value)}
                        className="py-2 px-3 border border-slate-300 rounded-md text-sm focus:ring-primary focus:border-primary"
                    />
                    <button
                        onClick={applyDateFilter}
                        className="px-3 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primary-container transition-colors"
                    >
                        Apply
                    </button>
                    <button
                        onClick={resetDateFilter}
                        className="px-3 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-md hover:bg-slate-50 transition-colors"
                    >
                        Reset
                    </button>
                </div>
            </div>
            
            {/* Category multi-select — defaults exclude system noise */}
            {availableCategories.length > 0 && (
                <div className="flex flex-wrap gap-2 items-center">
                    <span className="text-sm font-medium text-slate-700 mr-2">Showing:</span>
                    {availableCategories.map(category => {
                        const isActive = (activeCategories || []).includes(category);
                        return (
                            <button
                                key={category}
                                onClick={() => handleCategoryToggle(category)}
                                title={category === 'system' ? 'Automated and maintenance activity (hidden by default)' : undefined}
                                className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors ${
                                    isActive
                                    ? 'bg-primary-fixed border-primary/20 text-primary'
                                    : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'
                                }`}
                            >
                                {CATEGORY_LABELS[category] || category}
                            </button>
                        );
                    })}
                    {!(activeCategories || []).includes('system') && (
                        <span className="text-xs text-slate-400">System activity hidden</span>
                    )}
                </div>
            )}

            {/* Actions multi-select */}
            <div className="flex flex-wrap gap-2 items-center">
                <span className="text-sm font-medium text-slate-700 mr-2">Actions:</span>
                {availableActions.map(action => {
                    const isActive = (filterValues.action || '').split(',').includes(action);
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

            {/* Modules multi-select */}
            {availableModules.length > 0 && (
                <div className="flex flex-wrap gap-2 items-center">
                    <span className="text-sm font-medium text-slate-700 mr-2">Modules:</span>
                    {availableModules.map(m => {
                        const isActive = (filterValues.module || '').split(',').includes(m);
                        return (
                            <button
                                key={m}
                                onClick={() => handleModuleToggle(m)}
                                className={`px-3 py-1 rounded-full text-xs font-medium border transition-colors ${
                                    isActive
                                    ? 'bg-violet-100 border-violet-200 text-violet-800'
                                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                                }`}
                            >
                                {availableModulesLabels[m] || m}
                            </button>
                        );
                    })}
                </div>
            )}
            
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

/**
 * Async single-select actor combobox backed by GET /audit-logs/actors
 * (admin-only). Kept local to the filter bar: it only ever writes `user_id`.
 */
function ActorPicker({ filterValues, onFilterChange }) {
    const userId = filterValues.user_id || '';
    const [query, setQuery] = useState('');
    const [actors, setActors] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [highlighted, setHighlighted] = useState(0);
    const wrapperRef = useRef(null);
    const debounceRef = useRef(null);
    const seqRef = useRef(0);
    const listboxId = useId();

    const selected = actors.find(a => String(a.id) === String(userId)) || null;

    const fetchActors = useCallback((search) => {
        const seq = ++seqRef.current;
        setLoading(true);
        fetch(`/audit-logs/actors?search=${encodeURIComponent(search)}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(res => (res.ok ? res.json() : []))
            .then((data) => {
                if (seq !== seqRef.current) return;
                setActors(Array.isArray(data) ? data : (data?.data ?? []));
            })
            .catch(() => {
                if (seq !== seqRef.current) return;
                setActors([]);
            })
            .finally(() => {
                if (seq !== seqRef.current) return;
                setLoading(false);
            });
    }, []);

    // Resolve the name behind a user_id that arrived from the URL.
    useEffect(() => {
        if (userId && !actors.some(a => String(a.id) === String(userId))) {
            fetchActors('');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId]);

    // Reset the typed query when the selection itself changes or is cleared.
    useEffect(() => {
        setQuery('');
        setOpen(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId]);

    useEffect(() => {
        const handleOutside = (e) => {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handleOutside);
        return () => {
            document.removeEventListener('mousedown', handleOutside);
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, []);

    const select = (actor) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        setOpen(false);
        setQuery('');
        onFilterChange({ ...filterValues, user_id: String(actor.id) });
    };

    const clear = () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        setOpen(false);
        setQuery('');
        onFilterChange({ ...filterValues, user_id: '' });
    };

    const handleQueryChange = (e) => {
        const value = e.target.value;
        setQuery(value);
        setOpen(true);
        setHighlighted(0);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => fetchActors(value), 400);
    };

    const handleFocus = () => {
        setOpen(true);
        setHighlighted(0);
        if (actors.length === 0) fetchActors(query);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
            setOpen(false);
            return;
        }
        if (!open) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlighted(i => Math.min(i + 1, Math.max(actors.length - 1, 0)));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlighted(i => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (actors[highlighted]) select(actors[highlighted]);
        }
    };

    const displayValue = open ? query : (selected?.name ?? (userId ? 'Selected actor' : ''));

    return (
        <div ref={wrapperRef} className="relative w-64">
            <input
                type="text"
                value={displayValue}
                onChange={handleQueryChange}
                onFocus={handleFocus}
                onKeyDown={handleKeyDown}
                placeholder="Search by name or email..."
                autoComplete="off"
                role="combobox"
                aria-expanded={open}
                aria-controls={listboxId}
                aria-autocomplete="list"
                aria-label="Actor"
                aria-activedescendant={open && actors[highlighted] ? `${listboxId}-${highlighted}` : undefined}
                className="w-full py-2 pl-3 pr-9 border border-slate-300 rounded-md text-sm focus:ring-primary focus:border-primary"
            />
            {userId && !open ? (
                <button
                    type="button"
                    onClick={clear}
                    title="Clear actor filter"
                    aria-label="Clear actor filter"
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                >
                    <span className="material-symbols-outlined text-[16px]">close</span>
                </button>
            ) : (
                <span className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 material-symbols-outlined text-[16px] text-slate-400">
                    {open ? 'expand_less' : 'expand_more'}
                </span>
            )}

            {open && (
                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label="Actors"
                    className="absolute z-50 mt-1 w-full max-h-56 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg owb-scroll-wide"
                >
                    {loading && actors.length === 0 && (
                        <li className="px-3 py-2 text-[11px] text-slate-400 italic">Searching...</li>
                    )}
                    {!loading && actors.length === 0 && (
                        <li className="px-3 py-2 text-[11px] text-slate-400 italic">No people found</li>
                    )}
                    {actors.map((actor, idx) => (
                        <li
                            key={actor.id}
                            id={`${listboxId}-${idx}`}
                            role="option"
                            aria-selected={String(actor.id) === String(userId)}
                            onMouseDown={(e) => { e.preventDefault(); select(actor); }}
                            onMouseEnter={() => setHighlighted(idx)}
                            className={`px-3 py-1.5 text-[12px] cursor-pointer transition-colors ${
                                idx === highlighted ? 'bg-slate-100' : 'hover:bg-slate-50'
                            }`}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="min-w-0">
                                    <span className="block truncate font-medium text-slate-800">{actor.name}</span>
                                    <span className="block truncate text-[11px] text-slate-400">{actor.email}</span>
                                </span>
                                {actor.deactivated && (
                                    <span className="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 ring-1 ring-slate-200">
                                        Deactivated
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function PaginationFooter({ pagination, onPageChange, perPage = 15, onPerPageChange }) {
    if (!pagination) return null;
    if (!pagination.total) return null;

    const { currentPage, totalPages, total, from, to } = pagination;

    return (
        <div className="mt-6 flex flex-col md:flex-row items-center justify-between gap-4 rounded-md border border-slate-300 bg-slate-50 px-6 py-4">
            <div className="text-[13px] text-slate-500 text-left">
                Showing <span className="font-bold text-slate-700">{from ?? 0}–{to ?? 0}</span> of <span className="font-bold text-slate-700">{formatCount(total)}</span> records
            </div>

            <div className="flex flex-wrap items-center gap-6">
                {onPerPageChange && (
                    <div className="flex items-center gap-3">
                        <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Rows per page:</label>
                        <select
                            value={perPage}
                            onChange={(e) => onPerPageChange(Number(e.target.value))}
                            className="bg-white border border-slate-300 text-[13px] font-bold text-slate-700 rounded-md pl-3 pr-7 py-1.5 outline-none focus:ring-1 focus:ring-primary"
                        >
                            {[15, 25, 50, 100].map(size => <option key={size} value={size}>{size}</option>)}
                        </select>
                    </div>
                )}

                <TablePagination currentPage={currentPage} totalPages={totalPages} onPageChange={onPageChange} />
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
