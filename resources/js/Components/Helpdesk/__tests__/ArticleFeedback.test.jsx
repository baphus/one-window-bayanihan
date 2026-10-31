import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ArticleFeedback from '../ArticleFeedback';

vi.mock('@inertiajs/react', () => ({ Link: ({ children, ...props }) => <a {...props}>{children}</a> }));

beforeEach(() => {
  global.route = (name) => name;
  global.fetch = vi.fn();
});

describe('ArticleFeedback', () => {
  it('waits for identity and acknowledges only saved votes, then permits changes', async () => {
    fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ vote: null }) });
    render(<ArticleFeedback slug="public-guide" />);
    expect(screen.getByRole('button', { name: 'Yes' })).toBeDisabled();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Yes' })).toBeEnabled());
    fetch.mockResolvedValueOnce({ ok: false });
    fireEvent.click(screen.getByRole('button', { name: 'Yes' }));
    await screen.findByRole('alert');
    expect(screen.queryByText('Thank you for your feedback.')).not.toBeInTheDocument();
    fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ vote: 'yes' }) });
    fireEvent.click(screen.getByRole('button', { name: 'Yes' }));
    await screen.findByText('Thank you for your feedback.');
    fireEvent.click(screen.getByRole('button', { name: 'Change vote' }));
    expect(screen.getByRole('button', { name: 'No' })).toBeEnabled();
  });

  it('keeps voting disabled on initialization failure and retries', async () => {
    fetch.mockRejectedValueOnce(new Error('Offline'));
    render(<ArticleFeedback slug="public-guide" />);
    await screen.findByRole('alert');
    expect(screen.getByRole('button', { name: 'Yes' })).toBeDisabled();
    fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ vote: 'no' }) });
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));
    await screen.findByText('Thank you for your feedback.');
  });
});
