import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const PRESETS = [
  { value: 'snapshot', label: 'Snapshot' },
  { value: 'geography', label: 'Geography' },
  { value: 'follow-up', label: 'Follow-up' },
];

export default function ReportPresetSelector({ value = 'snapshot', onChange }) {
  return (
    <div className="inline-flex overflow-hidden rounded-[2px] divide-x divide-slate-300" style={{ border: `1px solid ${COLORS.border}` }}>
      {PRESETS.map((preset) => {
        const isActive = value === preset.value;
        return (
          <button
            key={preset.value}
            type="button"
            onClick={() => onChange(preset.value)}
            className={`h-8 px-3 text-[11px] font-semibold transition-colors ${
              isActive
                ? 'text-white'
                : 'bg-white text-slate-600 hover:bg-slate-50'
            }`}
            style={isActive ? { backgroundColor: COLORS.primary } : undefined}
          >
            {preset.label}
          </button>
        );
      })}
    </div>
  );
}
