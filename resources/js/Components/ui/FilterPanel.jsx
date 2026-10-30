import { useMemo, useState } from 'react';
import FilterCheckboxGroup from './FilterCheckboxGroup';

/**
 * Shared shell for a multi-select filter panel.
 *
 * Layout is deliberately narrow and single-column, the way Jira's filter
 * popover works: one stack of checkbox groups, no side-by-side selects, no
 * fixed multi-row list boxes. Pages describe their groups as data, so this
 * component can apply the in-panel search across every option list without
 * each page re-implementing it.
 *
 * Groups start expanded so nothing is hidden behind an extra click; a group
 * with more than `collapseAfter` options starts collapsed to keep the popover
 * short. Collapsing is a disclosure, not a tab -- it only hides its own rows.
 *
 * @param {object}   props
 * @param {Array<{key: string, label: string, options: Array<{value: string, label: string, count?: number}>}>} props.groups
 * @param {object}   props.selected          Map of group key -> selected values.
 * @param {function} props.onToggle          (groupKey, value) => void
 * @param {function} props.onClearAll
 * @param {number}   props.activeCount       Number of applied filter values.
 * @param {boolean}  [props.searchable]
 * @param {number}   [props.collapseAfter]   Option count above which a group starts collapsed.
 */
export default function FilterPanel({
    groups = [],
    selected = {},
    onToggle,
    onClearAll,
    activeCount = 0,
    searchable = false,
    collapseAfter = 6,
}) {
    const [query, setQuery] = useState('');
    const [collapsed, setCollapsed] = useState(() => {
        const initial = {};
        groups.forEach((group) => {
            if ((group.options?.length ?? 0) > collapseAfter) {
                initial[group.key] = true;
            }
        });
        return initial;
    });

    const visibleGroups = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return groups
            .filter((group) => group.options?.length > 0)
            .map((group) => ({
                ...group,
                options:
                    needle === ''
                        ? group.options
                        : group.options.filter((option) => option.label.toLowerCase().includes(needle)),
            }))
            .filter((group) => group.options.length > 0);
    }, [groups, query]);

    const nothingMatches = query.trim() !== '' && visibleGroups.length === 0;
    const searching = query.trim() !== '';

    return (
        <div className="flex min-w-0 flex-col gap-3">
            {searchable && (
                <div className="relative">
                    <span
                        aria-hidden="true"
                        className="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[16px] text-slate-400"
                    >
                        search
                    </span>
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search filters"
                        aria-label="Search filter options"
                        className="h-9 w-full rounded-[3px] border border-slate-300 bg-white py-1.5 pl-8 pr-2.5 text-[13px] text-slate-700 placeholder:text-slate-400 outline-none transition-colors focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                </div>
            )}

            {nothingMatches && (
                <p className="text-[12px] text-slate-500">No filters match &ldquo;{query.trim()}&rdquo;.</p>
            )}

            <div className="flex min-w-0 flex-col gap-3">
                {visibleGroups.map((group) => {
                    const selectedForGroup = selected[group.key] ?? [];
                    // While searching, every matching group stays open so results
                    // are never hidden behind a collapsed disclosure.
                    const isCollapsed = searching ? false : Boolean(collapsed[group.key]);
                    // Any group with more than two options gets a disclosure so
                    // the popover stays short; `collapseAfter` sets which groups
                    // start closed (see the initial state above).
                    const canCollapse = (group.options?.length ?? 0) > 2;

                    return (
                        <div key={group.key} className="min-w-0">
                            {canCollapse ? (
                                <button
                                    type="button"
                                    aria-expanded={!isCollapsed}
                                    onClick={() => setCollapsed((prev) => ({ ...prev, [group.key]: !prev[group.key] }))}
                                    className="mb-1 flex w-full items-center justify-between gap-2 rounded text-left text-[10.5px] font-semibold uppercase tracking-[0.12em] text-slate-500 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                                >
                                    <span className="truncate">{group.label}</span>
                                    <span className="flex shrink-0 items-center gap-1.5">
                                        {selectedForGroup.length > 0 && (
                                            <span className="rounded bg-primary-fixed px-1.5 py-0.5 text-[10px] font-bold leading-none text-primary">
                                                {selectedForGroup.length}
                                            </span>
                                        )}
                                        <span aria-hidden="true" className="material-symbols-outlined text-[16px] leading-none">
                                            {isCollapsed ? 'expand_more' : 'expand_less'}
                                        </span>
                                    </span>
                                </button>
                            ) : null}

                            {isCollapsed ? null : (
                                <FilterCheckboxGroup
                                    label={group.label}
                                    options={group.options}
                                    selected={selectedForGroup}
                                    single={group.single}
                                    onToggle={(value) => onToggle(group.key, value)}
                                    hideHeading={canCollapse}
                                />
                            )}
                        </div>
                    );
                })}
            </div>

            <div className="mt-1 flex items-center justify-between gap-2 border-t border-slate-200 pt-2.5">
                <span className="text-[11px] font-medium text-slate-500">
                    {activeCount > 0 ? `${activeCount} active` : 'No filters applied'}
                </span>
                <button
                    type="button"
                    onClick={onClearAll}
                    disabled={activeCount === 0}
                    className="text-[11px] font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 disabled:text-slate-300 disabled:no-underline"
                >
                    Clear all
                </button>
            </div>
        </div>
    );
}
