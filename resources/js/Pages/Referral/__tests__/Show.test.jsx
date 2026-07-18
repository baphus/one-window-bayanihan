import { render, screen } from '@testing-library/react';
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
  requirements: [],
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
});
