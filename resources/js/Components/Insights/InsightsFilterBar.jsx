import { useMemo } from 'react';
import { RotateCcw } from 'lucide-react';

function toISODateInputValue(date) {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const PRESETS = [
  { label: '7D', days: -6 },
  { label: '30D', days: -29 },
  { label: '90D', days: -89 },
  { label: '6M', months: -6 },
  { label: '1Y', years: -1 },
  { label: 'Custom', custom: true },
];

function computePresetRange(preset) {
  const to = new Date();
  const from = new Date();
  if (preset.days) from.setDate(from.getDate() + preset.days);
  if (preset.months) from.setMonth(from.getMonth() + preset.months);
  if (preset.years) from.setFullYear(from.getFullYear() + preset.years);
  return { fromISO: toISODateInputValue(from), toISO: toISODateInputValue(to) };
}

const CLIENT_TYPE_OPTIONS = [
  { label: 'All', value: '' },
  { label: 'OFW', value: 'OFW' },
  { label: 'NOK', value: 'NOK' },
];

export default function InsightsFilterBar({
  fromDate,
  toDate,
  onFromChange,
  onToChange,
  activePreset,
  onPresetChange,
  onReset,
  agencies,
  agency,
  onAgencyChange,
  categories,
  category,
  onCategoryChange,
  caseManagers,
  caseManager,
  onCaseManagerChange,
  clientType,
  onClientTypeChange,
}) {
  const isCustom = activePreset === 'Custom';

  const handlePreset = (preset) => {
    if (preset.custom) {
      onPresetChange('Custom');
      return;
    }
    const range = computePresetRange(preset);
    onPresetChange(preset.label);
    onFromChange(range.fromISO);
    onToChange(range.toISO);
  };

  const displayLabel = useMemo(() => {
    if (!fromDate || !toDate) return '';
    const f = new Date(fromDate + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    const t = new Date(toDate + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    return `${f} — ${t}`;
  }, [fromDate, toDate]);

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="inline-flex overflow-hidden rounded border border-[#cbd5e1] divide-x divide-[#cbd5e1]">
        {PRESETS.map((preset) => {
          const isActive = activePreset === preset.label;
          return (
            <button
              key={preset.label}
              type="button"
              onClick={() => handlePreset(preset)}
              className={`h-7 px-2.5 text-[10px] font-extrabold uppercase tracking-[0.11em] transition-colors ${
                isActive
                  ? 'bg-[#0b5a8c] text-white'
                  : 'bg-white text-slate-500 hover:bg-slate-50'
              }`}
            >
              {preset.label}
            </button>
          );
        })}
      </div>

      {isCustom && (
        <div className="flex items-center gap-1.5">
          <input
            type="date"
            value={fromDate}
            onChange={(e) => {
              const next = e.target.value;
              onFromChange(next);
              if (next > toDate) onToChange(next);
            }}
            className="h-7 w-36 rounded border border-[#cbd5e1] bg-white px-2 text-[11px] text-slate-700"
          />
          <span className="text-[10px] text-slate-400">—</span>
          <input
            type="date"
            value={toDate}
            onChange={(e) => {
              const next = e.target.value;
              onToChange(next);
              if (next < fromDate) onFromChange(next);
            }}
            className="h-7 w-36 rounded border border-[#cbd5e1] bg-white px-2 text-[11px] text-slate-700"
          />
        </div>
      )}

      {!isCustom && displayLabel && (
        <span className="text-[10px] font-semibold text-slate-500">{displayLabel}</span>
      )}

      {agencies?.length > 0 && (
        <select
          value={agency}
          onChange={(e) => onAgencyChange(e.target.value)}
          className="h-7 rounded border border-[#cbd5e1] bg-white px-2 text-[11px] text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#0b5a8c]"
        >
          <option value="">All Agencies</option>
          {agencies.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
      )}

      {categories?.length > 0 && (
        <select
          value={category}
          onChange={(e) => onCategoryChange(e.target.value)}
          className="h-7 rounded border border-[#cbd5e1] bg-white px-2 text-[11px] text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#0b5a8c]"
        >
          <option value="">All Categories</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      )}

      {caseManagers?.length > 0 && (
        <select
          value={caseManager}
          onChange={(e) => onCaseManagerChange(e.target.value)}
          className="h-7 rounded border border-[#cbd5e1] bg-white px-2 text-[11px] text-slate-700 focus:outline-none focus:ring-1 focus:ring-[#0b5a8c]"
        >
          <option value="">All Case Managers</option>
          {caseManagers.map((cm) => (
            <option key={cm.id} value={cm.id}>{cm.name}</option>
          ))}
        </select>
      )}

      <div className="inline-flex overflow-hidden rounded border border-[#cbd5e1] divide-x divide-[#cbd5e1]">
        {CLIENT_TYPE_OPTIONS.map((opt) => {
          const isActive = clientType === opt.value;
          return (
            <button
              key={opt.label}
              type="button"
              onClick={() => onClientTypeChange(opt.value)}
              className={`h-7 px-2.5 text-[10px] font-extrabold uppercase tracking-[0.11em] transition-colors ${
                isActive
                  ? 'bg-[#0b5a8c] text-white'
                  : 'bg-white text-slate-500 hover:bg-slate-50'
              }`}
            >
              {opt.label}
            </button>
          );
        })}
      </div>

      <button
        type="button"
        onClick={() => {
          onAgencyChange?.('');
          onCategoryChange?.('');
          onCaseManagerChange?.('');
          onClientTypeChange?.('');
          onReset();
        }}
        className="inline-flex h-7 items-center gap-1 rounded px-2 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-400 hover:text-slate-600 transition-colors"
      >
        <RotateCcw className="h-3 w-3" />
        Reset
      </button>
    </div>
  );
}
