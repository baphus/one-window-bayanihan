import Select from '@/Components/Select';
import { formatDisplayDate } from '@/lib/utils';

const controlClass = 'h-9 shrink-0 rounded-md border border-slate-300 bg-white px-3 py-0 text-sm font-medium text-slate-700 focus:border-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200';

export default function DateRangePicker({ fromDateISO, toDateISO, quickRange, onFromChange, onToChange, onQuickRangeSelect }) {
  return (
    <div className="flex shrink-0 items-center gap-2">
      <Select aria-label="Date range" value={quickRange} onChange={(event) => onQuickRangeSelect(event.target.value)} className={`${controlClass} w-40 pr-8`}>
        <option value="7_DAYS">Last 7 days</option>
        <option value="14_DAYS">Last 14 days</option>
        <option value="30_DAYS">Last 30 days</option>
        <optgroup label="More ranges">
          <option value="3_MONTHS">Last 3 months</option>
          <option value="6_MONTHS">Last 6 months</option>
          <option value="9_MONTHS">Last 9 months</option>
          <option value="1_YEAR">Last year</option>
        </optgroup>
        <option value="CUSTOM">Custom dates</option>
      </Select>
      {quickRange === 'CUSTOM' ? (
        <>
          <input aria-label="Start date" type="date" value={fromDateISO} max={toDateISO} onChange={(event) => onFromChange(event.target.value)} className={controlClass} />
          <span className="text-sm text-slate-500">to</span>
          <input aria-label="End date" type="date" value={toDateISO} min={fromDateISO} onChange={(event) => onToChange(event.target.value)} className={controlClass} />
        </>
      ) : <span className="sr-only">{formatDisplayDate(fromDateISO)} to {formatDisplayDate(toDateISO)}</span>}
    </div>
  );
}
