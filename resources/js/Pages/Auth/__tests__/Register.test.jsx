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
vi.mock('@/Components/AddressDropdowns', () => ({
    default: ({ values, onChange }) => (
        <div>
            {['region', 'province', 'city_municipality', 'barangay'].map((field) => (
                <input
                    key={field}
                    data-testid={`addr-${field}`}
                    value={values[field]}
                    onChange={(e) => onChange({ [field]: e.target.value })}
                />
            ))}
        </div>
    ),
}));
vi.mock('@/Components/CountrySelect', () => ({
    default: ({ value, onChange }) => (
        <input data-testid="country" value={value} onChange={(e) => onChange(e.target.value)} />
    ),
}));
vi.mock('@/Components/SearchableSelect', () => ({
    default: ({ value, onChange }) => (
        <input data-testid="occupation" value={value} onChange={(e) => onChange(e.target.value)} />
    ),
}));
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
        axios.get.mockReset();
        // Fresh emails resolve as free; individual tests override for taken emails.
        axios.get.mockResolvedValue({ data: { taken: false } }); // eslint-disable-line no-undef
        global.route = (name, params) => `/${name}${params ? `?email=${params.email}` : ''}`;
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

    it('blocks Continue while the entered email is already registered', async () => {
        axios.get
            .mockResolvedValueOnce({ data: { taken: true } })
            .mockResolvedValueOnce({ data: { taken: false } });

        const { container } = render(<Register />);
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(container.querySelector('input[type="email"]'), { target: { value: 'taken@example.com' } });

        // With everything else filled, the taken email is what keeps Continue locked.
        await waitFor(() => expect(screen.getByText(/already registered/i)).toBeInTheDocument());
        expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled();

        // Entering a free email unlocks the step again.
        fireEvent.change(container.querySelector('input[type="email"]'), { target: { value: 'free@example.com' } });
        await waitFor(() => expect(screen.getByRole('button', { name: 'Continue' })).not.toBeDisabled());
        expect(screen.queryByText(/already registered/i)).not.toBeInTheDocument();
    });

    it('blocks the employment step when the end date precedes the start date', async () => {
        const { container } = render(<Register />);

        // Step 1 → 2
        fireEvent.change(container.querySelector('input[type="email"]'), { target: { value: 'juan@example.com' } });
        fireEvent.change(screen.getByTestId('pw-Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.change(screen.getByTestId('pw-Confirm Password'), { target: { value: 'Str0ng!Passphrase' } });
        fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

        // Step 2 → 3
        const [firstName, lastName] = container.querySelectorAll('input[type="text"]');
        fireEvent.change(firstName, { target: { value: 'Juan' } });
        fireEvent.change(lastName, { target: { value: 'Dela Cruz' } });
        fireEvent.change(container.querySelector('input[type="date"]'), { target: { value: '1990-06-15' } });
        fireEvent.change(container.querySelectorAll('select')[1], { target: { value: 'Male' } });
        fireEvent.change(container.querySelector('input[type="tel"]'), { target: { value: '09171234567' } });
        fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

        // Step 3 → 4
        fireEvent.change(screen.getByTestId('addr-region'), { target: { value: 'R7' } });
        fireEvent.change(screen.getByTestId('addr-province'), { target: { value: 'Cebu' } });
        fireEvent.change(screen.getByTestId('addr-city_municipality'), { target: { value: 'Cebu City' } });
        fireEvent.change(screen.getByTestId('addr-barangay'), { target: { value: 'Barangay 1' } });
        fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
        expect(screen.getByText('Step 4 of 4')).toBeInTheDocument();

        // Step 4, all filled and ordered.
        fireEvent.change(screen.getAllByRole('textbox')[0], { target: { value: 'Acme Corp' } });
        fireEvent.change(container.querySelectorAll('input[type="date"]')[0], { target: { value: '2020-01-10' } });
        fireEvent.change(container.querySelectorAll('input[type="date"]')[1], { target: { value: '2023-05-20' } });
        fireEvent.change(container.querySelectorAll('input[type="date"]')[2], { target: { value: '2024-02-01' } });
        fireEvent.change(screen.getByTestId('country'), { target: { value: 'Japan' } });
        fireEvent.change(screen.getByTestId('occupation'), { target: { value: 'Welder' } });

        await waitFor(() => expect(screen.getByRole('button', { name: 'Continue to Review' })).not.toBeDisabled());

        // End date before start date must be rejected client-side.
        fireEvent.change(container.querySelectorAll('input[type="date"]')[1], { target: { value: '2019-01-01' } });
        expect(screen.getByRole('button', { name: 'Continue to Review' })).toBeDisabled();
        expect(screen.getByText(/must be on or after Start Date/i)).toBeInTheDocument();

        // Fixing it unlocks the submit button again.
        fireEvent.change(container.querySelectorAll('input[type="date"]')[1], { target: { value: '2023-05-20' } });
        expect(screen.getByRole('button', { name: 'Continue to Review' })).not.toBeDisabled();
    });
});
