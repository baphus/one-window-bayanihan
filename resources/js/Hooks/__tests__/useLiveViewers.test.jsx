import { act, renderHook } from '@testing-library/react';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import useLiveViewers, { normalizeViewers } from '../useLiveViewers';

vi.mock('axios', () => ({ default: { post: vi.fn(), isCancel: vi.fn(() => false) } }));

describe('live viewers', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        Object.defineProperty(document, 'hidden', { configurable: true, value: false });
        axios.post.mockReset();
    });
    afterEach(() => vi.useRealTimers());

    it('deduplicates viewers by id', () => {
        expect(normalizeViewers([{ id: '1', name: 'Ada' }, { id: '1', name: 'Ada again' }, { id: '2', name: 'Lin' }])).toHaveLength(2);
    });

    it('polls immediately and every 20 seconds; denial clears and stops retries', async () => {
        axios.post.mockResolvedValueOnce({ data: { viewers: [{ id: '1', name: 'Ada' }] } });
        const { result } = renderHook(() => useLiveViewers('/cases/1/presence'));
        await act(async () => {});
        expect(result.current).toHaveLength(1);
        expect(axios.post).toHaveBeenCalledTimes(1);
        axios.post.mockRejectedValueOnce({ response: { status: 403 } });
        await act(async () => { await vi.advanceTimersByTimeAsync(20_000); });
        expect(result.current).toEqual([]);
        await act(async () => { await vi.advanceTimersByTimeAsync(40_000); });
        expect(axios.post).toHaveBeenCalledTimes(2);
    });

    it('pauses while hidden, resumes immediately and aborts on unmount', async () => {
        const pending = [];
        axios.post.mockImplementation((_url, _body, options) => new Promise((resolve) => pending.push({ resolve, options })));
        const { unmount } = renderHook(() => useLiveViewers('/referrals/1/presence'));
        expect(axios.post).toHaveBeenCalledTimes(1);
        await act(async () => { pending[0].resolve({ data: { viewers: [] } }); });
        Object.defineProperty(document, 'hidden', { configurable: true, value: true });
        act(() => document.dispatchEvent(new Event('visibilitychange')));
        await act(async () => { await vi.advanceTimersByTimeAsync(40_000); });
        expect(axios.post).toHaveBeenCalledTimes(1);
        Object.defineProperty(document, 'hidden', { configurable: true, value: false });
        act(() => document.dispatchEvent(new Event('visibilitychange')));
        expect(axios.post).toHaveBeenCalledTimes(2);
        unmount();
        expect(pending[1].options.signal.aborted).toBe(true);
    });

    it('clears stale roster after lease TTL', async () => {
        axios.post.mockResolvedValueOnce({ data: { viewers: [{ id: '1', name: 'Ada' }] } })
            .mockRejectedValue({ response: { status: 500 } });
        const { result } = renderHook(() => useLiveViewers('/cases/1/presence'));
        await act(async () => {});
        expect(result.current).toHaveLength(1);
        await act(async () => { await vi.advanceTimersByTimeAsync(60_000); });
        expect(result.current).toEqual([]);
    });
});
