import { useState, useCallback } from 'react';

/** Read a JSON value from localStorage, falling back when missing/corrupt. */
export function readStoredValue(storageKey, fallback) {
  try {
    const raw = localStorage.getItem(storageKey);
    if (raw === null) return fallback;
    const parsed = JSON.parse(raw);
    return parsed ?? fallback;
  } catch {
    return fallback;
  }
}

/** Write a JSON value to localStorage; quota/unavailable storage is ignored. */
export function writeStoredValue(storageKey, value) {
  try {
    localStorage.setItem(storageKey, JSON.stringify(value));
  } catch {
    // quota exceeded or storage unavailable — silently ignore
  }
}

/**
 * useLocalStorage — useState persisted to localStorage as JSON.
 *
 * @param {string} storageKey — full localStorage key (callers own prefixing)
 * @param {any|Function} defaultValue — fallback when nothing valid is stored
 * @param {(parsed: any) => boolean} [isValid] — extra guard for stored values
 * @returns {[any, Function]} — same API as useState
 */
export default function useLocalStorage(storageKey, defaultValue, isValid) {
  const [value, _setValue] = useState(() => {
    const fallback = typeof defaultValue === 'function' ? defaultValue() : defaultValue;
    const stored = readStoredValue(storageKey, fallback);
    return isValid && !isValid(stored) ? fallback : stored;
  });

  const setValue = useCallback((next) => {
    _setValue((prev) => {
      const resolved = typeof next === 'function' ? next(prev) : next;
      if (isValid && !isValid(resolved)) return resolved;
      writeStoredValue(storageKey, resolved);
      return resolved;
    });
  }, [storageKey, isValid]);

  return [value, setValue];
}
