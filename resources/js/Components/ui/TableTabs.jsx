/**
 * TableTabs — the register-style quick-filter strip shared by list pages.
 *
 * Renders the underline-tab presentation from the case-register reference while
 * keeping the semantics of a filter toggle group: these buttons swap which
 * subset of records the table shows, they do not switch tab panels, so
 * `aria-pressed` on buttons inside a labelled group is the honest contract
 * (a `tablist` would promise a panel relationship that does not exist).
 *
 * The record count renders inside the button for the visual rhythm the
 * reference uses, but it is decorative: the accessible name stays exactly the
 * filter label ("Open"), which is how callers, tests, and screen-reader users
 * address these controls. The authoritative counts remain in the footer range
 * and the stat tiles.
 *
 * @param {object} props
 * @param {Array<{key?: string, label: string, value: string, count?: number}>} props.items
 * @param {string|string[]} props.value Currently applied filter value ('' for "all").
 * @param {function} props.onSelect   (value: string) => void
 * @param {string} [props.label]      Accessible name for the group.
 * @param {string} [props.allValue]   Value that represents "no filter applied".
 */
export default function TableTabs({
  items = [],
  value = '',
  onSelect,
  label = 'Quick filters',
  allValue = '',
}) {
  if (items.length === 0) return null;

  return (
    <div
      role="group"
      aria-label={label}
      className="flex flex-wrap items-center gap-1 border-b border-slate-200"
    >
      {items.map((item) => {
        const selectedValues = (Array.isArray(value) ? value : [value]).filter(Boolean);
        const isActive = selectedValues.includes(item.value) || (selectedValues.length === 0 && item.value === allValue);
        const count = Number(item.count);
        const hasCount = Number.isFinite(count);

        return (
          <button
            key={item.key ?? item.value ?? item.label}
            type="button"
            aria-pressed={isActive}
            onClick={() => onSelect?.(item.value)}
            className={[
              '-mb-px min-h-[40px] whitespace-nowrap border-b-2 px-3 text-[13px] transition-colors',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1',
              isActive
                ? 'border-primary font-semibold text-primary'
                : 'border-transparent font-medium text-slate-500 hover:border-slate-300 hover:text-slate-700',
            ].join(' ')}
          >
            {item.label}
            {hasCount && (
              <span
                aria-hidden="true"
                className={`ml-1.5 tabular-nums ${isActive ? 'text-primary' : 'text-slate-400'}`}
              >
                {count}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
