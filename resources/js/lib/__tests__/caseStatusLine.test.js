import { describe, it, expect } from 'vitest';
import { buildCaseStatusLine, NEUTRAL_STATUS_LINE } from '@/lib/caseStatusLine';

describe('buildCaseStatusLine', () => {
    it('explains that a submitted case is with a case manager', () => {
        expect(buildCaseStatusLine({ status: 'BEING_REVIEWED' }))
            .toBe('Your case is being reviewed by a case manager.');
    });

    it('never reports a pending-review case as unavailable', () => {
        const line = buildCaseStatusLine({ status: 'BEING_REVIEWED' });

        expect(line).not.toMatch(/unavailable/i);
        expect(line).not.toBe(NEUTRAL_STATUS_LINE);
    });

    it('points an unassigned in-progress case at upcoming referrals', () => {
        expect(buildCaseStatusLine({ status: 'IN_PROGRESS', totalAgencies: 0 }))
            .toBe('Your case is being reviewed. Referrals to partner offices will appear here.');
    });

    it('counts completed partner offices for a multi-office case', () => {
        expect(buildCaseStatusLine({ status: 'IN_PROGRESS', totalAgencies: 3, completedAgencies: 2 }))
            .toBe('In progress — 2 of 3 partner offices have completed their part.');
    });

    it('uses singular wording for a single partner office', () => {
        expect(buildCaseStatusLine({ status: 'IN_PROGRESS', totalAgencies: 1, completedAgencies: 1 }))
            .toBe('In progress — 1 of 1 partner office has completed their part.');
    });

    it('notes rejected partner offices in the plural and the singular', () => {
        expect(buildCaseStatusLine({ status: 'RESOLVED', rejectedCount: 2 }))
            .toBe('This case has been resolved. 2 offices were unable to assist.');
        expect(buildCaseStatusLine({ status: 'RESOLVED', rejectedCount: 1 }))
            .toBe('This case has been resolved. 1 office was unable to assist.');
    });

    it('omits the rejection note when nothing was rejected', () => {
        expect(buildCaseStatusLine({ status: 'RESOLVED', rejectedCount: 0 }))
            .toBe('This case has been resolved.');
    });

    it('describes a draft case as still being prepared', () => {
        expect(buildCaseStatusLine({ status: 'BEING_PREPARED' }))
            .toBe('This case is still being prepared by the One Window Bayanihan team.');
    });

    it('states plainly that an archived case is archived', () => {
        expect(buildCaseStatusLine({ status: 'ARCHIVED' })).toBe('This case has been archived.');
    });

    it('falls back to a neutral sentence for an unrecognized status', () => {
        expect(buildCaseStatusLine({ status: 'SOME_FUTURE_STATUS' })).toBe(NEUTRAL_STATUS_LINE);
    });

    it('falls back to the neutral sentence when the status is missing', () => {
        expect(buildCaseStatusLine({})).toBe(NEUTRAL_STATUS_LINE);
        expect(buildCaseStatusLine()).toBe(NEUTRAL_STATUS_LINE);
    });

    it('never renders an implied outage for an unrecognized status', () => {
        expect(buildCaseStatusLine({ status: 'UNKNOWN' })).not.toMatch(/unavailable/i);
    });
});
