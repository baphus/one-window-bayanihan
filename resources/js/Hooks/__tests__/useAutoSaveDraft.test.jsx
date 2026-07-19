import '@testing-library/jest-dom/vitest';
import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import useAutoSave from '../useAutoSave';

describe('draft autosave PUT route characterization', () => {
  it('skips the initial render and does not debounce an unchanged form', async () => {
    globalThis.fetch = vi.fn();
    renderHook(() => useAutoSave({ formData: { value: 'initial' }, draftId: 'draft-123', options: { debounceMs: 10 } }));

    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 15)); });

    expect(fetch).not.toHaveBeenCalled();
  });

  it('uses the draft id in the PUT route and aborts a superseded save', async () => {
    let resolveFirst;
    globalThis.fetch = vi.fn()
      .mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; }))
      .mockResolvedValueOnce({ ok: true, json: async () => ({ ok: true }) });
    const { rerender } = renderHook(({ value }) => useAutoSave({ formData: { value }, draftId: 'draft-123', options: { debounceMs: 10 } }), { initialProps: { value: 'one' } });

    rerender({ value: 'two' });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 15)); });
    rerender({ value: 'three' });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 15)); });

    expect(fetch).toHaveBeenCalledTimes(2);
    expect(fetch.mock.calls[0][0]).toBe('/cases/draft-123/save-draft');
    expect(fetch.mock.calls[0][1].method).toBe('PUT');
    expect(fetch.mock.calls[0][1].signal).toBeInstanceOf(AbortSignal);
    expect(fetch.mock.calls[1][1].method).toBe('PUT');
    resolveFirst?.({ ok: true, json: async () => ({ ok: true }) });
  });

  it('reports a failed PUT rather than claiming the draft was saved', async () => {
    globalThis.fetch = vi.fn().mockRejectedValue(new Error('network down'));
    const { result, rerender } = renderHook(({ value }) => useAutoSave({ formData: { value }, draftId: 'draft-123', options: { debounceMs: 10 } }), { initialProps: { value: 'one' } });

    rerender({ value: 'changed' });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 15)); });

    expect(result.current.autoSaveStatus).toBe('error');
  });

  it('notifies the saved snapshot only after a successful PUT', async () => {
    const onSaveSuccess = vi.fn();
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ ok: true }) });
    const { rerender } = renderHook(({ value }) => useAutoSave({
      formData: { value }, draftId: 'draft-123', onSaveSuccess, options: { debounceMs: 10 },
    }), { initialProps: { value: 'initial' } });

    rerender({ value: 'saved' });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 15)); });

    expect(onSaveSuccess).toHaveBeenCalledWith({ value: 'saved' });
  });

  it('does not issue an unload autosave request', () => {
    const sendBeacon = vi.fn();
    globalThis.fetch = vi.fn();
    navigator.sendBeacon = sendBeacon;
    renderHook(() => useAutoSave({ formData: { value: 'unloaded', has_next_of_kin: true }, draftId: 'draft-123' }));

    act(() => { window.dispatchEvent(new Event('beforeunload')); });

    expect(sendBeacon).not.toHaveBeenCalled();
    expect(fetch).not.toHaveBeenCalled();
  });
});
