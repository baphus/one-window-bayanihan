import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

let mockPageProps = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    usePage: () => ({ props: mockPageProps }),
    router: { post: vi.fn() },
}));

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/TurnstileWidget', () => ({
    default: ({ onToken }) => (
        <button data-testid="turnstile" onClick={() => onToken('mock-token')}>Turnstile</button>
    ),
}));

beforeEach(() => {
    mockPageProps = {};
    global.route = (name) => `/${name}`;
});

import Login from '../Login';

describe('Login page — login step', () => {
    it('renders email and password inputs with Sign In button', () => {
        mockPageProps = { errors: {} };
        render(<Login />);

        expect(screen.getByRole('textbox')).toBeInTheDocument();
        expect(screen.getByText(/email address/i)).toBeInTheDocument();
        expect(screen.getByText(/password/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
    });

    it('does not show error message when no login error exists', () => {
        mockPageProps = { errors: {} };
        render(<Login />);

        expect(screen.queryByText(/invalid credentials/i)).not.toBeInTheDocument();
    });

    it('toggles password visibility', () => {
        mockPageProps = { errors: {} };
        render(<Login />);

        const passwordInput = document.querySelector('input[type="password"]');
        expect(passwordInput).not.toBeNull();

        const toggleBtn = passwordInput.closest('div').querySelector('button[type="button"]');
        fireEvent.click(toggleBtn);

        expect(passwordInput).toHaveAttribute('type', 'text');
    });
});

describe('Login page — OTP step', () => {
    it('renders OTP verification UI when step is "otp"', () => {
        mockPageProps = { errors: {}, step: 'otp', email: 'test@example.com', hint: 'te***@example.com' };
        render(<Login />);

        expect(screen.getByText('Verify Your Identity')).toBeInTheDocument();
        expect(screen.getByText(/te\*\*\*@example\.com/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /verify & continue/i })).toBeInTheDocument();
    });

    it('renders 6 OTP digit inputs', () => {
        mockPageProps = { errors: {}, step: 'otp', email: 'test@example.com', hint: 'te***@example.com' };
        render(<Login />);

        const numericInputs = screen.getAllByRole('textbox')
            .filter(input => input.getAttribute('inputmode') === 'numeric');
        expect(numericInputs).toHaveLength(6);
    });

    it('auto-fills OTP when debug_otp prop is provided', () => {
        mockPageProps = {
            errors: {},
            step: 'otp',
            email: 'test@example.com',
            hint: 'te***@example.com',
            debug_otp: '123456',
        };
        render(<Login />);

        const numericInputs = screen.getAllByRole('textbox')
            .filter(input => input.getAttribute('inputmode') === 'numeric');

        expect(numericInputs[0]).toHaveValue('1');
        expect(numericInputs[1]).toHaveValue('2');
        expect(numericInputs[2]).toHaveValue('3');
        expect(numericInputs[3]).toHaveValue('4');
        expect(numericInputs[4]).toHaveValue('5');
        expect(numericInputs[5]).toHaveValue('6');
    });

    it('shows debug mode banner when debug_otp is present', () => {
        mockPageProps = {
            errors: {},
            step: 'otp',
            email: 'test@example.com',
            hint: 'te***@example.com',
            debug_otp: '654321',
        };
        render(<Login />);

        expect(screen.getByText(/debug mode/i)).toBeInTheDocument();
        expect(screen.getByText(/654321/)).toBeInTheDocument();
    });

    it('shows error when submitting incomplete OTP', () => {
        mockPageProps = { errors: {}, step: 'otp', email: 'test@example.com', hint: 'te***@example.com' };
        render(<Login />);

        fireEvent.click(screen.getByRole('button', { name: /verify & continue/i }));
        expect(screen.getByText(/please enter the complete 6-digit code/i)).toBeInTheDocument();
    });

    it('allows returning to login step', () => {
        mockPageProps = { errors: {}, step: 'otp', email: 'test@example.com', hint: 'te***@example.com' };
        render(<Login />);

        fireEvent.click(screen.getByText(/return to login/i));
        expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
    });
});
