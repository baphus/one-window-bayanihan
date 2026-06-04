import { LayoutDashboard, TrendingUp, PieChart, Activity, Award, SmilePlus, BrainCircuit, Bell } from 'lucide-react';

const TABS = [
  { id: 'executive', label: 'Executive', icon: LayoutDashboard },
  { id: 'trends', label: 'Trends', icon: TrendingUp },
  { id: 'distribution', label: 'Distribution', icon: PieChart },
  { id: 'operational', label: 'Operational', icon: Activity },
  { id: 'scorecards', label: 'Scorecards', icon: Award },
  { id: 'satisfaction', label: 'Satisfaction', icon: SmilePlus },
  { id: 'predictive', label: 'Predictive', icon: BrainCircuit },
  { id: 'alerts', label: 'Alerts', icon: Bell },
];

export default function InsightsTabNav({ activeTab, onTabChange, tabs: allowedTabs }) {
  const visibleTabs = allowedTabs
    ? TABS.filter((t) => allowedTabs.includes(t.id))
    : TABS;

  return (
    <nav className="overflow-x-auto border-b border-slate-200">
      <div className="flex min-w-max gap-0">
        {visibleTabs.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              type="button"
              onClick={() => onTabChange(tab.id)}
              className={`inline-flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-[11px] font-bold uppercase tracking-[0.11em] transition-colors ${
                isActive
                  ? 'border-blue-600 text-blue-700'
                  : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
              }`}
            >
              <Icon className="h-3.5 w-3.5" />
              {tab.label}
            </button>
          );
        })}
      </div>
    </nav>
  );
}
