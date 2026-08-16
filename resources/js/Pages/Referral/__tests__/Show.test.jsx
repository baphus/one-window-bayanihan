import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ReferralShow from '../Show';

const state = vi.hoisted(() => ({ role: 'AGENCY', agencyId: 'agency-1' }));

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: { post: vi.fn(), patch: vi.fn(), delete: vi.fn(), reload: vi.fn() },
  usePage: () => ({ props: { auth: { user: { role: state.role, agcy_id: state.agencyId } } } }),
  useForm: (initial = {}) => ({
    data: initial,
    errors: {},
    processing: false,
    transform: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    reset: vi.fn(),
  }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/PrimaryButton', () => ({ default: ({ children, ...props }) => <button {...props}>{children}</button> }));
vi.mock('@/Components/TextInput', () => ({ default: (props) => <input {...props} /> }));
vi.mock('@/Components/InputLabel', () => ({ default: ({ children }) => <label>{children}</label> }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));
vi.mock('@/Components/ui/CardSection', () => ({
  CardSection: ({ title, children }) => <section><h2>{title}</h2>{children}</section>,
  InfoCell: ({ label, value }) => <div><span>{label}</span>{value}</div>,
}));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => null, getAvatarColor: () => '' }));
vi.mock('@/Components/PeerProfileModal', () => ({ default: () => null }));
vi.mock('@/Components/AuditLogModal', () => ({ default: () => null }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/lib/utils', () => ({ formatDisplayDateTime: () => 'Jan 1, 2026', formatDisplayDate: () => 'Jan 1, 2026' }));
vi.mock('@/lib/relativeTime', () => ({ formatRelativeTime: () => 'today' }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: () => 'N/A' }));
vi.mock('@/Hooks/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  }),
}));

globalThis.route = (name, params) => `/${name}/${Array.isArray(params) ? params.join('/') : params ?? ''}`;

const referral = {
  id: 'referral-1',
  agcy_id: 'agency-1',
  status: 'PROCESSING',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-02T00:00:00Z',
  required_services: 'Assistance',
  case_id: 'case-1',
  agency: { name: 'Partner Agency' },
  case_file: { case_number: 'CASE-1', tracker_number: 'TRACK-1', client: null },
  milestones: [],
  comments: [],
  attachments: [],
  documents: [],
};

const requestHistory = [{
  id: 'request-1',
  type: 'QUESTION',
  title: 'Client question',
  instructions: 'Please answer.',
  status: 'OPEN',
  items: [],
  messages: [],
  access_links: [],
}];

const historyWithAttachments = [{
  ...requestHistory[0],
  messages: [
    {
      id: 'msg-1',
      body: 'Here is my passport.',
      sender_kind: 'CLIENT_ACCESS',
      created_at: '2026-01-01T00:00:00Z',
      attachments: [
        { id: 'att-img', file_name: 'passport.jpg', file_type: 'image/jpeg', size: 204800, created_at: '2026-01-01T00:00:00Z' },
        { id: 'att-pdf', file_name: 'contract.pdf', file_type: 'application/pdf', size: 1024, created_at: '2026-01-01T00:00:00Z' },
      ],
    },
  ],
}];

function renderReferral(role, permissions) {
  state.role = role;
  return render(
    <ReferralShow
      referral={referral}
      clientRequestHistory={requestHistory}
      clientRequestPermissions={permissions}
      timeline={[]}
    />,
  );
}

describe('Referral/Show client request permissions', () => {
  it('shows the request-client creation control to the owning agency', () => {
    renderReferral('AGENCY', { canCreate: true, canReply: true, canTransition: true, canRevokeAccess: true });

    expect(screen.getByRole('button', { name: /Request client/i })).toBeInTheDocument();
    expect(screen.getByText('Client question')).toBeInTheDocument();
  });

  it('shows request history to an oversight role without agency creation controls', () => {
    renderReferral('CASE_MANAGER', { canCreate: false, canReply: false, canTransition: false, canRevokeAccess: true });

    expect(screen.getByText('Client question')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Request client/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Issue access link|Issue replacement/i })).not.toBeInTheDocument();
  });

  it('previews image attachments and keeps non-image attachments as plain links', () => {
    state.role = 'AGENCY';
    render(
      <ReferralShow
        referral={referral}
        clientRequestHistory={historyWithAttachments}
        clientRequestPermissions={{ canCreate: true, canReply: true, canTransition: true, canRevokeAccess: true }}
        timeline={[]}
      />,
    );

    const thumb = screen.getByRole('button', { name: /Preview passport\.jpg/ });
    const thumbImg = thumb.querySelector('img');
    expect(thumbImg).not.toBeNull();
    expect(thumbImg.getAttribute('src')).toContain('referrals.client-requests.attachments.download');

    const pdfLink = screen.getByRole('link', { name: /contract\.pdf/ });
    expect(pdfLink.querySelector('img')).toBeNull();
    expect(screen.getByRole('button', { name: /Preview contract\.pdf/ })).toBeInTheDocument();
  });

  it('opens a lightbox with the full image and download link when the thumbnail is clicked', () => {
    state.role = 'AGENCY';
    render(
      <ReferralShow
        referral={referral}
        clientRequestHistory={historyWithAttachments}
        clientRequestPermissions={{ canCreate: true, canReply: true, canTransition: true, canRevokeAccess: true }}
        timeline={[]}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Preview passport\.jpg/ }));

    const previewImg = screen.getByRole('img', { name: 'passport.jpg' });
    expect(previewImg.getAttribute('src')).toContain('referrals.client-requests.attachments.download');

    const downloadLink = screen.getByRole('link', { name: /Download/i });
    expect(downloadLink.getAttribute('href')).toContain('referrals.client-requests.attachments.download');

    fireEvent.click(screen.getByRole('button', { name: 'Close preview' }));
    expect(screen.queryByRole('img', { name: 'passport.jpg' })).not.toBeInTheDocument();
  });

  it('opens a lightbox with a browser-native iframe for PDF attachments', () => {
    state.role = 'AGENCY';
    render(
      <ReferralShow
        referral={referral}
        clientRequestHistory={historyWithAttachments}
        clientRequestPermissions={{ canCreate: true, canReply: true, canTransition: true, canRevokeAccess: true }}
        timeline={[]}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Preview contract\.pdf/ }));

    const previewFrame = document.querySelector('iframe[title="contract.pdf"]');
    expect(previewFrame).not.toBeNull();
    expect(previewFrame.getAttribute('src')).toContain('referrals.client-requests.attachments.download');

    fireEvent.click(screen.getByRole('button', { name: 'Close preview' }));
    expect(document.querySelector('iframe[title="contract.pdf"]')).toBeNull();
  });

  it('shows a conversation list and switches between multiple client requests', () => {
    state.role = 'AGENCY';
    const twoRequests = [
      requestHistory[0],
      { ...requestHistory[0], id: 'request-2', title: 'Second question', status: 'COMPLETED' },
    ];
    render(
      <ReferralShow
        referral={referral}
        clientRequestHistory={twoRequests}
        clientRequestPermissions={{ canCreate: true, canReply: true, canTransition: true, canRevokeAccess: true }}
        timeline={[]}
      />,
    );

    // Conversation list pane with both subjects.
    expect(screen.getByRole('button', { name: /Client question/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Second question/ })).toBeInTheDocument();

    // First request is shown in the conversation pane by default.
    const conversationPane = document.querySelector('article');
    expect(conversationPane).not.toBeNull();
    expect(within(conversationPane).getByText('Client question')).toBeInTheDocument();
    expect(within(conversationPane).queryByText('Second question')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Second question/ }));

    const activePane = document.querySelector('article');
    expect(activePane).not.toBeNull();
    expect(within(activePane).getByText('Second question')).toBeInTheDocument();
    expect(within(activePane).queryByText('Client question')).not.toBeInTheDocument();
  });
});
