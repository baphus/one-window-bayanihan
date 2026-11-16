import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ToastProvider from '@/Components/ToastProvider';
import { useToast } from '@/Hooks/useToast.jsx';

vi.mock('@inertiajs/react', () => ({ router: {}, usePage: () => ({ props: {} }) }));

let toast;
function Controls() {
  toast = useToast();
  return null;
}

const advance = (ms) => act(() => vi.advanceTimersByTime(ms));

describe('toast stack', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    render(<ToastProvider><Controls /></ToastProvider>);
  });
  afterEach(() => vi.useRealTimers());

  it('queues repeated messages and starts their timers only when visible', () => {
    act(() => {
      toast.success('First');
      toast.info('Repeat');
      toast.info('Repeat');
      toast.warning('Queued');
    });
    expect(screen.queryByText('Queued')).toBeNull();
    expect(screen.getAllByText('Repeat')).toHaveLength(2);
    advance(5000);
    advance(300);
    expect(screen.getByText('Queued')).toBeTruthy();
    advance(7999);
    expect(screen.getByText('Queued')).toBeTruthy();
    advance(301);
    expect(screen.queryByText('Queued')).toBeNull();
  });

  it('pauses for hover and focus and closes on request', () => {
    act(() => toast.error('Check this'));
    const notification = screen.getByRole('alert');
    const close = screen.getByRole('button', { name: 'Dismiss notification' });
    advance(3000);
    fireEvent.mouseEnter(notification);
    fireEvent.focus(close);
    advance(10000);
    fireEvent.mouseLeave(notification);
    advance(10000);
    expect(screen.getByText('Check this')).toBeTruthy();
    fireEvent.blur(close);
    advance(4999);
    expect(screen.getByText('Check this')).toBeTruthy();
    fireEvent.click(close);
    advance(300);
    expect(screen.queryByText('Check this')).toBeNull();
  });

  it('honors longer explicit durations', () => {
    act(() => toast.info('Longer', 9000));
    advance(8999);
    expect(screen.getByText('Longer')).toBeTruthy();
    advance(301);
    expect(screen.queryByText('Longer')).toBeNull();
  });
});
