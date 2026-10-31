import Select from '@/Components/Select';
import { useMemo, useCallback, useState, useEffect, useRef, useId } from 'react';
import { formatDateGroup } from '@/lib/relativeTime';
import { AuditLogRow, CATEGORY_LABELS, actionStyle } from '@/lib/audit';
import { formatCount } from '@/Components/Dashboard/primitives';
import { sortTimelineItems } from '@/Components/Timeline';
import TablePagination from '@/Components/ui/TablePagination';

const ROLE_OPTIONS = ['ADMIN', 'CASE_MANAGER', 'AGENCY', 'OFW'];
const ROLE_LABELS = { ADMIN: 'Admin', CASE_MANAGER: 'Case Manager', AGENCY: 'Agency', OFW: 'OFW' };

// Presets are inclusive windows ending today, so "7d" spans 7 calendar days.
const DATE_PRESETS = [
    { key: 'today', label: 'Today', days: 1 },
    { key: '7d', label: '7d', days: 7 },
    { key: '30d', label: '30d', days: 30 },
];

/** ISO Y-m-d in the viewer's local timezone (date inputs and URL params are local). */
function toLocalISO(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

/** String-set compare so "default categories" detection is order-independent. */
function sameStringSet(a, b) {
    if (!Array.isArray(a) || !Array.isArray(b)) return false;
    if (a.length !== b.length) return false;
    const sa = [...a].sort().join('|');
    const sb = [...b].sort().join('|');
    return sa === sb;
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
                />
            )}

            {logs.length > 0 ? (
                <div className="relative">
                    {/* Vertical line */}
                    <div className="absolute left-5 top-3 bottom-0 w-0.5 bg-slate-200" />

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
                <div className="bg-slate-50 rounded-xl border border-dashed border-slate-300 p-10 text-center">
                    <span className="material-symbols-outlined text-[40px] text-slate-400 mb-3 block mx-auto">
                        history
                    </span>
                    <h3 className="text-base font-medium text-slate-900 mb-1">No activity found</h3>
                    <p className="text-slate-500 text-xs mb-3">Try adjusting your filters or check back later.</p>

                    {hasFiltersActive && (
                        <button
                            onClick={() => onFilterChange({})}
                            className="inline-flex items-center gap-1 h-8 px-3 bg-white border border-slate-300 rounded-md text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            <span className="material-symbols-outlined text-[16px]">filter_alt_off</span>
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
        <div className="relative pl-11 py-2">
            <div className={`absolute left-5 top-3 -translate-x-1/2 w-2.5 h-2.5 rounded-full ring-4 ring-white ${style.dot} z-10`} />
            {/* Dense row: hairline separator, no card */}
            <div className="border-b border-slate-100 pb-2 hover:bg-slate-50/60 -mx-2 px-2 rounded transition-colors">
                <AuditLogRow log={log} maxRows={3} variant="full" />
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  FilterBar — two-tier: a calm primary row (search + quick date      */
/*  presets + a disclosure toggle) with everything else behind the     */
/*  "Filters" disclosure as one flat label/column block, plus an        */
/*  always-visible active-filter chip bar.                             */
/* ------------------------------------------------------------------ */

/* Shared compact scale — kept here, not extracted into a new file:   */
/* both pages use the same strings verbatim so they stay in sync.     */
const CONTROL_BASE = 'h-8 px-2.5 border border-slate-300 rounded-md text-xs bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary';
const LABEL_QUIET = 'text-[11px] font-medium uppercase tracking-wide text-slate-400';

function FilterBar({ availableActions, availableModules, availableModulesLabels, availableCategories, activeCategories, filterValues, onFilterChange }) {
    /* ---------- local state for debounced search + Apply-gated dates ---------- */
    const [localSearch, setLocalSearch] = useState(() => filterValues.search || '');
    const [localDateFrom, setLocalDateFrom] = useState(() => filterValues.date_from || '');
    const [localDateTo, setLocalDateTo] = useState(() => filterValues.date_to || '');
    const [showAdvanced, setShowAdvanced] = useState(false);
    const debounceRef = useRef(null);
    const dateFromRef = useRef(null);
    const filterValuesRef = useRef(filterValues);
    const onFilterChangeRef = useRef(onFilterChange);
    filterValuesRef.current = filterValues;
    onFilterChangeRef.current = onFilterChange;

    const advancedId = useId();
    const searchId = useId();
    const actorInputId = useId();
    const dateFromId = useId();
    const dateToId = useId();

    // Sync local search from external changes (e.g. browser back).
    useEffect(() => {
        setLocalSearch(filterValues.search || '');
    }, [filterValues.search]);

    // Sync local dates from external changes.
    useEffect(() => {
        setLocalDateFrom(filterValues.date_from || '');
        setLocalDateTo(filterValues.date_to || '');
    }, [filterValues.date_from, filterValues.date_to]);

    // Cleanup debounce timer on unmount.
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

    const clearSearch = () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        setLocalSearch('');
        onFilterChange({ ...filterValues, search: '' });
    };

    const splitList = (value) => (value || '').split(',').filter(Boolean);

    const toggleInList = (key, item) => {
        const current = splitList(filterValues[key]);
        const next = current.includes(item)
            ? current.filter(x => x !== item)
            : [...current, item];
        onFilterChange({ ...filterValues, [key]: next.join(',') });
    };

    const removeFromList = (key, item) => {
        const next = splitList(filterValues[key]).filter(x => x !== item);
        onFilterChange({ ...filterValues, [key]: next.join(',') });
    };

    const handleActionToggle = (action) => toggleInList('action', action);
    const handleModuleToggle = (module) => toggleInList('module', module);
    const handleRoleToggle = (role) => toggleInList('role', role);

    const handleCategoryToggle = (category) => {
        const current = activeCategories || [];
        const next = current.includes(category)
            ? current.filter(c => c !== category)
            : [...current, category];
        // Never allow an empty selection — fall back to the server default.
        onFilterChange({ ...filterValues, category: next.length > 0 ? next.join(',') : '' });
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

    const openCustomRange = () => {
        setShowAdvanced(true);
        // Focus the From input once the disclosure paints.
        requestAnimationFrame(() => dateFromRef.current?.focus());
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

    /* ---------- active-filter chips ---------- */
    // Categories ship with a server default (all except "system"). A deviation
    // from that default surfaces as a single removable chip that restores it.
    const defaultCategories = (availableCategories || []).filter(c => c !== 'system');
    const categoriesAreDefault = sameStringSet(activeCategories || [], defaultCategories);

    const selectedActorName = filterValues.user_id
        ? (filterValues._actorName ?? null) // resolved by ActorPicker; not required
        : null;

    const activeChips = [];
    if (filterValues.user_id) {
        activeChips.push({
            key: 'user_id',
            label: 'Actor',
            value: selectedActorName || 'Selected user',
            remove: () => onFilterChange({ ...filterValues, user_id: '' }),
        });
    }
    splitList(filterValues.role).forEach(r => {
        activeChips.push({
            key: `role:${r}`,
            label: 'Role',
            value: ROLE_LABELS[r] || r,
            remove: () => removeFromList('role', r),
        });
    });
    if (filterValues.date_from || filterValues.date_to) {
        const preset = presets.find(p => p.from === filterValues.date_from && p.to === filterValues.date_to);
        activeChips.push({
            key: 'date',
            label: 'Date',
            value: preset ? preset.label : 'Custom range',
            remove: resetDateFilter,
        });
    }
    splitList(filterValues.action).forEach(a => {
        activeChips.push({
            key: `action:${a}`,
            label: 'Action',
            value: a,
            remove: () => removeFromList('action', a),
        });
    });
    splitList(filterValues.module).forEach(m => {
        activeChips.push({
            key: `module:${m}`,
            label: 'Module',
            value: availableModulesLabels[m] || m,
            remove: () => removeFromList('module', m),
        });
    });
    if (hasChangesOnly) {
        activeChips.push({
            key: 'has_changes',
            label: 'Only with changes',
            value: '',
            remove: () => onFilterChange({ ...filterValues, has_changes: '' }),
        });
    }
    if (!categoriesAreDefault) {
        activeChips.push({
            key: 'category',
            label: 'Categories',
            value: 'Customized',
            remove: () => onFilterChange({ ...filterValues, category: '' }),
        });
    }

    const activeChipCount = activeChips.length;

    return (
        <div className="bg-white p-3 rounded-xl border border-slate-200 shadow-sm mb-4">
            {/* ---------- Primary row: search + quick date presets + disclosure ---------- */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                <div className="relative flex-1 min-w-0">
                    <label htmlFor={searchId} className="sr-only">Search audit logs</label>
                    <span className="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[16px]" aria-hidden="true">
                        search
                    </span>
                    <input
                        id={searchId}
                        type="text"
                        placeholder="Search action, module, actor, or ID..."
                        value={localSearch}
                        onChange={handleSearchChange}
                        className={`${CONTROL_BASE} w-full pl-9 pr-8`}
                    />
                    {localSearch && (
                        <button
                            type="button"
                            onClick={clearSearch}
                            aria-label="Clear search"
                            className="absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center h-6 w-6 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <span className="material-symbols-outlined text-[15px]">close</span>
                        </button>
                    )}
                </div>

                {/* Quick date presets (inclusive windows ending today) */}
                <div className="inline-flex items-stretch h-8 rounded-md border border-slate-300 bg-white overflow-hidden self-start sm:self-auto" role="group" aria-label="Quick date range">
                    {presets.map(preset => {
                        const isActive = activePresetKey === preset.key;
                        return (
                            <button
                                key={preset.key}
                                type="button"
                                onClick={() => applyPreset(preset)}
                                aria-pressed={isActive}
                                className={`px-2.5 inline-flex items-center justify-center text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                                    isActive
                                        ? 'bg-primary-fixed text-primary'
                                        : 'text-slate-500 hover:bg-slate-50'
                                }`}
                            >
                                {preset.label}
                            </button>
                        );
                    })}
                    <button
                        type="button"
                        onClick={openCustomRange}
                        aria-pressed={activePresetKey === 'custom'}
                        title="Set a custom range"
                        className={`px-2.5 inline-flex items-center justify-center text-xs font-semibold border-l border-slate-200 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                            activePresetKey === 'custom'
                                ? 'bg-primary-fixed text-primary'
                                : 'text-slate-500 hover:bg-slate-50'
                        }`}
                    >
                        Custom
                    </button>
                </div>

                {/* Disclosure toggle */}
                <button
                    type="button"
                    onClick={() => setShowAdvanced(v => !v)}
                    aria-expanded={showAdvanced}
                    aria-controls={advancedId}
                    className="h-8 px-3 inline-flex items-center justify-center gap-1.5 border border-slate-300 rounded-md text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary self-start sm:self-auto"
                >
                    <span className="material-symbols-outlined text-[16px]" aria-hidden="true">tune</span>
                    <span>Filters</span>
                    {activeChipCount > 0 && (
                        <span className="bg-primary text-white text-[10px] font-bold min-w-[16px] h-[16px] px-1 rounded-full inline-flex items-center justify-center leading-none">
                            {activeChipCount}
                        </span>
                    )}
                    <span className="material-symbols-outlined text-[16px] transition-transform" aria-hidden="true" style={{ transform: showAdvanced ? 'rotate(180deg)' : 'none' }}>
                        expand_more
                    </span>
                </button>
            </div>

            {/* ---------- Active-filter chip bar ---------- */}
            {activeChipCount > 0 && (
                <div className="mt-2 pt-2 border-t border-slate-100 flex flex-wrap items-center gap-1.5">
                    <span className={`${LABEL_QUIET} mr-0.5`}>Active</span>
                    {activeChips.map(chip => (
                        <FilterChip key={chip.key} label={chip.label} value={chip.value} onRemove={chip.remove} />
                    ))}
                    <button
                        type="button"
                        onClick={clearFilters}
                        className="ml-0.5 text-xs font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded"
                    >
                        Clear all
                    </button>
                </div>
            )}

            {/* ---------- Advanced filters disclosure ---------- */}
            {showAdvanced && (
                <div id={advancedId} className="mt-2 pt-2 border-t border-slate-100 space-y-2.5">
                    {/* Actor */}
                    <FilterRow labelId={actorInputId} label="Actor">
                        <ActorPicker inputId={actorInputId} filterValues={filterValues} onFilterChange={onFilterChange} />
                    </FilterRow>

                    {/* Role */}
                    <FilterRow label="Role" title="Reflects the user's current role, not necessarily the role they held when the event was recorded.">
                        <div role="group" aria-label="Current role" className="flex flex-wrap gap-1.5">
                            {ROLE_OPTIONS.map(role => {
                                const isActive = splitList(filterValues.role).includes(role);
                                return (
                                    <ToggleChip
                                        key={role}
                                        active={isActive}
                                        onClick={() => handleRoleToggle(role)}
                                    >
                                        {ROLE_LABELS[role] || role}
                                    </ToggleChip>
                                );
                            })}
                        </div>
                    </FilterRow>

                    {/* Date range (complements the quick presets above) */}
                    <FilterRow label="Date">
                        <div className="flex flex-wrap items-center gap-2">
                            <label htmlFor={dateFromId} className="sr-only">Date from</label>
                            <input
                                id={dateFromId}
                                ref={dateFromRef}
                                type="date"
                                value={localDateFrom}
                                onChange={(e) => setLocalDateFrom(e.target.value)}
                                className={`${CONTROL_BASE} w-[8.5rem]`}
                            />
                            <span className="text-slate-400 text-xs" aria-hidden="true">to</span>
                            <label htmlFor={dateToId} className="sr-only">Date to</label>
                            <input
                                id={dateToId}
                                type="date"
                                value={localDateTo}
                                onChange={(e) => setLocalDateTo(e.target.value)}
                                className={`${CONTROL_BASE} w-[8.5rem]`}
                            />
                            <button
                                type="button"
                                onClick={applyDateFilter}
                                className="h-8 px-3 bg-primary text-on-primary text-xs font-semibold rounded-md hover:bg-primary-container transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                onClick={resetDateFilter}
                                className="h-8 px-3 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-md hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            >
                                Reset
                            </button>
                        </div>
                    </FilterRow>

                    {/* Categories (defaults exclude system noise) */}
                    {availableCategories.length > 0 && (
                        <FilterRow label="Showing">
                            <div role="group" aria-label="Categories" className="flex flex-wrap gap-1.5">
                                {availableCategories.map(category => {
                                    const isActive = (activeCategories || []).includes(category);
                                    return (
                                        <ToggleChip
                                            key={category}
                                            active={isActive}
                                            onClick={() => handleCategoryToggle(category)}
                                            title={category === 'system' ? 'Automated and maintenance activity (hidden by default)' : undefined}
                                        >
                                            {CATEGORY_LABELS[category] || category}
                                        </ToggleChip>
                                    );
                                })}
                            </div>
                            {!(activeCategories || []).includes('system') && (
                                <p className="text-[11px] text-slate-400 mt-1">System activity hidden</p>
                            )}
                        </FilterRow>
                    )}

                    {/* Actions */}
                    <FilterRow label="Actions">
                        <div role="group" aria-label="Actions" className="flex flex-wrap gap-1.5">
                            {availableActions.map(action => {
                                const isActive = splitList(filterValues.action).includes(action);
                                return (
                                    <ToggleChip
                                        key={action}
                                        active={isActive}
                                        onClick={() => handleActionToggle(action)}
                                    >
                                        {action}
                                    </ToggleChip>
                                );
                            })}
                        </div>
                    </FilterRow>

                    {/* Modules */}
                    {availableModules.length > 0 && (
                        <FilterRow label="Modules">
                            <div role="group" aria-label="Modules" className="flex flex-wrap gap-1.5">
                                {availableModules.map(m => {
                                    const isActive = splitList(filterValues.module).includes(m);
                                    return (
                                        <ToggleChip
                                            key={m}
                                            active={isActive}
                                            onClick={() => handleModuleToggle(m)}
                                        >
                                            {availableModulesLabels[m] || m}
                                        </ToggleChip>
                                    );
                                })}
                            </div>
                        </FilterRow>
                    )}

                    {/* Options */}
                    <FilterRow label="Options">
                        <label
                            className="inline-flex items-center gap-2 select-none cursor-pointer"
                            title="Hide events that recorded no field changes"
                        >
                            <input
                                type="checkbox"
                                checked={hasChangesOnly}
                                onChange={(e) => onFilterChange({ ...filterValues, has_changes: e.target.checked ? '1' : '' })}
                                className="h-3.5 w-3.5 rounded border-slate-300 text-primary focus:ring-primary"
                            />
                            <span className="text-xs text-slate-700">Only with changes</span>
                        </label>
                    </FilterRow>
                </div>
            )}
        </div>
    );
}

/** One disclosure row: a quiet label column + a flowing control column. */
function FilterRow({ label, labelId, title, children }) {
    const labelEl = labelId
        ? <label htmlFor={labelId} className={`${LABEL_QUIET} pt-1.5`}>{label}</label>
        : <span className={`${LABEL_QUIET} pt-1.5`} title={title}>{label}</span>;
    return (
        <div className="grid grid-cols-[4.5rem_1fr] gap-x-3 items-start">
            {labelEl}
            <div className="min-w-0">{children}</div>
        </div>
    );
}

/** Removable active-filter chip. */
function FilterChip({ label, value, onRemove }) {
    return (
        <span className="inline-flex items-center gap-1 bg-primary-fixed text-primary pl-2 pr-0.5 py-0.5 rounded-full text-[11px] font-medium border border-primary-fixed-dim">
            <span className="font-semibold">{label}{value ? `: ${value}` : ''}</span>
            <button
                type="button"
                onClick={onRemove}
                aria-label={`Remove ${label} filter`}
                className="inline-flex items-center justify-center h-5 w-5 rounded-full hover:bg-primary/10 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
                <span className="material-symbols-outlined text-[14px]">close</span>
            </button>
        </span>
    );
}

/** Toggle pill — unifies role/category/action/module selection visuals. */
function ToggleChip({ active, onClick, children, title }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={title}
            aria-pressed={active}
            className={`h-7 px-2.5 inline-flex items-center rounded-full text-xs font-medium border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 ${
                active
                    ? 'bg-primary-fixed border-primary/20 text-primary'
                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
        >
            {children}
        </button>
    );
}

/**
 * Async single-select actor combobox backed by GET /audit-logs/actors
 * (admin-only). Kept local to the filter bar: it only ever writes `user_id`.
 */
function ActorPicker({ inputId, filterValues, onFilterChange }) {
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
        <div ref={wrapperRef} className="relative w-full max-w-xs">
            <input
                id={inputId}
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
                className="w-full h-8 pl-2.5 pr-8 border border-slate-300 rounded-[3px] text-xs text-slate-700 bg-white outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
            />
            {userId && !open ? (
                <button
                    type="button"
                    onClick={clear}
                    title="Clear actor filter"
                    aria-label="Clear actor filter"
                    className="absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center h-6 w-6 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                    <span className="material-symbols-outlined text-[15px]">close</span>
                </button>
            ) : (
                <span className="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-[15px] text-slate-400" aria-hidden="true">
                    {open ? 'expand_less' : 'expand_more'}
                </span>
            )}

            {open && (
                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label="Actors"
                    className="absolute z-50 mt-1 w-full max-h-52 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg owb-scroll-wide"
                >
                    {loading && actors.length === 0 && (
                        <li className="px-2.5 py-1.5 text-[11px] text-slate-400 italic">Searching...</li>
                    )}
                    {!loading && actors.length === 0 && (
                        <li className="px-2.5 py-1.5 text-[11px] text-slate-400 italic">No people found</li>
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
                                String(actor.id) === String(userId) ? 'bg-indigo-50 text-indigo-700 font-semibold' : idx === highlighted ? 'bg-slate-100 text-slate-800' : 'text-slate-700 hover:bg-slate-50'
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
        <div className="mt-4 flex flex-col md:flex-row items-center justify-between gap-3 rounded-md border border-slate-300 bg-slate-50 px-4 py-2.5">
            <div className="text-xs text-slate-500 text-left">
                Showing <span className="font-semibold text-slate-700">{from ?? 0}–{to ?? 0}</span> of <span className="font-semibold text-slate-700">{formatCount(total)}</span> records
            </div>

            <div className="flex flex-wrap items-center gap-4">
                {onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <label htmlFor="audit-rows-per-page" className={LABEL_QUIET}>Rows per page:</label>
                        <Select
                            id="audit-rows-per-page"
                            value={perPage}
                            onChange={(e) => onPerPageChange(Number(e.target.value))}
                            className="bg-white border border-slate-300 text-xs font-semibold text-slate-700 rounded-md pl-2.5 pr-6 h-7 outline-none focus:ring-1 focus:ring-primary"
                        >
                            {[15, 25, 50, 100].map(size => <option key={size} value={size}>{size}</option>)}
                        </Select>
                    </div>
                )}

                <TablePagination currentPage={currentPage} totalPages={totalPages} onPageChange={onPageChange} />
            </div>
        </div>
    );
}

function DateGroupHeader({ label, count }) {
    return (
        <div className="relative pl-11 py-2 mt-3 first:mt-0">
            {/* The dot for header on the line */}
            <div className="absolute left-5 top-1/2 -translate-x-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-slate-300 ring-4 ring-slate-50 z-10" />

            <div className="flex items-center gap-2">
                <h3 className="text-xs font-semibold text-slate-700 uppercase tracking-wide">{label}</h3>
                <span className="px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[11px] font-medium">
                    {count}
                </span>
            </div>
        </div>
    );
}
