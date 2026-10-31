import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({ post: vi.fn(), success: vi.fn(), error: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ Head: () => null, router: { post: vi.fn() } }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: mocks.success, error: mocks.error }) }));
vi.mock('@/lib/utils', () => ({ formatDisplayDateTime: (date) => `formatted:${date}` }));

globalThis.route = (name) => `/mocked/${name}`;

import SystemSettings from '../Index';

afterEach(cleanup);
beforeEach(() => {
  mocks.post.mockReset();
  mocks.success.mockReset();
  mocks.error.mockReset();
  window.axios = { post: mocks.post };
});

describe('System settings chatbot knowledge refresh', () => {
  it('posts to the reindex endpoint, reports success, updates timestamp, and locks button while pending', async () => {
    let resolveRequest;
    mocks.post.mockReturnValueOnce(new Promise((resolve) => { resolveRequest = resolve; }));
    render(<SystemSettings referral_overdue_days={7} chatbot_last_reindexed_at="2026-01-01T00:00:00Z" />);

    expect(screen.getByText('Last updated: formatted:2026-01-01T00:00:00Z')).toBeInTheDocument();
    const button = screen.getByRole('button', { name: 'Update Knowledge' });
    fireEvent.click(button);
    expect(button).toBeDisabled();
    expect(await screen.findByRole('button', { name: /refreshing/i })).toBeDisabled();
    expect(mocks.post).toHaveBeenCalledWith('/mocked/admin.system-settings.reindex-chatbot');

    resolveRequest({ data: { success: true, message: 'Knowledge refreshed.', last_reindexed_at: '2026-02-01T00:00:00Z' } });
    await waitFor(() => expect(mocks.success).toHaveBeenCalledWith('Knowledge refreshed.'));
    expect(screen.getByText('Last updated: formatted:2026-02-01T00:00:00Z')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Update Knowledge' })).toBeEnabled();
  });

  it('shows the server message on unsuccessful refresh and a fallback on request failure', async () => {
    mocks.post.mockResolvedValueOnce({ data: { success: false, message: 'Index unavailable.' } });
    render(<SystemSettings referral_overdue_days={7} />);
    fireEvent.click(screen.getByRole('button', { name: 'Update Knowledge' }));
    await waitFor(() => expect(mocks.error).toHaveBeenCalledWith('Index unavailable.'));

    mocks.post.mockRejectedValueOnce(new Error('offline'));
    fireEvent.click(screen.getByRole('button', { name: 'Update Knowledge' }));
    await waitFor(() => expect(mocks.error).toHaveBeenCalledWith('Failed to reach the server. Please try again.'));
  });
});
