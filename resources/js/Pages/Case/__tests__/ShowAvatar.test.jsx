import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CaseShow from '../Show';

vi.mock('@inertiajs/react', () => ({ Head: () => null, Link: ({ children, ...props }) => <a {...props}>{children}</a>, router: {}, usePage: () => ({ props: { roles: {}, auth: { user: {} } } }), useForm: () => ({ data: {}, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }) }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: () => null }));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => null, getAvatarColor: () => '' }));
vi.mock('@/lib/utils', () => ({ formatDisplayDateTime: () => '', formatDisplayDate: () => '', formatDisplayTime: () => '' }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: () => '' }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: () => null, bypassNext: vi.fn() }) }));
vi.mock('@/Components/Select', () => ({ default: () => null }));
vi.mock('@/Components/ui/UnifiedTable', () => ({ UnifiedTable: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({ RowContextMenu: () => null, RowContextMenuItem: () => null }));
vi.mock('@/Components/FileUpload', () => ({ default: () => null }));
vi.mock('@/Components/AuditLogModal', () => ({ default: () => null }));
vi.mock('@/Components/Timeline', () => ({ default: () => null }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
globalThis.route = () => '/';

describe('Case details identity', () => {
  it('renders independent case identity without legacy client relations', () => {
    render(<CaseShow case={{ first_name: 'Ari', last_name: 'Example', avatar_url: '/case.jpg' }} milestoneTimeline={[]} />);
    expect(document.querySelector('img')).toHaveAttribute('src', '/case.jpg');
    expect(screen.getByText('Ari Example')).toBeInTheDocument();
  });
});

describe('Case documents section', () => {
  it('no longer claims documents are viewable to all referred agencies', () => {
    render(<CaseShow case={{ first_name: 'Ari', last_name: 'Example', documents: [] }} />);
    expect(screen.queryByText(/viewable to all referred agencies/i)).not.toBeInTheDocument();
  });

  it('lists document requirements with their state and note', () => {
    render(<CaseShow
      case={{ first_name: 'Ari', last_name: 'Example', documents: [] }}
      documentRequirements={[
        { slot: 'arrival_stamp', state: 'for_compliance', label: 'BI arrival stamp', note: 'To follow' },
        { slot: 'passport_front_page', state: 'received', label: 'Passport front page', note: null },
      ]}
    />);

    expect(screen.getByTestId('document-requirements')).toBeInTheDocument();
    expect(screen.getByText('BI arrival stamp')).toBeInTheDocument();
    expect(screen.getByText('For compliance')).toBeInTheDocument();
    expect(screen.getByText('Received')).toBeInTheDocument();
    expect(screen.getByText('To follow')).toBeInTheDocument();
  });
});
