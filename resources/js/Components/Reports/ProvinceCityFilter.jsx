import Select from '@/Components/Select';

const controlClass = 'h-9 w-40 shrink-0 rounded-md border-slate-300 bg-white py-0 pl-3 pr-8 text-sm font-medium text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200';

export default function ProvinceCityFilter({ provinceOptions = [], cityOptions = [], province, city, onProvinceChange, onCityChange }) {
  return (
    <div className="flex shrink-0 items-center gap-2">
      <Select aria-label="Province" value={province || ''} onChange={(event) => { onProvinceChange(event.target.value || null); onCityChange(null); }} className={controlClass}>
        <option value="">All provinces</option>
        {provinceOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </Select>
      {province && <Select aria-label="City or municipality" value={city || ''} onChange={(event) => onCityChange(event.target.value || null)} className={controlClass}>
        <option value="">All cities</option>
        {cityOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </Select>}
    </div>
  );
}
