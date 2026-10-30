import Select from '@/Components/Select';

export default function AgencyFilter({ agencyOptions = [], agencyId, onChange }) {
  return (
    <Select aria-label="Agency" value={agencyId || ''} onChange={(event) => onChange(event.target.value || null)} className="h-9 w-40 shrink-0 rounded-md border-slate-300 bg-white py-0 pl-3 pr-8 text-sm font-medium text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
      <option value="">All agencies</option>
      {agencyOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
    </Select>
  );
}
