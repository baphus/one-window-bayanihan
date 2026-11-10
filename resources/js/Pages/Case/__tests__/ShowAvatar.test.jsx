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
