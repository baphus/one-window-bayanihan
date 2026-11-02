import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({ required: true }));

vi.mock('@/Hooks/useMfaStatus', () => ({
    default: () => ({
        status: { required: state.required, confirmed_until: null, enabled: true },
        isFresh: false,
        refresh: vi.fn(),
    }),
}));
vi.mock('@/Components/Modal', () => ({ default: ({ show, children }) => show ? <div role="dialog">{children}</div> : null }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));

globalThis.route = (name) => `/mocked/${name}`;

import MfaSetup from '../MfaSetup';

afterEach(cleanup);

describe('profile MFA policy display', () => {
    it('shows required policy and omits the self-disable control', () => {
        state.required = true;
        render(<MfaSetup mfaEnabled />);

        expect(screen.getByText('Required by policy')).toBeInTheDocument();
        expect(screen.getByText(/cannot be disabled here/i)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Disable MFA' })).not.toBeInTheDocument();
    });

    it('shows optional policy and offers self-disable', () => {
        state.required = false;
        render(<MfaSetup mfaEnabled />);

        expect(screen.getByText('Optional')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Disable MFA' })).toBeInTheDocument();
    });

    it('collects recovery proof and sends it once with disable instead of consuming it in confirmation', async () => {
        state.required = false;
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true, json: async () => ({}) })
            .mockResolvedValueOnce({ ok: true, json: async () => ({}) });
        vi.stubGlobal('fetch', fetchMock);
        globalThis.route = (name) => `/mocked/${name}`;
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';

        render(<MfaSetup mfaEnabled />);
        fireEvent.click(screen.getByRole('button', { name: 'Disable MFA' }));
        const proofDialog = screen.getByRole('dialog');
        fireEvent.click(within(proofDialog).getByRole('button', { name: 'Recovery code' }));
        fireEvent.change(within(proofDialog).getByLabelText('Recovery code'), { target: { value: 'ABCD-EFGH-IJKL' } });
        fireEvent.click(within(proofDialog).getByRole('button', { name: 'Continue' }));
        await screen.findByText('Disable Two-Factor Authentication');

        const passwordDialog = screen.getByText('Disable Two-Factor Authentication').closest('.fixed');
        fireEvent.change(within(passwordDialog).getByLabelText('Your Password'), { target: { value: 'password' } });
        fireEvent.click(within(passwordDialog).getByRole('button', { name: 'Disable MFA' }));

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        expect(fetchMock.mock.calls[0][0]).toBe('/mocked/profile.mfa.disable');
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ password: 'password', otp: undefined, recovery_code: 'ABCD-EFGH-IJKL' });
        vi.unstubAllGlobals();
    });
});
