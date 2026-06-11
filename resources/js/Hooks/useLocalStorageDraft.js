import { useEffect, useRef, useState, useCallback } from 'react';

const STORAGE_KEY_PREFIX = 'owb_draft_backup';
const BACKUP_VERSION = 1;

/**
 * useLocalStorageDraft — localStorage backup of draft form data.
 *
 * Acts as an offline fallback when server auto-save fails. Saves form data
 * debounced (1000ms after last change) with version info for future schema
 * migration. Catches QuotaExceededError gracefully without crashing.
 *
 * @param {Object}        options
 * @param {Object|null}   options.formData  — The useForm data object
 * @param {string|null}   options.userId    — User ID for storage key scoping
 * @param {boolean}       [options.enabled=true] — Disable during publish flow
 * @returns {{ hasLocalBackup: boolean, localBackup: Object|null, clearLocalBackup: Function }}
 */
export default function useLocalStorageDraft({ formData, userId, enabled = true }) {
  const [hasLocalBackup, setHasLocalBackup] = useState(false);
  const [localBackup, setLocalBackup] = useState(null);
  const debounceRef = useRef(null);
  const isFirstRender = useRef(true);

  const storageKey = `${STORAGE_KEY_PREFIX}_${userId || 'anonymous'}`;

  // Check existing backup on mount
  useEffect(() => {
    if (!enabled) return;
    try {
      const raw = localStorage.getItem(storageKey);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && parsed.version === BACKUP_VERSION && parsed.data) {
          setHasLocalBackup(true);
          setLocalBackup(parsed);
        }
      }
    } catch (e) {
      // Corrupted data — silently remove and ignore
      console.warn('Corrupted localStorage draft backup — clearing entry');
      try {
        localStorage.removeItem(storageKey);
      } catch (_) {
        // Best-effort cleanup
      }
    }
  }, [storageKey, enabled]);

  // Debounced save on form data change
  useEffect(() => {
    if (!enabled) return;
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }

    // Don't save empty forms
    const hasData =
      formData?.client?.first_name?.length > 0 ||
      formData?.client?.last_name?.length > 0;
    if (!hasData) return;

    if (debounceRef.current) clearTimeout(debounceRef.current);

    debounceRef.current = setTimeout(() => {
      try {
        const backup = {
          version: BACKUP_VERSION,
          savedAt: new Date().toISOString(),
          data: formData,
        };
        localStorage.setItem(storageKey, JSON.stringify(backup));
        setHasLocalBackup(true);
        setLocalBackup(backup);
      } catch (e) {
        if (e.name === 'QuotaExceededError' || e.code === 22) {
          console.warn('localStorage quota exceeded — backup skipped');
        } else {
          console.error('localStorage write failed:', e);
        }
      }
    }, 1000);

    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [formData, storageKey, enabled]);

  const clearLocalBackup = useCallback(() => {
    try {
      localStorage.removeItem(storageKey);
      setHasLocalBackup(false);
      setLocalBackup(null);
    } catch (e) {
      console.error('Failed to clear localStorage backup:', e);
    }
  }, [storageKey]);

  return {
    hasLocalBackup,
    localBackup,
    clearLocalBackup,
  };
}
