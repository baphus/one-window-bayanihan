import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CaseShow from '../Show';
import { router } from '@inertiajs/react';

const pageState = vi.hoisted(() => ({ role: 'CASE_MANAGER' }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, Link: ({ children, ...props }) => <a {...props}>{children}</a>, router: { patch: vi.fn(), post: vi.fn() }, usePage: () => ({ props: { roles: { CASE_MANAGER: 'CASE_MANAGER', ADMIN: 'ADMIN' }, auth: { user: { role: pageState.role } }, categories: [{ id: 'cat-1', name: 'Legal' }, { id: 'cat-2', name: 'Medical' }] } }), useForm: () => ({ data: {}, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }) }));
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
const { toastError } = vi.hoisted(() => ({ toastError: vi.fn() }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: toastError }) }));
globalThis.route = () => '/';

describe('Case PDF export', () => {
  it('does not save login HTML returned after session expiration', async () => {
    const html = '<!DOCTYPE html><html><body>Sign in</body></html>';
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(html, { headers: { 'content-type': 'text/html' } }));
    const createObjectURL = vi.spyOn(URL, 'createObjectURL');
    render(<CaseShow case={{ id: 'case-1', case_number: 'OWB-1', first_name: 'Ari', last_name: 'Example', documents: [] }} />);

    fireEvent.click(screen.getByRole('button', { name: 'Export PDF' }));
    await waitFor(() => expect(toastError).toHaveBeenCalledWith(expect.stringContaining('sign in again')));
    expect(fetchMock).toHaveBeenCalledWith(expect.any(String), { credentials: 'same-origin' });
    expect(createObjectURL).not.toHaveBeenCalled();
    fetchMock.mockRestore();
    createObjectURL.mockRestore();
  });
});

describe('Case details identity', () => {
  it('renders independent case identity without legacy client relations', () => {
    render(<CaseShow case={{ first_name: 'Ari', last_name: 'Example', avatar_url: '/case.jpg' }} milestoneTimeline={[]} />);
    expect(document.querySelector('img')).toHaveAttribute('src', '/case.jpg');
    expect(screen.getByText('Ari Example')).toBeInTheDocument();
  });
});

describe('Case activity count', () => {
  it('counts lifecycle events alongside referral milestones', () => {
    render(<CaseShow
      case={{ id: 'case-1', first_name: 'Ari', last_name: 'Example', documents: [] }}
      milestoneTimeline={[{ type: 'referral_sent', title: 'Referral sent', date: '2026-11-14' }]}
      lifecycle={[{ label: 'Case opened', at: '2026-11-14' }, { label: 'Case reviewed', at: '2026-11-14' }]}
    />);

    expect(screen.getByText('Showing 3 of 3 events')).toBeInTheDocument();
  });
});

describe('Case documents section', () => {
  it('no longer claims documents are viewable to all referred agencies', () => {
    render(<CaseShow case={{ first_name: 'Ari', last_name: 'Example', documents: [] }} />);
    expect(screen.queryByText(/viewable to all referred agencies/i)).not.toBeInTheDocument();
  });

  it('offers a case-manager ZIP download only when case documents exist', () => {
    globalThis.route = vi.fn((name) => `/${name}`);
    const { rerender } = render(<CaseShow
      case={{ id: 'case-1', first_name: 'Ari', last_name: 'Example', documents: [{ id: 'doc-1', file_name: 'passport.pdf' }] }}
    />);

    expect(screen.getByRole('link', { name: 'Download all case documents as ZIP' })).toHaveAttribute('href', '/cases.intake-documents.archive');
    expect(screen.getByRole('link', { name: 'View document passport.pdf' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete document passport.pdf' })).toBeInTheDocument();

    rerender(<CaseShow case={{ id: 'case-1', first_name: 'Ari', last_name: 'Example', documents: [] }} />);
    expect(screen.queryByRole('link', { name: 'Download all case documents as ZIP' })).not.toBeInTheDocument();
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

describe('Case actions', () => {
  it('blocks invalid correction emails and clears their errors on edit', () => {
    render(<CaseShow case={{ id: 'case-1', status: 'OPEN', referrals: [] }} case_categories={[{ id: 'cat-1', name: 'Legal' }]} />);
    fireEvent.click(screen.getByRole('button', { name: 'Edit Case' }));
    router.patch.mockClear();
    const email = screen.getByLabelText('Email');
    const nok = screen.getByLabelText('Next of kin email');
    fireEvent.change(email, { target: { value: 'bad' } });
    fireEvent.change(nok, { target: { value: 'also-bad' } });
    fireEvent.change(screen.getByLabelText('Reason for correction *'), { target: { value: 'Fix email' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));
    expect(router.patch).not.toHaveBeenCalled();
    expect(email).toHaveFocus();
    expect(email).toHaveAccessibleDescription('Enter a valid email address.');
    expect(nok).toHaveAccessibleDescription('Enter a valid email address.');
    fireEvent.change(email, { target: { value: 'valid@example.com' } });
    fireEvent.change(nok, { target: { value: '' } });
    expect(email).not.toHaveAttribute('aria-describedby');
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));
    expect(router.patch).toHaveBeenCalledTimes(1);
  });
  it('saves corrections and categories together with PATCH', () => {
    render(<CaseShow case={{ id: 'case-1', status: 'OPEN', first_name: 'Ari', referrals: [] }} case_categories={[{ id: 'cat-1', name: 'Legal' }]} />);
    globalThis.route = (name) => `/${name}`;
    fireEvent.click(screen.getByRole('button', { name: 'Edit Case' }));
    router.patch.mockClear();
    fireEvent.change(screen.getByLabelText('First name'), { target: { value: 'Maria' } });
    fireEvent.click(screen.getByRole('checkbox', { name: 'Medical' }));
    fireEvent.change(screen.getByLabelText('Reason for correction *'), { target: { value: 'Correct name' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));
    expect(router.patch).toHaveBeenCalledTimes(1);
    expect(router.patch).toHaveBeenCalledWith('/cases.update', expect.objectContaining({ first_name: 'Maria', correction_reason: 'Correct name', category_ids: ['cat-1', 'cat-2'] }), expect.any(Object));
    expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    fireEvent.submit(screen.getByRole('button', { name: 'Saving…' }).closest('form'));
    expect(router.patch).toHaveBeenCalledTimes(1);
  });
  it('restricts editing to case managers', () => {
    pageState.role = 'AGENCY';
    const { rerender } = render(<CaseShow case={{ id: 'case-1', status: 'OPEN', referrals: [] }} />);
    expect(screen.queryByRole('button', { name: 'Edit Case' })).not.toBeInTheDocument();
    pageState.role = 'CASE_MANAGER';
    rerender(<CaseShow case={{ id: 'case-1', status: 'OPEN', referrals: [] }} />);
    expect(screen.getByRole('button', { name: 'Edit Case' })).toBeInTheDocument();
  });
  it('keeps edits on validation failure and confirms dismissal', () => {
    render(<CaseShow case={{ id: 'case-1', status: 'OPEN', first_name: 'Ari', referrals: [] }} case_categories={[{ id: 'cat-1', name: 'Legal' }]} />);
    fireEvent.click(screen.getByRole('button', { name: 'Edit Case' }));
    fireEvent.change(screen.getByLabelText('First name'), { target: { value: 'Maria' } });
    fireEvent.change(screen.getByLabelText('Reason for correction *'), { target: { value: 'Fix typo' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));
    const callbacks = router.patch.mock.calls.at(-1)[2];
    act(() => { callbacks.onError({ first_name: 'Invalid name' }); callbacks.onFinish(); });
    expect(screen.getByRole('alert')).toHaveTextContent('Invalid name');
    expect(screen.getByLabelText('First name')).toHaveValue('Maria');
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancel' }));
    expect(screen.getByText('Your case edits have not been saved.')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Discard changes' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
  it('only locks normal close for active referrals', () => {
    const { rerender } = render(<CaseShow case={{ id: 'case-1', status: 'OPEN', referrals: [{ id: 'r1', status: 'PENDING' }] }} />);
    const normalClose = screen.getByRole('button', { name: 'Close Case' });
    expect(normalClose).toBeDisabled();
    expect(normalClose).toHaveTextContent('lock');
    expect(screen.getByRole('button', { name: 'Force close case' })).toBeEnabled();
    router.post.mockClear();
    fireEvent.click(screen.getByRole('button', { name: 'Force close case' }));
    expect(screen.getByText(/referrals are still processing.*anyway/i)).toBeInTheDocument();
    expect(router.post).not.toHaveBeenCalled();
    fireEvent.click(screen.getAllByRole('button', { name: 'Force close case' }).at(-1));
    expect(router.post).toHaveBeenCalledWith('/cases.toggle-status', { force_close: true }, expect.any(Object));
    rerender(<CaseShow case={{ id: 'case-1', status: 'OPEN', referrals: [{ id: 'r1', status: 'COMPLETED' }] }} />);
    expect(screen.getByRole('button', { name: 'Close Case' })).toBeEnabled();
    expect(screen.getByRole('button', { name: 'Close Case' })).not.toHaveTextContent('lock');
    expect(screen.queryByRole('button', { name: 'Force close case' })).not.toBeInTheDocument();
    rerender(<CaseShow case={{ id: 'case-1', status: 'OPEN', referrals: [{ id: 'r1', status: 'REJECTED' }] }} />);
    expect(screen.getByRole('button', { name: 'Close Case' })).toBeEnabled();
    expect(screen.getByRole('button', { name: 'Close Case' })).not.toHaveTextContent('lock');
  });
});
