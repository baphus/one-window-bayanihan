import React from 'react';
import { useLazyProp } from '@/Hooks/useLazyProp';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import EmploymentAnalytics from '@/Components/Reports/EmploymentAnalytics';
import { ChevronDown, ChevronRight } from 'lucide-react';

function SectionAccordion({ title, defaultOpen = false, children }) {
  const [open, setOpen] = React.useState(defaultOpen);
  return (
    <div className="border bg-white shadow-sm" style={{ borderColor: '#e2e8f0' }}>
      <button type="button" onClick={() => setOpen(!open)}
        className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50">
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{title}</h3>
        {open ? <ChevronDown className="h-3.5 w-3.5 text-slate-400" /> : <ChevronRight className="h-3.5 w-3.5 text-slate-400" />}
      </button>
      {open && <div className="border-t border-slate-200 p-4">{children}</div>}
    </div>
  );
}

export default function EmploymentSection() {
  const [employmentDistribution, employmentLoading, employmentError] = useLazyProp('employmentDistribution');
  const [employmentPositionBreakdown, positionLoading, positionError] = useLazyProp('employmentPositionBreakdown');

  const isLoading = employmentLoading || positionLoading;

  return (
    <SectionAccordion title="Employment Analytics" defaultOpen>
      {isLoading ? (
        <ChartSkeleton />
      ) : (
        <EmploymentAnalytics
          employmentDistribution={employmentDistribution}
          employmentPositionBreakdown={employmentPositionBreakdown}
        />
      )}
    </SectionAccordion>
  );
}
