import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AppToast from '../AppToast';

describe('AppToast', () => {
  it.each([
    ['success', 'Success', 'status', 'bg-success-container', 'bg-success'],
    ['error', 'Error', 'alert', 'bg-error-container', 'bg-error'],
    ['warning', 'Warning', 'status', 'bg-warning-container', 'bg-warning'],
    ['info', 'Informational', 'status', 'bg-info-container', 'bg-info'],
  ])('renders %s with its heading, semantic colors and dismiss control', (tone, label, role, surface, icon) => {
    const onClose = vi.fn();
    render(<AppToast tone={tone} message="Example message" onClose={onClose} />);
    const notification = screen.getByRole(role);
    expect(notification.classList.contains(surface)).toBe(true);
    expect(notification.querySelector(`.${icon}`)).not.toBeNull();
    expect(screen.getByText(label)).toBeTruthy();
    expect(screen.getByText('Example message')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Dismiss notification' }));
    expect(onClose).toHaveBeenCalledOnce();
  });
});
