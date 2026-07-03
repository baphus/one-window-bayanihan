import { useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { getQuickRangeDates } from '@/Components/Reports/DateRangePicker';

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
  const [quickRange, setQuickRange] = useState('1_YEAR');

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
