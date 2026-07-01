import { useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';
import EmploymentAnalytics from '@/Components/Reports/EmploymentAnalytics';

export default function EmploymentSection({ employmentDistribution, employmentPositionBreakdown }) {
  const [open, setOpen] = useState(true);

  if (!employmentDistribution && !employmentPositionBreakdown) return null;

  return (
    <div className="border bg-white shadow-sm" style={{ borderColor: COLORS.border }}>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50"
      >
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Employment Analytics</h3>
        {open ? <ChevronDown className="h-3.5 w-3.5 text-slate-400" /> : <ChevronRight className="h-3.5 w-3.5 text-slate-400" />}
      </button>
      {open && (
        <div className="border-t border-[#e2e8f0] p-4">
          <EmploymentAnalytics
            employmentDistribution={employmentDistribution}
            employmentPositionBreakdown={employmentPositionBreakdown}
          />
        </div>
      )}
    </div>
  );
}
