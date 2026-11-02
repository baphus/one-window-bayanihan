import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({ post: vi.fn(), reload: vi.fn() }));
const auth = vi.hoisted(() => ({ id: 'admin-1' }));
const mfaHook = vi.hoisted(() => ({
    status: { required: true, confirmed_until: new Date(Date.now() + 60_000).toISOString() },
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }) => <a>{children}</a>,
    router,
    usePage: () => ({ props: { auth: { user: { id: auth.id } } } }),
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Hooks/useMfaStatus', () => ({ default: () => ({ status: mfaHook.status }) }));
vi.mock('@/Components/Admin/UserFormModal', () => ({ default: () => null }));

globalThis.route = (name, id) => `/${name}/${id ?? ''}`;

import AdminUserShow from '../Show';

const makeUser = () => ({
    id: 'user-1',
    name: 'Ada User',
    email: 'ada@example.test',
    role: 'CASE_MANAGER',
    is_active: true,
    email_verified_at: '2026-01-01T00:00:00Z',
    mfa_enabled_at: '2026-01-01T00:00:00Z',
    agency: null,
    emergency_contact: {},
    mfaPolicy: { role_default: 'required', override: 'inherit', effective: 'required' },
});

afterEach(cleanup);

beforeEach(() => {
    router.post.mockReset();
    auth.id = 'admin-1';
});

describe('admin MFA policy controls', () => {
    it('shows role default, override and effective requirement', () => {
        render(<AdminUserShow user={makeUser()} />);

        expect(screen.getByText('MFA Role Default')).toBeInTheDocument();
        expect(screen.getByText('MFA User Override')).toBeInTheDocument();
        expect(screen.getByText('MFA Effective')).toBeInTheDocument();
        expect(screen.getByText('Inherit role default')).toBeInTheDocument();
    });

    it('requires a reason and resets only after fresh MFA confirmation', () => {
        render(<AdminUserShow user={makeUser()} />);

        fireEvent.click(screen.getByRole('button', { name: 'Reset MFA' }));
        const dialog = screen.getByRole('dialog');

        // The mock status window is still open, so proof is not repeated.
        expect(within(dialog).queryByLabelText('Authentication code')).not.toBeInTheDocument();
        fireEvent.change(within(dialog).getByLabelText('Reason'), { target: { value: 'Phone replaced' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Reset MFA' }));

        expect(router.post).toHaveBeenCalledWith(
            '/admin.users.reset-mfa/user-1',
            { reason: 'Phone replaced' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does not offer a self-reset', () => {
        auth.id = 'user-1';
        render(<AdminUserShow user={makeUser()} />);

        expect(screen.queryByRole('button', { name: 'Reset MFA' })).not.toBeInTheDocument();
    });
});
