import { useState, useCallback } from 'react';

const STORAGE_PREFIX = 'owb-columns';

/**
 * usePersistedColumns — useState for column visibility that persists to localStorage.
 *
 * @param {string}   pageKey      — unique key for this table (e.g. 'users', 'cases')
 * @param {string[]} defaultKeys  — default visible column keys from COLUMN_DEFS
 * @returns {[string[], Function]} — same API as useState
 */
export default function usePersistedColumns(pageKey, defaultKeys) {
  const storageKey = `${STORAGE_PREFIX}_${pageKey}`;

  const [visibleColumns, _setVisibleColumns] = useState(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch {
      // corrupted data — fall through to default
    }
    return defaultKeys;
  });

  const setVisibleColumns = useCallback((value) => {
    _setVisibleColumns((prev) => {
      const next = typeof value === 'function' ? value(prev) : value;
      try {
        localStorage.setItem(storageKey, JSON.stringify(next));
      } catch {
        // quota exceeded — silently ignore
      }
      return next;
    });
  }, [storageKey]);

  return [visibleColumns, setVisibleColumns];
}
