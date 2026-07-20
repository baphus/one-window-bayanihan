import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Login page — login step', () => {
    it('renders email and password inputs with Sign In button', () => {
        mockPageProps = { errors: {} };
        render(<Login />);

        expect(screen.getByRole('textbox')).toBeInTheDocument(); // email input
        expect(screen.getByText(/email address/i)).toBeInTheDocument();
        expect(screen.getByText(/password/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
    });

    it('does not show error message when no login error exists', () => {
        mockPageProps = { errors: {} };
        render(<Login />);

        // No error container rendered
        expect(screen.queryByText(/invalid credentials/i)).not.toBeInTheDocument();
    });

    it('toggles password visibility', () => {
        mockPageProps = { errors: {} };
        render(<Login />);

        // Password input is type="password" by default
        const passwordInput = document.querySelector('input[type="password"]');
        expect(passwordInput).not.toBeNull();

        // Click the visibility toggle button (the eye icon)
        const toggleBtn = passwordInput.closest('div').querySelector('button[type="button"]');
        fireEvent.click(toggleBtn);

        // After toggle, it should be type="text"
        expect(passwordInput).toHaveAttribute('type', 'text');
    });
});


