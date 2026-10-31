import { afterEach, describe, expect, it, vi } from 'vitest';
import { getQuickRangeDates } from '../dates';

afterEach(() => vi.useRealTimers());

describe('report rolling ranges', () => {
  it('includes today in 7, 14 and 30 day ranges', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 9, 31, 12));
    expect(getQuickRangeDates('7_DAYS')).toEqual({ fromISO: '2026-10-25', toISO: '2026-10-31' });
    expect(getQuickRangeDates('14_DAYS').fromISO).toBe('2026-10-18');
    expect(getQuickRangeDates('30_DAYS').fromISO).toBe('2026-10-02');
  });
  it('clamps month ends rather than rolling into the next month', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 4, 31, 12));
    expect(getQuickRangeDates('3_MONTHS').fromISO).toBe('2026-02-28');
    expect(getQuickRangeDates('6_MONTHS').fromISO).toBe('2025-11-30');
    expect(getQuickRangeDates('9_MONTHS').fromISO).toBe('2025-08-31');
    expect(getQuickRangeDates('1_YEAR').fromISO).toBe('2025-05-31');
  });
});
