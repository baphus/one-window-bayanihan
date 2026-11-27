import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CaseShow from '../Show';

const dirtySpy = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: { patch: vi.fn(), post: vi.fn(), visit: vi.fn() },
  usePage: () => ({ props: { auth: { user: { id: 'u-1', role: 'CASE_MANAGER' } }, roles: { CASE_MANAGER: 'CASE_MANAGER', ADMIN: 'ADMIN' }, categories: [{ id: 'cat-1', name: 'Legal' }, { id: 'cat-2', name: 'Medical' }] }, url: '/cases/case-1' }),
  useForm: () => ({ data: {}, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }),
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: () => null }));
vi.mock('@/Components/ui/UserAvatar', () => ({ default: () => null, getAvatarColor: () => '' }));
vi.mock('@/lib/utils', () => ({ formatDisplayDateTime: () => '', formatDisplayDate: () => '', formatTimestampDate: () => '', formatDisplayTime: () => '' }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: () => '' }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: (dirty) => { dirtySpy(dirty); return { UnsavedModal: null, bypassNext: vi.fn() }; } }));
vi.mock('@/Components/Select', () => ({ default: () => null }));
vi.mock('@/Components/ui/UnifiedTable', () => ({ UnifiedTable: () => null }));
vi.mock('@/Components/ui/RowContextMenu', () => ({ RowContextMenu: () => null, RowContextMenuItem: () => null }));
vi.mock('@/Components/FileUpload', () => ({ default: () => null }));
vi.mock('@/Components/AuditLogModal', () => ({ default: () => null }));
vi.mock('@/Components/Timeline', () => ({ default: () => null }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/Components/PresenceRoster', () => ({ default: () => null }));
vi.mock('@/Components/presencePolicy', () => ({ canShowCasePresence: () => false }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Components/DraftControls', async () => {
  const React = await import('react');
  return { default: ({ status, onClear }) => React.createElement('div', null, React.createElement('p', { role: 'status' }, status), status && React.createElement('button', { type: 'button', onClick: onClear }, 'Clear saved draft')) };
});

globalThis.route = (name) => `/${name}`;

const draftKey = 'owb_session_draft_v1_u-1_case-correction_case-1';
const correctionDraft = { first_name: 'Restored', category_ids: ['cat-2'], correction_reason: 'Correct record' };

describe('Case correction session draft', () => {
  it('restores on opening editor and Clear restores original baseline', async () => {
    sessionStorage.clear();
    sessionStorage.setItem(draftKey, JSON.stringify({ version: 1, data: correctionDraft }));
    render(<CaseShow case={{ id: 'case-1', status: 'OPEN', first_name: 'Original', referrals: [] }} case_categories={[{ id: 'cat-1', name: 'Legal' }]} />);

    await waitFor(() => expect(screen.getByRole('button', { name: 'Edit Case' })).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Edit Case' }));
    expect(await screen.findByLabelText('First name')).toHaveValue('Restored');
    expect(screen.getByLabelText('Reason for correction *')).toHaveValue('Correct record');
    expect(screen.getByRole('checkbox', { name: 'Medical' })).toBeChecked();
    fireEvent.change(screen.getByLabelText('Last name'), { target: { value: 'Changed' } });
    fireEvent.click(await screen.findByRole('button', { name: 'Clear saved draft' }));
    expect(screen.getByLabelText('First name')).toHaveValue('Original');
    expect(screen.getByLabelText('Reason for correction *')).toHaveValue('');
    expect(screen.getByRole('checkbox', { name: 'Legal' })).toBeChecked();
    await waitFor(() => expect(sessionStorage.getItem(draftKey)).toBeNull());
    expect(dirtySpy).toHaveBeenLastCalledWith(false);
  });
});
