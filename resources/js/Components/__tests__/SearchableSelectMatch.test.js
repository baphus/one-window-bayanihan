import { describe, it, expect } from 'vitest';
import { matchesQuery } from '@/Components/SearchableSelect';

describe('matchesQuery', () => {
    it('matches on a plain substring', () => {
        expect(matchesQuery('City of Cebu', 'cebu')).toBe(true);
        expect(matchesQuery('Province of Cebu', 'province')).toBe(true);
    });

    it('matches the word order users actually type for PSGC cities', () => {
        // PSGC stores "City of X"; Filipinos write "X City". A plain substring
        // match returned no results for these, which blocked the address step.
        expect(matchesQuery('City of Cebu', 'Cebu City')).toBe(true);
        expect(matchesQuery('City of Mandaue', 'Mandaue City')).toBe(true);
        expect(matchesQuery('City of Lapu-Lapu', 'Lapu-Lapu City')).toBe(true);
        expect(matchesQuery('City of Davao', 'Davao City')).toBe(true);
    });

    it('matches partial tokens so results narrow while typing', () => {
        expect(matchesQuery('City of Cebu', 'Ceb')).toBe(true);
        expect(matchesQuery('City of Lapu-Lapu', 'lapu')).toBe(true);
    });

    it('does not match unrelated places', () => {
        expect(matchesQuery('City of Cebu', 'Manila')).toBe(false);
        expect(matchesQuery('City of Cebu', 'Cebu Manila')).toBe(false);
        expect(matchesQuery('Municipality of Alcoy', 'Argao')).toBe(false);
    });

    it('treats an empty query as matching everything', () => {
        expect(matchesQuery('City of Cebu', '')).toBe(true);
        expect(matchesQuery('City of Cebu', '   ')).toBe(true);
    });

    it('is not confined to place names', () => {
        expect(matchesQuery('United Arab Emirates', 'united arab')).toBe(true);
        expect(matchesQuery('Domestic Helper / Household Service Worker', 'household')).toBe(true);
    });
});
