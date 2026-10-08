import useLocalStorage from './useLocalStorage';

const STORAGE_PREFIX = 'owb-columns';

/**
 * usePersistedColumns — useState for column visibility that persists to localStorage.
 *
 * @param {string}   pageKey      — unique key for this table (e.g. 'users', 'cases')
 * @param {string[]} defaultKeys  — default visible column keys from COLUMN_DEFS
 * @returns {[string[], Function]} — same API as useState
 */
export default function usePersistedColumns(pageKey, defaultKeys) {
  return useLocalStorage(
    `${STORAGE_PREFIX}_${pageKey}`,
    defaultKeys,
    (parsed) => Array.isArray(parsed) && parsed.length > 0,
  );
}
