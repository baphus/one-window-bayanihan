// Reusable segmented control to switch a chart between bar / line / pie views.
// Generalizes the inline toggle that previously only lived in TrendChart.
const LABELS = { bar: 'Bar', line: 'Line', pie: 'Pie', doughnut: 'Pie' };

export default function ChartTypeToggle({ value, onChange, types = ['bar', 'line'] }) {
  return (
    <div className="inline-flex overflow-hidden rounded-[2px] border border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-900">
      {types.map((t) => (
        <button
          key={t}
          type="button"
          onClick={() => onChange(t)}
          aria-pressed={value === t}
          className={`h-7 px-2.5 text-[11px] font-semibold transition ${
            value === t
              ? 'bg-[#0b5a8c] text-white'
              : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'
          }`}
        >
          {LABELS[t] ?? t}
        </button>
      ))}
    </div>
  );
}
