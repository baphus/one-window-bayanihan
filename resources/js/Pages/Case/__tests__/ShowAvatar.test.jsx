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

describe('Case details client avatar', () => {
  it('prefers linked user avatar and falls back to client avatar', () => {
    const client = { first_name: 'Ari', last_name: 'Example', avatar_url: '/client.jpg', user: { avatar_url: '/user.jpg' } };
    const { rerender } = render(<CaseShow case={{ client }} milestoneTimeline={[]} categories={[]} caseIssues={[]} />);
    expect(document.querySelector('img')).toHaveAttribute('src', '/user.jpg');

    rerender(<CaseShow case={{ client: { ...client, user: null } }} milestoneTimeline={[]} categories={[]} caseIssues={[]} />);
    expect(document.querySelector('img')).toHaveAttribute('src', '/client.jpg');
  });
});
