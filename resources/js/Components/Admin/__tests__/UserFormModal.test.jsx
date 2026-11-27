import '@testing-library/jest-dom/vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const postMock = vi.fn();
const pageProps = { auth: { user: { id: 'test-user' } }, roles: { ADMIN: 'ADMIN', CASE_MANAGER: 'CASE_MANAGER', AGENCY: 'AGENCY', OFW: 'OFW' }, passwordRules: { min_length: 8, require_uppercase: false, require_lowercase: false, require_number: false, require_symbol: false } };
const patchMock = vi.fn();
vi.mock('@/Hooks/useSessionDraft', () => ({ default: () => ({ clear: vi.fn(), onSuccess: vi.fn(), status: '' }) }));
vi.mock('@/Components/DraftControls', () => ({ default: () => null }));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        usePage: () => ({ props: pageProps }),
        router: { reload: vi.fn() },
        useForm: (initial) => {
            const [state, setState] = useState(initial);
            const [errors, setErrors] = useState({});
            let transformed;
            return {
                data: state,
                setData: (key, value) => setState((prev) => ({ ...prev, [key]: value })),
                transform: (callback) => { transformed = callback(state); },
                post: (url, options) => postMock(url, { ...options, data: transformed ?? state }),
                patch: (url, options) => patchMock(url, { ...options, data: transformed ?? state }),
                processing: false,
                errors,
                setError: (key, value) => setErrors((prev) => ({ ...prev, [key]: value })),
                clearErrors: (key) => setErrors((prev) => {
                    if (!key) return {};
                    const next = { ...prev };
                    delete next[key];
                    return next;
                }),
                reset: (...keys) => setState((prev) => keys.length
                    ? { ...prev, ...Object.fromEntries(keys.map((key) => [key, initial[key] ?? ''])) }
                    : initial),
            };
        },
    };
});

vi.mock('@/Hooks/useMfaStatus', () => ({ default: () => ({ status: null }) }));
vi.mock('@/Components/MfaConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Components/CropImageModal', () => ({ default: () => null }));
vi.mock('@/Components/PasswordStrengthMeter', () => ({ default: () => null }));

import UserFormModal from '../UserFormModal';

const roles = ['Case Manager', 'Agency Focal', 'System Admin', 'Citizen (OFW)'];

function chooseRole(label) {
    const combobox = screen.getAllByRole('combobox').find((node) => roles.some((role) => node.textContent.includes(role) && /case manager|citizen/i.test(node.textContent)));
    fireEvent.click(combobox);
    fireEvent.mouseDown(screen.getByRole('option', { name: label }));
}

beforeEach(() => {
    postMock.mockClear();
    patchMock.mockClear();
    globalThis.route = (name, id) => id === undefined ? `/${name}` : `/${name}/${id}`;
});

describe('admin citizen form', () => {
    it('creates an OFW with identity demographics, an active account, and the photo contract', () => {
        const { container } = render(<UserFormModal user={null} agencies={[]} initialMode="create" onClose={vi.fn()} />);

        chooseRole('Citizen (OFW)');

        expect(screen.getByLabelText(/date of birth/i)).toBeRequired();
        expect(screen.getByLabelText(/^sex/i)).toBeRequired();
        expect(screen.getByText(/hand this password over manually outside the app/i)).toBeInTheDocument();

        fireEvent.submit(container.querySelector('form'));

        expect(postMock).toHaveBeenCalledWith('/admin.users.store', expect.objectContaining({ forceFormData: true }));
        expect(postMock.mock.calls[0][1].data).toEqual(expect.objectContaining({
            role: 'OFW',
            is_active: true,
            remove_profile_picture: false,
        }));
    });

    it('locks the role when editing an OFW and offers the admin email and password flows', () => {
        render(
            <UserFormModal
                user={{
                    id: 'user-1',
                    role: 'OFW',
                    first_name: 'Maria',
                    last_name: 'Cruz',
                    email: 'maria@example.test',
                    is_active: true,
                    must_change_password: true,
                }}
                agencies={[]}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByText(/role cannot be changed after creation/i)).toBeInTheDocument();
        expect(screen.getByText('Change email')).toBeInTheDocument();
        expect(screen.getByLabelText('Email Address')).toHaveAttribute('readonly');
        expect(screen.getByText(/still uses a temporary password/i)).toBeInTheDocument();
        expect(screen.queryByText('MFA Requirement')).not.toBeInTheDocument();
    });

    it('resets an OFW password with admin re-authentication through a spoofed multipart POST', () => {
        render(
            <UserFormModal
                user={{ id: 'user-1', role: 'OFW', email: 'maria@example.test', is_active: true }}
                agencies={[]}
                onClose={vi.fn()}
            />,
        );

        fireEvent.change(screen.getByLabelText(/New temporary password/ ), { target: { value: 'N3w!Temporary' } });
        fireEvent.change(screen.getByLabelText(/Confirm temporary password/ ), { target: { value: 'N3w!Temporary' } });
        fireEvent.change(screen.getByLabelText(/Your current admin password/ ), { target: { value: 'Adm1n!Password' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reset Password' }));

        expect(postMock).toHaveBeenCalledWith('/admin.users.update/user-1', expect.objectContaining({ forceFormData: true }));
        expect(postMock.mock.calls[0][1].data).toEqual(expect.objectContaining({
            _method: 'patch',
            password: 'N3w!Temporary',
            password_confirmation: 'N3w!Temporary',
            current_password: 'Adm1n!Password',
        }));
    });

    it('blocks a password reset when the confirmation does not match', () => {
        render(
            <UserFormModal
                user={{ id: 'user-1', role: 'OFW', email: 'maria@example.test', is_active: true }}
                agencies={[]}
                onClose={vi.fn()}
            />,
        );

        fireEvent.change(screen.getByLabelText(/New temporary password/ ), { target: { value: 'N3w!Temporary' } });
        fireEvent.change(screen.getByLabelText(/Confirm temporary password/ ), { target: { value: 'Different!1' } });
        fireEvent.change(screen.getByLabelText(/Your current admin password/ ), { target: { value: 'Adm1n!Password' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reset Password' }));

        expect(screen.getByText('Passwords do not match.')).toBeInTheDocument();
        expect(postMock).not.toHaveBeenCalled();
    });

    it('requires the admin OTP before saving a changed OFW email', () => {
        const { container } = render(
            <UserFormModal
                user={{ id: 'user-1', role: 'OFW', email: 'maria@example.test', is_active: true }}
                agencies={[]}
                onClose={vi.fn()}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Change email' }));
        fireEvent.change(screen.getByLabelText('New email address'), { target: { value: 'new.maria@example.test' } });
        fireEvent.change(screen.getByLabelText(/Your admin password/ ), { target: { value: 'Adm1n!Password' } });
        fireEvent.click(screen.getByRole('button', { name: 'Send verification code' }));

        expect(postMock.mock.calls.at(-1)[0]).toBe('/admin.users.email-change.send-otp/user-1');
        expect(postMock.mock.calls.at(-1)[1].data).toEqual({ admin_password: 'Adm1n!Password', new_email: 'new.maria@example.test' });

        act(() => postMock.mock.calls.at(-1)[1].onSuccess());
        expect(screen.getByText(/ne\*\*\*@example\.test/)).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Verification code'), { target: { value: '123456' } });
        fireEvent.click(screen.getByRole('button', { name: 'Verify code' }));
        expect(postMock.mock.calls.at(-1)[0]).toBe('/admin.users.email-change.verify-otp/user-1');
        expect(postMock.mock.calls.at(-1)[1].data).toEqual({ otp: '123456' });

        act(() => postMock.mock.calls.at(-1)[1].onSuccess());
        expect(screen.getByLabelText('Email Address')).toHaveValue('new.maria@example.test');

        fireEvent.submit(container.querySelector('form'));
        expect(postMock.mock.calls.at(-1)[0]).toBe('/admin.users.update/user-1');
        expect(postMock.mock.calls.at(-1)[1].data).toEqual(expect.objectContaining({
            _method: 'patch',
            email: 'new.maria@example.test',
        }));
    });
});
