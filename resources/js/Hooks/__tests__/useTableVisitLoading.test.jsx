import { StrictMode } from 'react';
import { act, renderHook } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import useTableVisitLoading from '../useTableVisitLoading';

it('tracks visits after StrictMode effect replay and forwards callbacks', () => {
  const onStart = vi.fn();
  const onFinish = vi.fn();
  const { result, unmount } = renderHook(() => useTableVisitLoading(), { wrapper: StrictMode });
  const options = result.current.withLoading({ onStart, onFinish });
  act(() => options.onStart({}));
  expect(result.current.isLoading).toBe(true);
  act(() => options.onFinish({}));
  expect(result.current.isLoading).toBe(false);
  expect(onStart).toHaveBeenCalledOnce();
  expect(onFinish).toHaveBeenCalledOnce();
  unmount();
  act(() => options.onStart({}));
});
