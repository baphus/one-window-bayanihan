import { useEffect } from 'react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';
import { usePage } from '@inertiajs/react';

const ALL_TABS = [
  { value: 'overview', label: 'Overview' },
  { value: 'performance', label: 'Performance' },
  { value: 'agencies', label: 'Agencies & Services' },
  { value: 'clients', label: 'Caseload & Clients' },
];

const AGENCY_TABS = ALL_TABS.filter(
  (tab) => tab.value === 'overview' || tab.value === 'performance',
);

function getTabsForRole(role, roles) {
  return role === roles.AGENCY ? AGENCY_TABS : ALL_TABS;
}

export default function ReportTabBar({ value = 'overview', onChange, role }) {
  const roles = usePage().props.roles;
  const tabs = getTabsForRole(role, roles);

  useEffect(() => {
    const isValid = tabs.some((tab) => tab.value === value);
    if (!isValid) {
      onChange('overview');
    }
  }, [value, tabs, onChange]);

  return (
    <div
      className="inline-flex overflow-hidden rounded-[2px] divide-x divide-slate-300 dark:divide-slate-700"
      style={{ border: `1px solid ${COLORS.border}` }}
    >
      {tabs.map((tab) => {
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
