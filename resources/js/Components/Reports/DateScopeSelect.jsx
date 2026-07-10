import { CalendarDays } from 'lucide-react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const SCOPE_OPTIONS = [
  { value: 'case_created_at', label: 'Case Created Date' },
  { value: 'referral_created_at', label: 'Referral Created Date' },
  { value: 'referral_updated_at', label: 'Referral Updated Date' },
];

export default function DateScopeSelect({ value = 'case_created_at', onChange }) {
  return (
    <div className="inline-flex items-center gap-1.5">
      <CalendarDays className="h-4 w-4 text-slate-400 shrink-0" />
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="h-8 rounded-[2px] bg-white pl-2.5 pr-6 text-left text-[11px] font-semibold text-slate-600 shadow-none appearance-none"
        style={{ border: `1px solid ${COLORS.border}` }}
      >
        {SCOPE_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
    </div>
  );
}
