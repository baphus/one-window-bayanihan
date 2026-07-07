import { statusColor } from './pageHeadingStyles';

// Show/hide individual statuses on a chart. Driven by the case_statuses
// reference rows so labels, order and colors never drift from the DB.
// `hidden` is an array of status slugs currently toggled off.
export default function StatusToggleChips({ statuses = [], hidden = [], onToggle }) {
  if (!statuses.length) return null;

  return (
    <div className="flex flex-wrap items-center gap-1.5">
      {statuses.map((s) => {
        const isHidden = hidden.includes(s.slug);
        const color = statusColor(s.slug, s.color);
        return (
          <button
            key={s.slug}
            type="button"
            onClick={() => onToggle?.(s.slug)}
            aria-pressed={!isHidden}
            title={isHidden ? `Show ${s.name}` : `Hide ${s.name}`}
            className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold transition ${
              isHidden
                ? 'border-slate-200 text-slate-400 line-through dark:border-slate-700 dark:text-slate-600'
                : 'border-slate-300 text-slate-700 dark:border-slate-600 dark:text-slate-200'
            }`}
          >
            <span
              className="h-2 w-2 rounded-full"
              style={{ backgroundColor: isHidden ? '#cbd5e1' : color }}
            />
            {s.name}
          </button>
        );
      })}
    </div>
  );
}
