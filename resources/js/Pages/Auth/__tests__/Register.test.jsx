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
                turnstile: { enabled: true },
            },
        }),
        useForm: (initial) => {
            const [state, setState] = useState(() => initial);
            const [errors, setErrors] = useState({});
            return {
                data: state,
                setData: (key, value) => setState((prev) => ({ ...prev, [key]: value })),
                transform: (callback) => { state = callback(state); },
                post: (url, options) => postMock(url, { ...options, data: state }),
                processing: false,
                errors,
                setError: (key, value) => setErrors((prev) => ({ ...prev, [key]: value })),
                clearErrors: (key) => setErrors((prev) => { const next = { ...prev }; delete next[key]; return next; }),
            };
        },
    };
});

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: ({ onToken, onExpire }) => <div><button type="button" onClick={() => onToken('solved-token')}>Solve CAPTCHA</button><button type="button" onClick={onExpire}>Expire CAPTCHA</button></div> }));
// The crop step only needs to hand the chosen file back; react-easy-crop needs a
// real image decoder that jsdom does not provide.
vi.mock('@/Components/CropImageModal', () => ({
    default: ({ file, open, onCropComplete, onCancel }) => (open ? (
        <div>
            <button type="button" onClick={() => onCropComplete(file)}>Set as photo</button>
            <button type="button" onClick={onCancel}>Cancel crop</button>
        </div>
    ) : null),
}));
vi.mock('@/Components/PasswordInput', () => ({
    default: ({ label, value, onChange, showStrengthMeter, rules }) => (
        <div>
            <label>{label}</label>
            <input data-testid={`pw-${label}`} value={value} onChange={(event) => onChange(event.target.value)} required />
            {showStrengthMeter && <div data-testid="password-guide">{rules.min_length}</div>}
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
        // jsdom has no blob URLs; the preview value is irrelevant to these tests.
        URL.createObjectURL = vi.fn(() => 'blob:preview');
        URL.revokeObjectURL = vi.fn();
    });

    it('requires OFW identity details and leaves profile photo optional', () => {
        const { container } = render(<Register />);

        expect(screen.getByRole('heading', { name: 'Create your account' })).toBeInTheDocument();
        expect(screen.getByLabelText('Email address')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Password')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Confirm password')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Password')).toBeRequired();
        expect(screen.getByTestId('pw-Confirm password')).toBeRequired();
        expect(screen.getAllByTestId('password-guide')).toHaveLength(1);
        expect(screen.getByTestId('password-guide')).toHaveTextContent('8');
        expect(screen.queryByText(/who is this account for/i)).not.toBeInTheDocument();
        expect(screen.getByLabelText(/first name/i)).toBeRequired();
        expect(screen.getByLabelText(/last name/i)).toBeRequired();
        expect(screen.getByLabelText(/date of birth/i)).toBeRequired();
        expect(screen.getByLabelText(/^sex/i)).toBeRequired();
        expect(screen.getByLabelText(/middle name/i)).not.toBeRequired();
        expect(screen.getByLabelText(/^suffix/i)).not.toBeRequired();
        expect(container.querySelector('input[type="file"]')).not.toBeRequired();
        expect(screen.queryByTestId('addr-region')).not.toBeInTheDocument();
    });

    it('prefills identity and email from pending credentials', () => {
        render(<Register initial={{ credentials: { email: 'saved@example.com', first_name: 'Maria', last_name: 'Cruz', date_of_birth: '1990-01-01', sex: 'FEMALE', has_password: true } }} />);
        expect(screen.getByLabelText('Email address')).toHaveValue('saved@example.com');
        expect(screen.getByLabelText(/first name/i)).toHaveValue('Maria');
        expect(screen.getByLabelText(/last name/i)).toHaveValue('Cruz');
        expect(screen.getByLabelText(/date of birth/i)).toHaveValue('1990-01-01');
        expect(screen.getByLabelText(/^sex/i)).toHaveTextContent('Female');
    });

    it('shows and focuses email errors on submit and clears them on correction', () => {
        render(<Register initial={{ credentials: { first_name: 'Juan', last_name: 'Cruz', date_of_birth: '1990-01-01', sex: 'MALE' } }} />);
        fireEvent.click(screen.getByRole('button', { name: 'Solve CAPTCHA' }));
        const email = screen.getByLabelText('Email address');
        const submit = screen.getByRole('button', { name: /continue to email verification/i });

        fireEvent.click(submit);
        expect(screen.getByText('Email is required.')).toBeInTheDocument();
        expect(email).toHaveFocus();
        expect(email).toHaveAttribute('aria-describedby', 'register-email-error');
        expect(postMock).not.toHaveBeenCalled();

        fireEvent.change(email, { target: { value: 'invalid' } });
        expect(screen.queryByText('Email is required.')).not.toBeInTheDocument();
        fireEvent.click(submit);
        expect(screen.getByText('Please provide a valid email address.')).toBeInTheDocument();
        expect(email).toHaveFocus();
        expect(postMock).not.toHaveBeenCalled();
        fireEvent.change(email, { target: { value: 'juan@example.com' } });
        expect(screen.queryByText('Please provide a valid email address.')).not.toBeInTheDocument();
    });

    it('shows Zod email errors for browser-valid addresses', () => {
        render(<Register initial={{ credentials: { first_name: 'Juan', last_name: 'Cruz', date_of_birth: '1990-01-01', sex: 'MALE' } }} />);
        fireEvent.click(screen.getByRole('button', { name: 'Solve CAPTCHA' }));
        const email = screen.getByLabelText('Email address');
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(email, { target: { value: 'juan@localhost' } });
        fireEvent.click(screen.getByRole('button', { name: /continue to email verification/i }));
        expect(screen.getByText('Please provide a valid email address.')).toBeInTheDocument();
        expect(email).toHaveFocus();
        expect(postMock).not.toHaveBeenCalled();
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

    it('submits solved CAPTCHA token and omits it after expiry', () => {
        render(<Register />);
        fireEvent.click(screen.getByRole('button', { name: 'Solve CAPTCHA' }));
        fireEvent.change(screen.getByLabelText('Email address'), { target: { value: 'juan@example.com' } });
        fireEvent.change(screen.getByLabelText(/first name/i), { target: { value: 'Juan' } });
        fireEvent.change(screen.getByLabelText(/last name/i), { target: { value: 'Dela Cruz' } });
        fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: '1990-01-01' } });
        fireEvent.click(screen.getByLabelText(/^sex/i));
        fireEvent.mouseDown(screen.getByRole('option', { name: 'Male' }));
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.click(screen.getByRole('button', { name: /continue to email verification/i }));

        expect(postMock).toHaveBeenLastCalledWith('/register.store', expect.objectContaining({
            data: expect.objectContaining({ cf_turnstile_response: 'solved-token' }),
        }));

        fireEvent.click(screen.getByRole('button', { name: 'Expire CAPTCHA' }));
        expect(screen.getByRole('button', { name: /continue to email verification/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Solve CAPTCHA' })).toBeInTheDocument();
    });

    it('posts identity details and the cropped photo as multipart registration data', () => {
        const { container } = render(<Register />);
        fireEvent.click(screen.getByRole('button', { name: 'Solve CAPTCHA' }));
        fireEvent.change(screen.getByLabelText('Email address'), { target: { value: 'juan@example.com' } });
        const photo = new File(['photo'], 'profile.jpg', { type: 'image/jpeg' });
        fireEvent.change(container.querySelector('input[type="file"]'), { target: { files: [photo] } });
        fireEvent.click(screen.getByRole('button', { name: 'Set as photo' }));
        fireEvent.change(screen.getByLabelText(/first name/i), { target: { value: 'Juan' } });
        fireEvent.change(screen.getByLabelText(/last name/i), { target: { value: 'Dela Cruz' } });
        fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: '1990-01-01' } });
        fireEvent.click(screen.getByLabelText(/^sex/i));
        fireEvent.mouseDown(screen.getByRole('option', { name: 'Male' }));
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.click(screen.getByRole('button', { name: /continue to email verification/i }));

        expect(postMock).toHaveBeenCalledWith('/register.store', expect.objectContaining({
            forceFormData: true,
            data: expect.objectContaining({ first_name: 'Juan', last_name: 'Dela Cruz', date_of_birth: '1990-01-01', sex: 'MALE', profile_picture: photo }),
        }));
    });
});
