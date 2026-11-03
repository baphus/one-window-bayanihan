import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import MfaConfirmDialog from '../MfaConfirmDialog';

const future = () => new Date(Date.now() + 60_000).toISOString();

afterEach(cleanup);

beforeEach(() => {
    globalThis.route = (name) => `/mocked/${name}`;
});

describe('shared MFA confirmation', () => {
    it('has accessible name, focuses initial field, contains Tab, and restores focus on close', async () => {
        const trigger = document.createElement('button');
        trigger.textContent = 'Open confirmation';
        document.body.appendChild(trigger);
        trigger.focus();
        const onCancel = vi.fn();
        const { rerender } = render(
            <MfaConfirmDialog open title="Verify this change" onCancel={onCancel} onConfirmed={vi.fn()} />,
        );

        const dialog = screen.getByRole('dialog', { name: 'Verify this change' });
        const code = screen.getByLabelText('Authentication code');
        await waitFor(() => expect(code).toHaveFocus());

        const buttons = within(dialog).getAllByRole('button').filter((button) => !button.disabled);
        const interiorTab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        code.focus();
        dialog.dispatchEvent(interiorTab);
        expect(interiorTab.defaultPrevented).toBe(false);
        // Native Tab can now reach the recovery toggle, code input and controls in order.
        const recoveryToggle = buttons[1];
        expect(recoveryToggle).toBeInTheDocument();

        buttons.at(-1).focus();
        fireEvent.keyDown(dialog, { key: 'Tab' });
        expect(buttons[0]).toHaveFocus();

        buttons[0].focus();
        fireEvent.keyDown(dialog, { key: 'Tab', shiftKey: true });
        expect(buttons.at(-1)).toHaveFocus();

        fireEvent.keyDown(dialog, { key: 'Escape' });
        expect(onCancel).toHaveBeenCalledOnce();
        rerender(<MfaConfirmDialog open={false} onCancel={onCancel} onConfirmed={vi.fn()} />);
        expect(trigger).toHaveFocus();
        trigger.remove();
    });

    it('posts the authenticator code and hands the reason back to the caller', async () => {
        const onConfirmed = vi.fn();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ confirmed: true, confirmed_until: future() }),
        });
        vi.stubGlobal('fetch', fetchMock);

        render(
            <MfaConfirmDialog open reasonRequired onCancel={vi.fn()} onConfirmed={onConfirmed} />,
        );

        fireEvent.change(screen.getByLabelText('Authentication code'), { target: { value: '123456' } });
        fireEvent.change(screen.getByLabelText('Reason'), { target: { value: 'Lost authenticator' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(onConfirmed).toHaveBeenCalledWith({
            reason: 'Lost authenticator',
            password: '',
            proof: { otp: '123456' },
        }));

        expect(fetchMock).toHaveBeenCalledWith('/mocked/profile.mfa.confirm', expect.objectContaining({ method: 'POST' }));
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ otp: '123456' });
        vi.unstubAllGlobals();
    });

    it('collect-only mode returns proof without consuming it in the confirmation endpoint', async () => {
        const onConfirmed = vi.fn();
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        render(<MfaConfirmDialog open collectOnly onCancel={vi.fn()} onConfirmed={onConfirmed} />);
        fireEvent.click(screen.getByRole('button', { name: 'Recovery code' }));
        fireEvent.change(screen.getByLabelText('Recovery code'), { target: { value: 'ABCD-EFGH-IJKL' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(fetchMock).not.toHaveBeenCalled();
        expect(onConfirmed).toHaveBeenCalledWith({ proof: { recovery_code: 'ABCD-EFGH-IJKL' } });
        vi.unstubAllGlobals();
    });

    it('shows the server error and does not continue when the code is rejected', async () => {
        const onConfirmed = vi.fn();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ errors: { otp: ['The code is invalid.'] } }),
        }));

        render(<MfaConfirmDialog open onCancel={vi.fn()} onConfirmed={onConfirmed} />);

        fireEvent.change(screen.getByLabelText('Authentication code'), { target: { value: '000000' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(await screen.findByText('The code is invalid.')).toBeInTheDocument();
        expect(onConfirmed).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('honours an unexpired sign-in window and only asks for the reason', () => {
        render(
            <MfaConfirmDialog open reasonRequired confirmedUntil={future()} onCancel={vi.fn()} onConfirmed={vi.fn()} />,
        );

        expect(screen.queryByLabelText('Authentication code')).not.toBeInTheDocument();
        expect(screen.getByText(/recent MFA sign-in is still valid/i)).toBeInTheDocument();
        expect(screen.getByLabelText('Reason')).toBeInTheDocument();
    });
});
