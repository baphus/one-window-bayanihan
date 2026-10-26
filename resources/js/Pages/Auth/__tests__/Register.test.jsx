import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const postMock = vi.fn();

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
        // Reactive state mock so step-gate/disabled assertions re-render.
        useForm: (initial) => {
            const [state, setState] = useState(() => initial);
            return {
                data: state,
                setData: (keyOrObj, value) => setState((prev) => (
                    typeof keyOrObj === 'object' && keyOrObj !== null
                        ? { ...prev, ...keyOrObj }
                        : { ...prev, [keyOrObj]: typeof value === 'function' ? value(prev[keyOrObj]) : value }
                )),
                post: postMock,
                processing: false,
                errors: {},
                reset: vi.fn(),
                setError: vi.fn(),
                clearErrors: vi.fn(),
            };
        },
    };
});

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/AddressDropdowns', () => ({ default: () => null }));
vi.mock('@/Components/CountrySelect', () => ({ default: () => null }));
vi.mock('@/Components/SearchableSelect', () => ({ default: () => null }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: () => null }));
vi.mock('@/Components/PasswordInput', () => ({
    default: ({ label, value, onChange }) => (
        <div>
            <label>{label}</label>
            <input data-testid={`pw-${label}`} value={value} onChange={(e) => onChange(e.target.value)} />
        </div>
    ),
}));

import Register from '../Register';

describe('Register', () => {
    beforeEach(() => {
        postMock.mockClear();
        global.route = (name) => `/${name}`;
    });

    it('renders the account step first, without the branding panel', () => {
        render(<Register />);

        expect(screen.getByText('Create your account')).toBeInTheDocument();
        expect(screen.getByText('Step 1 of 4')).toBeInTheDocument();
        expect(screen.getByText('I am an OFW')).toBeInTheDocument();
        expect(screen.getByText('Email Address')).toBeInTheDocument();
        // Branding panel is gone.
        expect(screen.queryByAltText('One Window Bayanihan Logo')).not.toBeInTheDocument();
    });

    it('keeps Continue disabled until account fields are filled', () => {
        const { container } = render(<Register />);

        const continueBtn = screen.getByRole('button', { name: 'Continue' });
        expect(continueBtn).toBeDisabled();

        fireEvent.change(container.querySelector('input[type="email"]'), { target: { value: 'juan@example.com' } });
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm Password'), { target: { value: 'Str0ng!Passphrase' } });

        expect(continueBtn).not.toBeDisabled();
    });

    it('advances to Personal Details after completing the account step, and Back returns', () => {
        const { container } = render(<Register />);

        fireEvent.change(container.querySelector('input[type="email"]'), { target: { value: 'juan@example.com' } });
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

        expect(screen.getByText('Step 2 of 4')).toBeInTheDocument();
        expect(screen.getByText('First Name')).toBeInTheDocument();
        expect(screen.getByText('Date of Birth')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Back' }));
        expect(screen.getByText('Step 1 of 4')).toBeInTheDocument();
    });
});
