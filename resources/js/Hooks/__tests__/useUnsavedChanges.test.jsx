import '@testing-library/jest-dom/vitest';
import { act, renderHook } from '@testing-library/react';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import useUnsavedChanges from '../useUnsavedChanges';

/**
 * The bypass is a one-shot "let the visit I am about to start through" flag.
 * It must never survive into an unrelated navigation, because the whole point
 * of the hook is that a dirty form cannot be left without a warning.
 *
 * Inertia fires `inertia:before` (cancelable) for every visit — including
 * POST/PUT — and a listener returning `false` cancels it. So the hook's
 * handler sees non-GET visits too, and the bypass must be consumed there.
 */

const flush = async () => {
  await act(async () => { await Promise.resolve(); });
};

function fireBefore(method) {
  const event = new CustomEvent('inertia:before', {
    cancelable: true,
    detail: { visit: { method, url: `/target-${method}`, data: {}, replace: false } },
  });
  act(() => { document.dispatchEvent(event); });
  return event;
}

describe('useUnsavedChanges bypass lifecycle', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('warns on a dirty ordinary GET navigation', async () => {
    const { result } = renderHook(() => useUnsavedChanges(true));
    await flush();

    const event = fireBefore('get');

    expect(event.defaultPrevented).toBe(true);
    expect(result.current.showModal).toBe(true);
  });

  it('does not warn on a clean GET navigation', async () => {
    const { result } = renderHook(() => useUnsavedChanges(false));
    await flush();

    const event = fireBefore('get');

    expect(event.defaultPrevented).toBe(false);
    expect(result.current.showModal).toBe(false);
  });

  it('lets exactly one bypassed GET through, then warns again', async () => {
    const { result } = renderHook(() => useUnsavedChanges(true));
    await flush();

    act(() => { result.current.bypassNext(); });

    const first = fireBefore('get');
    expect(first.defaultPrevented).toBe(false);

    const second = fireBefore('get');
    expect(second.defaultPrevented).toBe(true);
    expect(result.current.showModal).toBe(true);
  });

  // THE BUG: bypassNext() is called before a router.post()/put()/delete().
  // A non-GET visit used to return before the handler cleared the flag, so the
  // stale bypass was then spent on the NEXT ordinary GET — silently navigating
  // a dirty form away with no warning.
  it('consumes the bypass on a non-GET visit so a later GET still warns', async () => {
    const { result } = renderHook(() => useUnsavedChanges(true));
    await flush();

    act(() => { result.current.bypassNext(); });

    const post = fireBefore('post');
    expect(post.defaultPrevented).toBe(false);

    const laterGet = fireBefore('get');
    expect(laterGet.defaultPrevented).toBe(true);
    expect(result.current.showModal).toBe(true);
  });

  it('does not accumulate bypasses across several non-GET visits', async () => {
    const { result } = renderHook(() => useUnsavedChanges(true));
    await flush();

    act(() => { result.current.bypassNext(); });
    fireBefore('post');
    fireBefore('put');

    act(() => { result.current.bypassNext(); });
    fireBefore('delete');

    // The single flag armed after the deletes above has now been spent.
    const laterGet = fireBefore('get');
    expect(laterGet.defaultPrevented).toBe(true);
  });

  it('confirms navigation by spending the bypass on the replayed visit', async () => {
    const onDiscard = vi.fn();
    const { result } = renderHook(() => useUnsavedChanges(true, { onDiscard }));
    await flush();

    fireBefore('get');
    expect(result.current.showModal).toBe(true);

    // cancelNavigation/replay goes through router.visit; stub it so the test
    // observes the bypass contract without a real Inertia request.
    const visitSpy = vi.spyOn(router, 'visit').mockImplementation(() => {});
    act(() => { result.current.confirmNavigation(); });

    expect(onDiscard).toHaveBeenCalledTimes(1);
    expect(result.current.showModal).toBe(false);
    expect(visitSpy).toHaveBeenCalledWith('/target-get', expect.objectContaining({ method: 'get' }));

    // The replayed GET is the one the bypass is for.
    const replayed = fireBefore('get');
    expect(replayed.defaultPrevented).toBe(false);

    // ...and it is spent, so the next unrelated GET warns again.
    const after = fireBefore('get');
    expect(after.defaultPrevented).toBe(true);
  });
});
