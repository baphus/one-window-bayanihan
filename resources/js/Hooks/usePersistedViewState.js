import { useEffect, useRef, useState } from 'react';

/**
 * Persist a page's list/grid view mode to localStorage so the user's chosen
 * layout survives navigation. Mirrors the conventions of usePersistedColumns.
 */
export function usePersistedViewMode(storageKey) {
  const [viewMode, setViewMode] = useState(() => {
    try {
      return localStorage.getItem(storageKey) || 'list';
    } catch {
      return 'list';
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem(storageKey, viewMode);
    } catch {
      /* storage unavailable — ignore */
    }
  }, [storageKey, viewMode]);

  return [viewMode, setViewMode];
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

  // Restore first: read the old saved value before the persist effect below
  // overwrites it with the current (usually empty) filter state.
  useEffect(() => {
    if (restoredRef.current) return;
    restoredRef.current = true;

    let saved = null;
    try {
      saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    } catch {
      saved = null;
    }
    if (!saved || typeof saved !== 'object' || Array.isArray(saved)) return;

    const { page: _page, per_page: _perPage, ...rest } = saved;
    const cleaned = Object.fromEntries(
      Object.entries(rest).filter(([, v]) => v != null && v !== ''),
    );
    if (JSON.stringify(cleaned) !== JSON.stringify(filters ?? {})) {
      applyFilters(cleaned);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (filters === undefined) return;
    try {
      localStorage.setItem(storageKey, JSON.stringify(filters));
    } catch {
      /* storage unavailable — ignore */
    }
  }, [storageKey, filters]);
}
