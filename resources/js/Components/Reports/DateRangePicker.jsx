import { CalendarRange } from 'lucide-react';
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
    <div className="flex flex-wrap items-center gap-2 rounded-[2px] border border-[#cfd6de] bg-[#f7f9fc] p-2.5">
      <div className="inline-flex h-9 items-center gap-2 border border-[#cbd5e1] bg-white px-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-600">
        <CalendarRange className="h-4 w-4" />
        Date Range
      </div>
      <div className="flex flex-wrap items-center gap-1.5">
        {quickOptions.map((option) => {
          const isActive = quickRange === option.value;
          return (
            <button
              key={option.value}
              type="button"
              onClick={() => onQuickRangeSelect(option.value)}
              className={`h-8 border px-2.5 text-[10px] font-extrabold uppercase tracking-[0.08em] ${
                isActive
                  ? 'border-[#0b5a8c] bg-[#0b5a8c] text-white'
                  : 'border-[#cbd5e1] bg-white text-slate-600 hover:border-[#94a3b8]'
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
            className="h-9 w-[132px] border border-[#cbd5e1] bg-white px-2 text-[11px] font-medium text-slate-700 outline-none"
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
            className="h-9 w-[132px] border border-[#cbd5e1] bg-white px-2 text-[11px] font-medium text-slate-700 outline-none"
          />
        </>
      ) : (
        <span className="text-[11px] font-bold text-slate-600">{currentLabel}</span>
      )}
      <button onClick={onReset} className="h-9 px-2 text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-500">
        Reset
      </button>
    </div>
  );
}

export { getQuickRangeDates, toCalendarDate, addDays, formatDisplayDate };
