import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const TABS = [
  { value: 'overview', label: 'Overview' },
  { value: 'performance', label: 'Performance' },
  { value: 'agencies', label: 'Agencies & Services' },
  { value: 'clients', label: 'Caseload & Clients' },
];

export default function ReportTabBar({ value = 'overview', onChange }) {
  return (
    <div
      className="inline-flex overflow-hidden rounded-[2px] divide-x divide-slate-300 dark:divide-slate-700"
      style={{ border: `1px solid ${COLORS.border}` }}
    >
      {TABS.map((tab) => {
        const isActive = value === tab.value;
        return (
          <button
            key={tab.value}
            type="button"
            onClick={() => onChange(tab.value)}
            className={`h-8 px-3 text-[11px] font-semibold transition-colors ${
              isActive
                ? 'text-white'
                : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
            }`}
            style={isActive ? { backgroundColor: COLORS.primary } : undefined}
          >
            {tab.label}
          </button>
        );
      })}
    </div>
  );
}
