import { MapPin } from 'lucide-react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

export default function ProvinceCityFilter({
  provinceOptions = [],
  cityOptions = [],
  province,
  city,
  onProvinceChange,
  onCityChange,
}) {
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <MapPin className="h-4 w-4 text-slate-400 shrink-0" />
      {/* Province */}
      <select
        value={province || ''}
        onChange={(e) => {
          onProvinceChange(e.target.value || null);
          onCityChange(null);
        }}
        className="h-8 rounded-[2px] bg-white px-2 text-center text-[11px] font-semibold text-slate-600 shadow-none"
        style={{ border: `1px solid ${COLORS.border}` }}
      >
        <option value="">All Provinces</option>
        {provinceOptions.map((opt) => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
      {/* City/Municipality */}
      <select
        value={city || ''}
        onChange={(e) => onCityChange(e.target.value || null)}
        className="h-8 rounded-[2px] bg-white px-2 text-center text-[11px] font-semibold text-slate-600 shadow-none"
        style={{ border: `1px solid ${COLORS.border}` }}
      >
        <option value="">All Cities</option>
        {cityOptions.map((opt) => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
    </div>
  );
}
