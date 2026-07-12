import { Building2 } from 'lucide-react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

export default function AgencyFilter({ agencyOptions = [], agencyId, onChange }) {
  return (
    <div className="inline-flex items-center gap-1.5">
      <Building2 className="h-4 w-4 shrink-0 text-slate-400" />
      <select
        value={agencyId || ''}
        onChange={(e) => onChange(e.target.value || null)}
        className="h-8 rounded-[2px] bg-white py-1 pl-2.5 pr-6 text-left text-[11px] font-semibold text-slate-600 shadow-none appearance-none"
        style={{ border: `1px solid ${COLORS.border}` }}
      >
        <option value="">All Agencies</option>
        {agencyOptions.map((opt) => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
    </div>
  );
}
