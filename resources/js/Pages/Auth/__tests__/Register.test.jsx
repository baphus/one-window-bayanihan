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
                post: postMock,
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

    it('asks only for email and password', () => {
        render(<Register />);

        expect(screen.getByRole('heading', { name: 'Create your account' })).toBeInTheDocument();
        expect(screen.getByLabelText('Email address')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Password')).toBeInTheDocument();
        expect(screen.getByTestId('pw-Confirm password')).toBeInTheDocument();
        expect(screen.queryByText(/who is this account for/i)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/first name/i)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/date of birth/i)).not.toBeInTheDocument();
        expect(screen.queryByTestId('addr-region')).not.toBeInTheDocument();
    });

    it('prefills the email from existing credentials', () => {
        render(<Register initial={{ credentials: { email: 'saved@example.com', has_password: true } }} />);
        expect(screen.getByLabelText('Email address')).toHaveValue('saved@example.com');
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

    it('posts only email and password fields', () => {
        render(<Register />);
        fireEvent.change(screen.getByLabelText('Email address'), { target: { value: 'juan@example.com' } });
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.click(screen.getByRole('button', { name: /continue to email verification/i }));

        expect(postMock).toHaveBeenCalledWith('/register.store', expect.any(Object));
    });
});
