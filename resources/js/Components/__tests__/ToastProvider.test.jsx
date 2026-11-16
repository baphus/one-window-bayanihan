import { act, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ToastProvider, { FlashMessageWatcher } from '@/Components/ToastProvider';
import { router } from '@inertiajs/react';

vi.mock('@inertiajs/react', () => ({
  router: { on: vi.fn() },
  usePage: () => ({ props: { flash: null } }),
}));
vi.mock('@/Components/ui/AppToast', () => ({ default: ({ message }) => <div role="alert">{message}</div> }));

describe('ToastProvider shared request errors', () => {
  let listeners;

  beforeEach(() => {
    listeners = {};
    router.on.mockImplementation((name, callback) => {
      listeners[name] = callback;
      return () => { delete listeners[name]; };
    });
  });

  it('registers centralized error handlers, summarizes validation and cleans up listeners', () => {
    const view = render(<ToastProvider><FlashMessageWatcher /></ToastProvider>);
    expect(router.on).toHaveBeenCalledWith('error', expect.any(Function));
    expect(router.on).toHaveBeenCalledWith('exception', expect.any(Function));
    expect(router.on).toHaveBeenCalledWith('invalid', expect.any(Function));
    act(() => listeners.error({ detail: { errors: { name: ['Name is required.'] } } }));
    expect(screen.getByRole('alert').textContent).toBe('Please review the form: Name is required.');
    const invalidEvent = { detail: { response: { status: 401 } }, preventDefault: vi.fn() };
    act(() => listeners.invalid(invalidEvent));
    expect(invalidEvent.preventDefault).toHaveBeenCalledOnce();
    expect(screen.getAllByRole('alert')[1].textContent).toContain('Sign in again');
    view.unmount();
    expect(listeners).toEqual({});
  });
});
