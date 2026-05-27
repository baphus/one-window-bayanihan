import { CalendarRange, RotateCcw } from 'lucide-react';
import { useMemo } from 'react';

const DAY_MS = 1000 * 60 * 60 * 24;

function toCalendarDate(isoDate) {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function addDays(date, days) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

function toISODateInputValue(date) {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getQuickRangeDates(option) {
  const toDate = new Date();
  let fromDate = new Date(toDate.getFullYear(), toDate.getMonth(), toDate.getDate());

  if (option === '7_DAYS') fromDate = addDays(toDate, -6);
  if (option === '14_DAYS') fromDate = addDays(toDate, -13);
  if (option === '30_DAYS') fromDate = addDays(toDate, -29);
  if (option === '6_MONTHS') fromDate = new Date(toDate.getFullYear(), toDate.getMonth() - 6, toDate.getDate());
  if (option === '1_YEAR') fromDate = new Date(toDate.getFullYear() - 1, toDate.getMonth(), toDate.getDate());

  return { fromISO: toISODateInputValue(fromDate), toISO: toISODateInputValue(toDate) };
}

function formatDisplayDate(isoDate) {
  if (!isoDate) return '---';
  return toCalendarDate(isoDate).toLocaleDateString('en-US', {
    month: 'short', day: '2-digit', year: 'numeric',
  });
}

export default function DateRangePicker({
  fromDateISO,
  toDateISO,
  quickRange,
  onFromChange,
  onToChange,
  onQuickRangeSelect,
  onReset,
}) {
  const currentLabel = useMemo(() => formatDisplayDate(fromDateISO) + ' - ' + formatDisplayDate(toDateISO), [fromDateISO, toDateISO]);

  const quickOptions = [
    { label: '7 Days', value: '7_DAYS' },
    { label: '14 Days', value: '14_DAYS' },
    { label: '30 Days', value: '30_DAYS' },
    { label: '6 Months', value: '6_MONTHS' },
    { label: '1 Year', value: '1_YEAR' },
    { label: 'Custom', value: 'CUSTOM' },
  ];

  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <CalendarRange className="h-4 w-4 text-slate-400 shrink-0" />
      <div className="inline-flex overflow-hidden rounded-[2px] border border-[#cbd5e1] divide-x divide-[#cbd5e1]">
        {quickOptions.map((option) => {
          const isActive = quickRange === option.value;
          return (
            <button
              key={option.value}
              type="button"
              onClick={() => onQuickRangeSelect(option.value)}
              className={`h-8 px-2.5 text-[11px] font-semibold transition-colors ${
                isActive
                  ? 'bg-[#0b5a8c] text-white'
                  : 'bg-white text-slate-600 hover:bg-slate-50'
              }`}
            >
              {option.label}
            </button>
          );
        })}
      </div>
      {quickRange === 'CUSTOM' ? (
        <>
          <input
            type="date"
            value={fromDateISO}
            onChange={(e) => {
              const next = e.target.value;
              onFromChange(next);
              if (next > toDateISO) onToChange(next);
            }}
            className="h-8 min-w-[140px] rounded-[2px] border border-[#cbd5e1] bg-white px-2 text-[13px] text-slate-700 shadow-none"
          />
          <span className="px-0.5 text-slate-400">—</span>
          <input
            type="date"
            value={toDateISO}
            onChange={(e) => {
              const next = e.target.value;
              onToChange(next);
              if (next < fromDateISO) onFromChange(next);
            }}
            className="h-8 min-w-[140px] rounded-[2px] border border-[#cbd5e1] bg-white px-2 text-[13px] text-slate-700 shadow-none"
          />
        </>
      ) : (
        <span className="text-[11px] font-medium text-slate-500">{currentLabel}</span>
      )}
      <button
        onClick={onReset}
        className="inline-flex h-8 items-center gap-1 rounded-[2px] px-2 text-[11px] font-medium text-slate-400 hover:text-slate-600 transition-colors"
      >
        <RotateCcw className="h-3.5 w-3.5" />
        Reset
      </button>
    </div>
  );
}

export { getQuickRangeDates, toCalendarDate, addDays, formatDisplayDate };
