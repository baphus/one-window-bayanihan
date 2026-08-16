import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import TrackingShow from '../Show';

const routerMock = vi.hoisted(() => ({ post: vi.fn(), reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }) => <title>{title}</title>,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: routerMock,
}));

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => <header /> }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => <footer /> }));
vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Components/TrackingNotFoundState', () => ({ default: () => <div /> }));

globalThis.route = (name, params) => `/${name}/${Array.isArray(params) ? params.join('/') : params ?? ''}`;

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
    exchange: '/track/request/exchange',
  },
};

describe('Tracking/Show client request panel', () => {
  it('renders only request-safe details, checklist, reply controls, and document upload', () => {
    render(<TrackingShow clientRequestPanel={readyPanel} trackingId="PRIVATE-CASE" />);

    expect(screen.getByRole('heading', { name: 'Submit passport copy' })).toBeInTheDocument();
    expect(screen.getByText('Passport copy')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Your reply' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Send reply/ })).toBeInTheDocument();
    expect(screen.getByLabelText(/Attach documents/)).toBeInTheDocument();
    expect(document.querySelector('input[type="file"]')).not.toBeNull();
    expect(screen.queryByText('PRIVATE-CASE')).not.toBeInTheDocument();
    expect(screen.queryByText('Case summary')).not.toBeInTheDocument();
    expect(screen.queryByText('Complete case history')).not.toBeInTheDocument();
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

  it('renders download links for attachments on thread messages', () => {
    render(<TrackingShow clientRequestPanel={{
      ...readyPanel,
      activeRequest: {
        ...readyPanel.activeRequest,
        messages: [
          {
            id: 'msg-1',
            body: 'Please upload your passport.',
            sender_kind: 'AGENCY_USER',
            created_at: '2026-01-01T00:00:00Z',
            attachments: [
              { id: 'att-1', file_name: 'passport-guide.pdf', file_type: 'application/pdf', size: 2048, created_at: '2026-01-01T00:00:00Z' },
            ],
          },
        ],
      },
    }} trackingId="PRIVATE-CASE" />);

    const link = screen.getByRole('link', { name: /passport-guide\.pdf/ });
    expect(link.getAttribute('href')).toContain('track.request.attachments.download');
  });

  it('attaches selected documents to the reply', () => {
    render(<TrackingShow clientRequestPanel={readyPanel} trackingId="PRIVATE-CASE" />);

    const fileInput = screen.getByLabelText(/Attach documents/);
    const file = new File(['%PDF-1.4 test'], 'passport.pdf', { type: 'application/pdf' });
    fireEvent.change(fileInput, { target: { files: [file] } });

    expect(screen.getByText('passport.pdf')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Send reply/ }));

    expect(routerMock.post).toHaveBeenCalledTimes(1);
    expect(routerMock.post).toHaveBeenCalledWith(
      '/track/request/messages',
      expect.any(FormData),
      expect.any(Object),
    );
    const formData = routerMock.post.mock.calls[0][1];
    expect(formData.get('attachments[]')).toBe(file);
  });

  it('clearly marks a completed request and hides the reply form', () => {
    render(<TrackingShow clientRequestPanel={{
      ...readyPanel,
      activeRequest: {
        ...readyPanel.activeRequest,
        status: 'COMPLETED',
      },
    }} trackingId="PRIVATE-CASE" />);

    expect(screen.getByText('This request is completed')).toBeInTheDocument();
    expect(screen.getByText('Completed')).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Your reply' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Send reply/ })).not.toBeInTheDocument();
  });

  it('exchanges a fresh magic-link token when an older session is already active', () => {
    routerMock.post.mockClear();
    window.location.hash = '#token=fresh-token-value';

    render(<TrackingShow clientRequestPanel={readyPanel} trackingId="PRIVATE-CASE" />);

    expect(routerMock.post).toHaveBeenCalledTimes(1);
    expect(routerMock.post).toHaveBeenCalledWith(
      '/track/request/exchange',
      { token: 'fresh-token-value' },
      expect.any(Object),
    );
    window.location.hash = '';
  });

  it('shows the unavailable state when a magic-link token cannot be exchanged', () => {
    routerMock.post.mockImplementationOnce((url, data, options) => options.onError?.({ token: 'invalid' }));
    window.location.hash = '#token=dead-token-value';

    render(<TrackingShow clientRequestPanel={readyPanel} trackingId="PRIVATE-CASE" />);

    expect(screen.getByText('Secure request unavailable')).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Your reply' })).not.toBeInTheDocument();
    window.location.hash = '';
  });
});
