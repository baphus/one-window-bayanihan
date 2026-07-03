/**
 * Suppress small counts for privacy.
 * Counts less than 5 render as "<5".
 * Does not suppress zero, null, or undefined values.
 */
export function suppressCount(value) {
  if (value === null || value === undefined) return value;
  if (typeof value === 'string') return value;
  if (value === 0) return 0;
  return value < 5 ? '<5' : value;
}
