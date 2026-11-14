import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import axios from 'axios';

const postMock = vi.fn();

vi.mock('axios', () => ({ default: { get: vi.fn(), isCancel: () => false } }));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        Link: ({ children, href }) => <a href={href}>{children}</a>,
        usePage: () => ({
            props: {
                passwordRules: { min_length: 8, require_mixed_case: true, require_numbers: true, require_symbols: true },
                turnstile: { enabled: false },
            },
        }),
        useForm: (initial) => {
            const [state, setState] = useState(() => initial);
            return {
                data: state,
                setData: (key, value) => setState((prev) => ({ ...prev, [key]: value })),
                post: (url, options) => postMock(url, { ...options, data: state }),
                processing: false,
                errors: {},
            };
        },
    };
});

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: () => null }));
vi.mock('@/Components/PasswordInput', () => ({
    default: ({ label, value, onChange }) => (
        <div>
            <label>{label}</label>
            <input data-testid={`pw-${label}`} value={value} onChange={(event) => onChange(event.target.value)} />
        </div>
    ),
}));

import Register from '../Register';

describe('Register', () => {
    beforeEach(() => {
        postMock.mockClear();
        axios.get.mockReset();
        axios.get.mockResolvedValue({ data: { taken: false } });
        global.route = (name) => `/${name}`;
    });

    it('requires OFW identity details and leaves profile photo optional', () => {
        render(<Register />);

        expect(screen.getByRole('heading', { name: 'Create your account' })).toBeInTheDocument();
        expect(screen.getByLabelText('Email address')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Password')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Confirm password')).toBeInTheDocument();
        expect(screen.queryByText(/who is this account for/i)).not.toBeInTheDocument();
        expect(screen.getByLabelText(/first name/i)).toBeRequired();
        expect(screen.getByLabelText(/last name/i)).toBeRequired();
        expect(screen.getByLabelText(/date of birth/i)).toBeRequired();
        expect(screen.getByLabelText(/^sex/i)).toBeRequired();
        expect(screen.getByLabelText(/middle name/i)).not.toBeRequired();
        expect(screen.getByLabelText(/^suffix/i)).not.toBeRequired();
        expect(screen.getByLabelText(/profile photo/i)).not.toBeRequired();
        expect(screen.queryByTestId('addr-region')).not.toBeInTheDocument();
    });

    it('prefills identity and email from pending credentials', () => {
        render(<Register initial={{ credentials: { email: 'saved@example.com', first_name: 'Maria', last_name: 'Cruz', date_of_birth: '1990-01-01', sex: 'FEMALE', has_password: true } }} />);
        expect(screen.getByLabelText('Email address')).toHaveValue('saved@example.com');
        expect(screen.getByLabelText(/first name/i)).toHaveValue('Maria');
        expect(screen.getByLabelText(/last name/i)).toHaveValue('Cruz');
        expect(screen.getByLabelText(/date of birth/i)).toHaveValue('1990-01-01');
        expect(screen.getByLabelText(/^sex/i)).toHaveValue('FEMALE');
    });

    it('blocks submission while the email is already registered', async () => {
        axios.get.mockResolvedValueOnce({ data: { taken: true } });

        render(<Register />);
        fireEvent.change(screen.getByLabelText('Email address'), { target: { value: 'taken@example.com' } });
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm password'), { target: { value: 'Str0ng!Passphrase' } });

        await waitFor(() => expect(screen.getByText(/already registered/i)).toBeInTheDocument());
        expect(screen.getByRole('button', { name: /continue to email verification/i })).toBeDisabled();
    });

    it('posts identity details and optional photo as multipart registration data', () => {
        render(<Register />);
        fireEvent.change(screen.getByLabelText('Email address'), { target: { value: 'juan@example.com' } });
        const photo = new File(['photo'], 'profile.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText(/profile photo/i), { target: { files: [photo] } });
        fireEvent.change(screen.getByLabelText(/first name/i), { target: { value: 'Juan' } });
        fireEvent.change(screen.getByLabelText(/last name/i), { target: { value: 'Dela Cruz' } });
        fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: '1990-01-01' } });
        fireEvent.change(screen.getByLabelText(/^sex/i), { target: { value: 'MALE' } });
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.click(screen.getByRole('button', { name: /continue to email verification/i }));

        expect(postMock).toHaveBeenCalledWith('/register.store', expect.objectContaining({
            forceFormData: true,
            data: expect.objectContaining({ first_name: 'Juan', last_name: 'Dela Cruz', date_of_birth: '1990-01-01', sex: 'MALE', profile_picture: photo }),
        }));
    });
});
