import { useCallback } from 'react';

/**
 * Normalise a filter value into a string array.
 *
 * The backend accepts both `?status=OPEN` and `?status[]=OPEN&status[]=CLOSED`,
 * and older bookmarked URLs still use the scalar form, so every consumer has to
 * treat a string, an array and null the same way.
 *
 * @param {string|string[]|null|undefined} value
 * @returns {string[]}
 */
export function toFilterList(value) {
    if (value === null || value === undefined || value === '') {
        return [];
    }

    const raw = Array.isArray(value) ? value : [value];

    return raw.filter((item) => typeof item === 'string' && item.trim() !== '').map((item) => item.trim());
}

/**
 * Toggle a value inside a filter list, returning the new list.
 *
 * @param {string[]} list
 * @param {string} value
 * @returns {string[]}
 */
export function toggleFilterValue(list, value) {
    const current = toFilterList(list);

    return current.includes(value) ? current.filter((item) => item !== value) : [...current, value];
}

/**
 * Builders for the array-valued filter params shared by the Case, Client and
 * Referral index pages.
 *
 * `withFilter` sets one group's values and drops the key entirely when nothing
 * is selected, so an empty selection never round-trips as `status=[]` in the URL.
 *
 * @returns {{setFilter: function(string, string[]): object, toggleFilter: function(string, string): object, clearFilters: function(object): object}}
 */
export function useArrayFilters() {
    // A single representative value collapses back to the scalar form, matching
    // how the backend echoes it and keeping URLs readable for the common case.
    const encode = useCallback((values) => {
        const list = toFilterList(values);

        if (list.length === 0) {
            return undefined;
        }

        return list.length === 1 ? list[0] : list;
    }, []);

    const setFilter = useCallback(
        (params, key, values) => {
            const next = { ...params };
            const encoded = encode(values);

            if (encoded === undefined) {
                delete next[key];
            } else {
                next[key] = encoded;
            }

            next.page = undefined;

            return next;
        },
        [encode],
    );

    const toggleFilter = useCallback(
        (params, key, value) => setFilter(params, key, toggleFilterValue(params?.[key], value)),
        [setFilter],
    );

    const clearFilters = useCallback((params, keys) => {
        const next = { ...params };

        keys.forEach((key) => delete next[key]);
        next.page = undefined;

        return next;
    }, []);

    return { setFilter, toggleFilter, clearFilters };
}
