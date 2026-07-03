import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const TABS = [
  { value: 'all', label: 'All Data' },
  { value: 'cases', label: 'Cases' },
  { value: 'referrals', label: 'Referrals' },
  { value: 'demographics', label: 'Demographics' },
];

export default function ReportTabBar({ value = 'all', onChange }) {
  return (
    <div className="inline-flex overflow-hidden rounded-[2px] divide-x divide-slate-300" style={{ border: `1px solid ${COLORS.border}` }}>
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
                : 'bg-white text-slate-600 hover:bg-slate-50'
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
