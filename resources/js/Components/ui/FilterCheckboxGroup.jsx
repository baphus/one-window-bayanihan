import { useId } from 'react';

/**
 * One labelled group of checkboxes in a filter panel.
 *
 * Jira-style: a small caps group heading, then a dense single-column list of
 * checkbox rows. Keeps the native checkbox for keyboard and assistive-tech
 * behaviour (visually hidden input, styled box) so space/enter toggling and
 * screen-reader state work without re-implementing them.
 *
 * The accessible name of each checkbox is the option label alone -- never the
 * label plus a count -- so tests and users address them by name.
 *
 * @param {object}   props
 * @param {string}   props.label     Group heading, e.g. "Status".
 * @param {Array<{value: string, label: string, count?: number}>} props.options
 * @param {string[]} props.selected  Currently-selected values.
 * @param {function} props.onToggle  Called with the toggled option's value.
 * @param {boolean}  [props.disabled]
 */
export default function FilterCheckboxGroup({ label, options = [], selected = [], onToggle, disabled = false, hideHeading = false, single = false }) {
    const headingId = `filter-group-${useId().replace(/[^a-zA-Z0-9-]/g, '')}`;

    if (options.length === 0) {
        return null;
    }

    const selectedSet = new Set(selected);
    const selectedCount = options.filter((option) => selectedSet.has(option.value)).length;

    return (
        <fieldset className="min-w-0 border-0 p-0 m-0" aria-labelledby={headingId}>
            {/* A collapsible group already renders this label in its disclosure
                button, so the legend is visually hidden there but stays in the
                accessibility tree to name the fieldset. */}
            <legend
                id={headingId}
                className={
                    hideHeading
                        ? 'sr-only'
                        : 'mb-1 flex w-full items-center justify-between gap-2 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-slate-500'
                }
            >
                <span>{label}</span>
                {selectedCount > 0 && !hideHeading && (
                    <span className="rounded bg-primary-fixed px-1.5 py-0.5 text-[10px] font-bold leading-none text-primary">
                        {selectedCount}
                    </span>
                )}
            </legend>
            <div className="flex flex-col">
                {options.map((option) => {
                    const checked = selectedSet.has(option.value);

                    return (
                        <label
                            key={option.value}
                            className={`group flex items-center gap-2 rounded px-1.5 py-1 ${
                                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-slate-100'
                            }`}
                        >
                            <input
                                type={single ? 'radio' : 'checkbox'}
                                name={single ? headingId : undefined}
                                className="peer sr-only"
                                checked={checked}
                                disabled={disabled}
                                onChange={() => onToggle(option.value)}
                            />
                            <span
                                aria-hidden="true"
                                className={`flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-[3px] border transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-1 ${
                                    checked
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-slate-400 bg-white group-hover:border-slate-500'
                                } ${single ? 'rounded-full' : ''}`}
                            >
                                {checked && (
                                    <span className="material-symbols-outlined text-[11px] leading-none">check</span>
                                )}
                            </span>
                            <span className="min-w-0 flex-1 truncate text-[12.5px] text-slate-700" title={option.label}>
                                {option.label}
                            </span>
                            {Number.isFinite(Number(option.count)) && (
                                <span aria-hidden="true" className="shrink-0 text-[11px] tabular-nums text-slate-400">
                                    {option.count}
                                </span>
                            )}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
