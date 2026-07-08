import { useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { getQuickRangeDates } from '@/Components/Reports/DateRangePicker';

const QUICK_RANGE_OPTIONS = ['7_DAYS', '14_DAYS', '30_DAYS', '6_MONTHS', '1_YEAR'];

function guessQuickRange(fromISO, toISO) {
  if (!fromISO || !toISO) return '1_YEAR';
  const from = new Date(fromISO);
  const to = new Date(toISO);
  const diffDays = Math.round((to - from) / (1000 * 60 * 60 * 24));
  // Maximum tolerance of ±1 day for rounding errors
  if (Math.abs(diffDays - 6) <= 1) return '7_DAYS';
  if (Math.abs(diffDays - 13) <= 1) return '14_DAYS';
  if (Math.abs(diffDays - 29) <= 1) return '30_DAYS';
  if (Math.abs(diffDays - 182) <= 3) return '6_MONTHS';
  if (Math.abs(diffDays - 365) <= 3) return '1_YEAR';
  return 'CUSTOM';
}

/**
 * Encapsulates the repeated report filter state + URL sync pattern.
 *
 * @param {string|undefined} initialFrom - Initial from date ISO string
 * @param {string|undefined} initialTo - Initial to date ISO string
 * @param {object} extraDeps - Optional reactive values to sync as URL params (e.g. { date_scope, province, city })
 * @returns {{ fromDateISO, setFromDateISO, toDateISO, setToDateISO, quickRange, setQuickRange, handleQuickRange, resetDateRange }}
 */
export function useReportFilters(initialFrom, initialTo, extraDeps = {}) {
  const [fromDateISO, setFromDateISO] = useState(
    () => initialFrom || new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
  );
  const [toDateISO, setToDateISO] = useState(
    () => initialTo || new Date().toISOString().slice(0, 10),
  );
  // Derive quickRange from initial dates so the active button matches the URL
  const [quickRange, setQuickRange] = useState(
    () => guessQuickRange(initialFrom, initialTo),
  );

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('from', fromDateISO);
    params.set('to', toDateISO);
    for (const [key, value] of Object.entries(extraDeps)) {
      if (value != null && value !== '') {
        params.set(key, value);
      }
    }
    const qs = params.toString();
    const current = window.location.search.slice(1);
    if (current !== qs) {
      router.get(route('reports.index') + '?' + qs, {}, { preserveState: true, replace: true });
    }
  }, [fromDateISO, toDateISO, ...Object.values(extraDeps)]);

  const handleQuickRange = useCallback((option) => {
    setQuickRange(option);
    if (option === 'CUSTOM') return;
    const range = getQuickRangeDates(option);
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
  }, []);

  const resetDateRange = useCallback(() => {
    const range = getQuickRangeDates('1_YEAR');
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
    setQuickRange('1_YEAR');
  }, []);

  return {
    fromDateISO, setFromDateISO,
    toDateISO, setToDateISO,
    quickRange, setQuickRange,
    handleQuickRange,
    resetDateRange,
  };
}
