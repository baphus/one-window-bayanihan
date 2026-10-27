/**
 * A labelled group of checkboxes for one multi-select filter.
 *
 * Follows the visually-hidden-checkbox pattern already used in the app
 * (Case/ReviewPending.jsx) so the control keeps a native checkbox's keyboard
 * and assistive-technology behaviour while the visual box is fully styled with
 * Tailwind utilities.
 *
 * The accessible name of each checkbox is the option label alone — never the
 * label plus a count — so tests can query it by exact name.
 *
 * @param {object}   props
 * @param {string}   props.label     Group heading, e.g. "Status".
 * @param {Array<{value: string, label: string}>} props.options
 * @param {string[]} props.selected  Currently-selected values.
 * @param {function} props.onToggle  Called with the toggled option's value.
 * @param {boolean}  [props.disabled]
 */
export default function FilterCheckboxGroup({ label, options = [], selected = [], onToggle, disabled = false }) {
    if (options.length === 0) {
        return null;
    }

    const selectedSet = new Set(selected);

    return (
        <fieldset className="min-w-0 border-0 p-0 m-0">
            <legend className="block text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1.5">
                {label}
            </legend>
            <div className="flex flex-col">
                {options.map((option) => {
                    const checked = selectedSet.has(option.value);

                    return (
                        <label
                            key={option.value}
                            className={`group flex items-center gap-2 rounded-[2px] px-2 py-1.5 ${
                                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-slate-50'
                            }`}
                        >
                            <input
                                type="checkbox"
                                className="peer sr-only"
                                checked={checked}
                                disabled={disabled}
                                onChange={() => onToggle(option.value)}
                            />
                            <span
                                aria-hidden="true"
                                className={`flex h-4 w-4 shrink-0 items-center justify-center rounded-[2px] border transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-blue-900 peer-focus-visible:ring-offset-1 ${
                                    checked
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-slate-300 bg-white group-hover:border-slate-400'
                                }`}
                            >
                                {checked && (
                                    <span className="material-symbols-outlined text-[12px] leading-none">check</span>
                                )}
                            </span>
                            <span className="truncate text-[13px] font-medium text-slate-700">{option.label}</span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
