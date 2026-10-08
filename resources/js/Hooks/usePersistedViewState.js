import { useEffect, useRef } from 'react';
import useLocalStorage, { readStoredValue, writeStoredValue } from './useLocalStorage';

/**
 * Persist a page's list/grid view mode to localStorage so the user's chosen
 * layout survives navigation. Mirrors the conventions of usePersistedColumns.
 */
export function usePersistedViewMode(storageKey) {
  return useLocalStorage(storageKey, 'list');
}

/**
 * Persist a page's server-driven filter state to localStorage and restore it
 * on first mount. `applyFilters` is the page's own navigation call (its
 * `updateTable`), invoked with the saved params so the server re-renders the
 * list with the previously active filters. `page`/`per_page` are dropped on
 * restore so the user lands on the default first page.
 */
export function usePersistedFilters(storageKey, filters, applyFilters) {
  const restoredRef = useRef(false);
  // Latest-callback refs: the restore below must run exactly once on mount
  // (it reads pre-persist storage before the persist effect overwrites it),
  // so it can't list the per-render `filters`/`applyFilters` values as deps.
  // The refs preserve that mount-once behavior without a lint suppression.
  const filtersRef = useRef(filters);
  filtersRef.current = filters;
  const applyFiltersRef = useRef(applyFilters);
  applyFiltersRef.current = applyFilters;

  // Restore first: read the old saved value before the persist effect below
  // overwrites it with the current (usually empty) filter state.
  useEffect(() => {
    if (restoredRef.current) return;
    restoredRef.current = true;

    const saved = readStoredValue(storageKey, null);
    if (!saved || typeof saved !== 'object' || Array.isArray(saved)) return;

    const { page: _page, per_page: _perPage, ...rest } = saved;
    const cleaned = Object.fromEntries(
      Object.entries(rest).filter(([, v]) => v != null && v !== ''),
    );
    if (JSON.stringify(cleaned) !== JSON.stringify(filtersRef.current ?? {})) {
      applyFiltersRef.current(cleaned);
    }
  }, [storageKey]);

  useEffect(() => {
    if (filters === undefined) return;
    writeStoredValue(storageKey, filters);
  }, [storageKey, filters]);
}
