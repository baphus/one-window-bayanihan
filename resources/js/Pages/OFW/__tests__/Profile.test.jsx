import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { passwordRules: { min_length: 8 } } }),
    useForm: (initial) => ({
        data: initial,
        setData: vi.fn(),
        put: vi.fn(),
        transform: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        setError: vi.fn(),
        processing: false,
        errors: {},
        isDirty: false,
    }),
}));
vi.mock('@/Layouts/OfwPortalLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Components/ProfilePictureUpload', () => ({ default: () => null }));
vi.mock('@/Pages/Profile/Partials/ChangeEmailForm', () => ({ default: () => <section aria-label="Change email"><h2>Change Email</h2></section> }));
vi.mock('@/Pages/Profile/Partials/UpdatePasswordForm', () => ({ default: () => <section aria-label="Update password"><h2>Update Password</h2><label>New password</label></section> }));
vi.mock('@/Components/MfaSetup', () => ({ default: ({ mfaRequired = false }) => <section aria-label="Two-Factor Authentication"><h2>Two-Factor Authentication</h2><p>{mfaRequired ? 'MFA required' : 'MFA optional'}</p></section> }));
vi.mock('@/Pages/Profile/Partials/DeleteUserForm', () => ({ default: ({ description, confirmationDescription }) => <section aria-label="Delete account"><h2>Delete Account</h2><p>{description}</p><p>{confirmationDescription}</p><label>Password</label></section> }));

import Profile from '../Profile';

const user = {
    id: 'u-1',
    name: 'Juan Cruz',
    first_name: 'Juan',
    middle_name: '',
    last_name: 'Cruz',
    suffix: '',
    date_of_birth: '1990-01-01',
    sex: 'MALE',
    email: 'juan@example.com',
    contact_number: '09171234567',
    avatar_url: null,
};

describe('OFW account settings', () => {
    it('shows six account sections and excludes staff-only profile fields', () => {
        render(<Profile user={user} />);

        expect(screen.getByLabelText(/first name/i)).toHaveValue('Juan');
        expect(screen.getByLabelText(/last name/i)).toHaveValue('Cruz');
        expect(screen.getByLabelText(/date of birth/i)).toHaveValue('1990-01-01');
        expect(screen.getByLabelText(/^sex/i)).toHaveValue('MALE');
        expect(screen.getByText('juan@example.com')).toBeInTheDocument();
        expect(screen.getByLabelText('Contact number')).toBeInTheDocument();
        expect(screen.getByText('New password')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Account' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Contact' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Change Email' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Update Password' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Two-Factor Authentication' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Delete Account' })).toBeInTheDocument();
        expect(screen.queryByText(/home address|employment|next of kin|position|department|office location|emergency contact/i)).not.toBeInTheDocument();
        expect(screen.getByText(/editing these details updates your account only/i)).toBeInTheDocument();
        expect(screen.getByText('MFA optional')).toBeInTheDocument();
        expect(screen.getByText(/deactivates access.*case, document, message, and audit records are retained/i)).toBeInTheDocument();
    });

    it('passes required MFA policy through when backend marks it required', () => {
        render(<Profile user={user} mfaRequired />);
        expect(screen.getByText('MFA required')).toBeInTheDocument();
    });
});
