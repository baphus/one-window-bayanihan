import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import TrackingShow from '../Show';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }) => <title>{title}</title>,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: { post: vi.fn(), reload: vi.fn() },
}));

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => <header /> }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => <footer /> }));
vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Components/TrackingNotFoundState', () => ({ default: () => <div /> }));

const readyPanel = {
  state: 'ready',
  activeRequest: {
    type: 'DOCUMENT_REQUEST',
    title: 'Submit passport copy',
    instructions: 'Please provide the requested document.',
    due_at: null,
    status: 'OPEN',
    agency_name: 'Partner Agency',
    checklist: [{ id: 'item-1', label: 'Passport copy' }],
    messages: [],
  },
  actions: {
    reply: '/track/request/messages',
    requestReplacement: '/track/request/replacement',
  },
};

describe('Tracking/Show client request panel', () => {
  it('renders only request-safe details, checklist, and reply controls', () => {
    render(<TrackingShow clientRequestPanel={readyPanel} trackingId="PRIVATE-CASE" />);

    expect(screen.getByRole('heading', { name: 'Submit passport copy' })).toBeInTheDocument();
    expect(screen.getByText('Passport copy')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Your reply' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Send reply/ })).toBeInTheDocument();
    expect(screen.queryByText('PRIVATE-CASE')).not.toBeInTheDocument();
    expect(screen.queryByText('Case summary')).not.toBeInTheDocument();
    expect(screen.queryByText('Complete case history')).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: /upload/i })).not.toBeInTheDocument();
    expect(document.querySelector('input[type="file"]')).toBeNull();
  });

  it('offers a safe replacement action for an expired request state without upload controls', () => {
    render(<TrackingShow clientRequestPanel={{
      state: 'expired',
      actions: readyPanel.actions,
    }} />);

    expect(screen.getByText('This request link has expired')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Request a new link/ })).toBeInTheDocument();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(document.querySelector('input[type="file"]')).toBeNull();
  });
});
