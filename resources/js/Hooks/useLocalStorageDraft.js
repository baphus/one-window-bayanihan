const STORAGE_KEY_PREFIX = 'owb_draft_backup';

/** Remove every draft backup for every user — call on logout. */
export function clearAllDraftBackups() {
  try {
    const prefix = `${STORAGE_KEY_PREFIX}_`;
    const keys = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key && key.startsWith(prefix)) keys.push(key);
    }
    keys.forEach((key) => localStorage.removeItem(key));
  } catch {
    // Best-effort cleanup
  }
}
