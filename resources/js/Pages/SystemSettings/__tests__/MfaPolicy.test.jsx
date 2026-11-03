import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({ post: vi.fn() }));
const mfaHook = vi.hoisted(() => ({
    status: { confirmed_until: new Date(Date.now() + 60_000).toISOString() },
}));

vi.mock('@inertiajs/react', () => ({ Head: () => null, router }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/Hooks/useMfaStatus', () => ({ default: () => ({ status: mfaHook.status }) }));
vi.mock('@/lib/utils', () => ({ formatDisplayDateTime: (date) => `formatted:${date}` }));

globalThis.route = (name) => `/mocked/${name}`;

import SystemSettings from '../Index';

const groups = [{
    key: 'security',
    label: 'Security',
    description: 'Access policy.',
    settings: [
        {
            key: 'mfa_role_case_manager_required',
            type: 'bool',
            value: true,
            description: 'Case managers must use MFA.',
            confirm_required: false,
        },
    ],
}];

afterEach(cleanup);

beforeEach(() => {
    router.post.mockReset();
});

describe('role MFA defaults', () => {
    it('labels role defaults as required or optional and pins the administrator floor', () => {
        render(<SystemSettings groups={groups} />);

        expect(screen.getByLabelText('Case managers must use MFA.')).toBeChecked();
        expect(screen.getByText('Required')).toBeInTheDocument();
        expect(screen.getByText(/administrator accounts are always required/i)).toBeInTheDocument();
        expect(screen.getByText(/never deletes/i)).toBeInTheDocument();
    });

    it('requires a reason and MFA confirmation before saving a role policy change', () => {
        render(<SystemSettings groups={groups} />);

        fireEvent.click(screen.getByLabelText('Case managers must use MFA.'));
        fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));

        const dialog = screen.getByRole('dialog');
        fireEvent.change(within(dialog).getByLabelText('Reason'), { target: { value: 'Policy update' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Save policy' }));

        expect(router.post).toHaveBeenCalledWith(
            '/mocked/admin.system-settings.update',
            expect.objectContaining({
                confirmed: false,
                mfa_role_case_manager_required: false,
                mfa_change_reason: 'Policy update',
            }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
