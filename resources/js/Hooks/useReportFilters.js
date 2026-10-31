import { useState, useCallback, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { getQuickRangeDates } from '@/lib/dates';

const QUICK_RANGE_OPTIONS = ['7_DAYS', '14_DAYS', '30_DAYS', '3_MONTHS', '6_MONTHS', '9_MONTHS', '1_YEAR'];

function defaultFromDateISO() {
  return getQuickRangeDates('30_DAYS').fromISO;
}

function defaultToDateISO() {
  return getQuickRangeDates('30_DAYS').toISO;
}

function buildFilterQueryString(fromDateISO, toDateISO, deps = {}) {
  const params = new URLSearchParams();
  params.set('from', fromDateISO);
  params.set('to', toDateISO);
  for (const [key, value] of Object.entries(deps)) {
    if (value != null && value !== '') {
      params.set(key, value);
    }
  }

  return params.toString();
}

function guessQuickRange(fromISO, toISO) {
  if (!fromISO || !toISO) return '30_DAYS';
  return QUICK_RANGE_OPTIONS.find((option) => {
    const range = getQuickRangeDates(option);
    return range.fromISO === fromISO && range.toISO === toISO;
  }) || 'CUSTOM';
}

/**
 * Encapsulates the repeated report filter state + explicit apply pattern.
 *
 * Filter changes are staged locally. The server is only queried when the user
 * clicks Apply, preventing normal filter exploration from flooding /reports.
 *
 * @param {string|undefined} initialFrom - Initial from date ISO string
 * @param {string|undefined} initialTo - Initial to date ISO string
 * @param {object} extraDeps - Optional reactive values to sync as URL params (e.g. { date_scope, province, city })
 * @param {object} appliedExtraDeps - Server-applied extra filter values used to detect pending changes
 * @returns {{ fromDateISO, setFromDateISO, toDateISO, setToDateISO, quickRange, setQuickRange, handleQuickRange, resetDateRange }}
 */
export function useReportFilters(initialFrom, initialTo, extraDeps = {}, appliedExtraDeps = extraDeps) {
  const [fromDateISO, setFromDateISO] = useState(
    () => initialFrom || defaultFromDateISO(),
  );
  const [toDateISO, setToDateISO] = useState(
    () => initialTo || defaultToDateISO(),
  );
  // Derive quickRange from initial dates so the active button matches the URL
  const [quickRange, setQuickRange] = useState(
    () => guessQuickRange(initialFrom, initialTo),
  );

  const filterQueryString = useMemo(
    () => buildFilterQueryString(fromDateISO, toDateISO, extraDeps),
    [fromDateISO, toDateISO, ...Object.values(extraDeps)],
  );

  const appliedQueryString = useMemo(
    () => buildFilterQueryString(
      initialFrom || defaultFromDateISO(),
      initialTo || defaultToDateISO(),
      appliedExtraDeps,
    ),
    [initialFrom, initialTo, ...Object.values(appliedExtraDeps)],
  );

  const hasPendingChanges = appliedQueryString !== filterQueryString;

  const applyFilters = useCallback(() => {
    if (!hasPendingChanges) return;

    router.get(route('reports.index') + '?' + filterQueryString, {}, { preserveState: true, replace: true });
  }, [filterQueryString, hasPendingChanges]);

  const handleQuickRange = useCallback((option) => {
    setQuickRange(option);
    if (option === 'CUSTOM') return;
    const range = getQuickRangeDates(option);
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
  }, []);

  const resetDateRange = useCallback(() => {
    const range = getQuickRangeDates('30_DAYS');
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
    setQuickRange('30_DAYS');
  }, []);

  return {
    fromDateISO, setFromDateISO,
    toDateISO, setToDateISO,
    quickRange, setQuickRange,
    handleQuickRange,
    resetDateRange,
    applyFilters,
    hasPendingChanges,
  };
}
