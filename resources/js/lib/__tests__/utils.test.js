import { describe, expect, it } from 'vitest';
import { getCategoryFilterIds } from '../utils';

describe('getCategoryFilterIds', () => {
  it('returns an empty array when no category filter is set', () => {
    expect(getCategoryFilterIds(undefined)).toEqual([]);
    expect(getCategoryFilterIds(null)).toEqual([]);
    expect(getCategoryFilterIds({})).toEqual([]);
    expect(getCategoryFilterIds({ status: 'OPEN' })).toEqual([]);
  });

  it('normalises a single category_id to a string array', () => {
    expect(getCategoryFilterIds({ category_id: 7 })).toEqual(['7']);
  });

  it('normalises a string id without splitting it into characters', () => {
    expect(getCategoryFilterIds({ category_id: 'abc' })).toEqual(['abc']);
  });

  it('returns every id of a category_ids selection as strings', () => {
    expect(getCategoryFilterIds({ category_ids: [1, '2', 3] })).toEqual(['1', '2', '3']);
  });

  it('merges a legacy category_id alongside a category_ids selection', () => {
    expect(getCategoryFilterIds({ category_ids: ['1'], category_id: '5' })).toEqual(['1', '5']);
  });

  it('de-duplicates an id present in both keys', () => {
    expect(getCategoryFilterIds({ category_ids: ['5'], category_id: '5' })).toEqual(['5']);
    expect(getCategoryFilterIds({ category_ids: [5], category_id: 5 })).toEqual(['5']);
  });

  it('prefers category_ids over category_id when only one is needed', () => {
    // The array key is the current shape; the scalar key is only a legacy link.
    expect(getCategoryFilterIds({ category_ids: [], category_id: '9' })).toEqual(['9']);
  });

  it('drops null, undefined and empty-string ids', () => {
    expect(getCategoryFilterIds({ category_ids: [null, undefined, '', '4'] })).toEqual(['4']);
    expect(getCategoryFilterIds({ category_id: '' })).toEqual([]);
  });
});
