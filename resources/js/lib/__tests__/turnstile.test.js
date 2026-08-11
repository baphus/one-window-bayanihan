import { describe, it, expect } from 'vitest';
import { getTurnstileError, TURNSTILE_MESSAGES } from '@/lib/turnstile';

describe('getTurnstileError', () => {
    it('returns null when a token is present regardless of status', () => {
        expect(getTurnstileError({ token: 'tok', status: 'idle' })).toBeNull();
        expect(getTurnstileError({ token: 'tok', status: 'loading' })).toBeNull();
        expect(getTurnstileError({ token: 'tok', status: 'expired' })).toBeNull();
        expect(getTurnstileError({ token: 'tok', status: 'error' })).toBeNull();
    });

    it('returns the idle message when the token is empty and status is idle', () => {
        expect(getTurnstileError({ token: '', status: 'idle' })).toBe(TURNSTILE_MESSAGES.idle);
    });

    it('returns the idle message when status is omitted', () => {
        expect(getTurnstileError({ token: '' })).toBe(TURNSTILE_MESSAGES.idle);
    });

    it('returns the loading message for the loading status', () => {
        expect(getTurnstileError({ token: '', status: 'loading' })).toBe(TURNSTILE_MESSAGES.loading);
    });

    it('returns the expired message for the expired status', () => {
        expect(getTurnstileError({ token: '', status: 'expired' })).toBe(TURNSTILE_MESSAGES.expired);
    });

    it('returns the error message for the error status', () => {
        expect(getTurnstileError({ token: '', status: 'error' })).toBe(TURNSTILE_MESSAGES.error);
    });

    it('falls back to the idle message for an unknown status', () => {
        expect(getTurnstileError({ token: '', status: 'unknown' })).toBe(TURNSTILE_MESSAGES.idle);
    });
});