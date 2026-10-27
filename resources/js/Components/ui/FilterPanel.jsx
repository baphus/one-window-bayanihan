import { useMemo, useState } from 'react';
import FilterCheckboxGroup from './FilterCheckboxGroup';

/**
 * Shared shell for a multi-select filter panel.
 *
 * Pages describe their groups as data (`groups`) so this component can apply
 * the in-panel search across every option list without each page re-implementing
 * it. The footer reports how many filter values are applied and offers a single
 * "Clear all" action.
 *
 * A group is skipped entirely when it has no options, and its options are hidden
 * by the search box while the group keeps its heading so the layout stays stable.
 *
 * @param {object}   props
 * @param {Array<{key: string, label: string, options: Array<{value: string, label: string}>}>} props.groups
 * @param {object}   props.selected          Map of group key → selected values.
 * @param {function} props.onToggle          (groupKey, value) => void
 * @param {function} props.onClearAll
 * @param {number}   props.activeCount       Number of applied filter values.
 * @param {boolean}  [props.searchable]
 */
export default function FilterPanel({ groups = [], selected = {}, onToggle, onClearAll, activeCount = 0, searchable = false }) {
    const [query, setQuery] = useState('');

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
            }));
    }, [groups, query]);

    const nothingMatches = query.trim() !== '' && visibleGroups.every((group) => group.options.length === 0);

    return (
        <div className="space-y-4">
            {searchable && (
                <div className="relative">
                    <span className="material-symbols-outlined pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[16px] text-slate-400">
                        search
                    </span>
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search filters…"
                        aria-label="Search filter options"
                        className="w-full rounded-[2px] border border-slate-300 py-1.5 pl-8 pr-2 text-[12px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                    />
                </div>
            )}

            {nothingMatches && (
                <p className="text-[12px] font-medium text-slate-400">No filters match "{query.trim()}".</p>
            )}

            {visibleGroups.map((group) => (
                <FilterCheckboxGroup
                    key={group.key}
                    label={group.label}
                    options={group.options}
                    selected={selected[group.key] ?? []}
                    onToggle={(value) => onToggle(group.key, value)}
                />
            ))}

            <div className="flex items-center justify-between border-t border-slate-200 pt-3">
                <span className="text-[11px] font-bold uppercase tracking-widest text-slate-500">
                    {activeCount > 0 ? `${activeCount} active` : 'No filters'}
                </span>
                <button
                    type="button"
                    onClick={onClearAll}
                    disabled={activeCount === 0}
                    className="text-[11px] font-bold uppercase tracking-widest text-blue-900 hover:underline disabled:text-slate-300 disabled:no-underline"
                >
                    Clear all
                </button>
            </div>
        </div>
    );
}
