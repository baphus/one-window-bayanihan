import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const { reset, clearErrors } = vi.hoisted(() => ({ reset: vi.fn(), clearErrors: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { passwordRules: { min_length: 8 } } }),
    useForm: (data) => ({ data, setData: vi.fn(), errors: {}, put: vi.fn(), reset, processing: false, setError: vi.fn(), clearErrors }),
}));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn() }) }));
vi.mock('@/Hooks/useClientValidation', () => ({ default: () => ({ validate: () => true }) }));
vi.mock('@/Components/PasswordInput', () => ({ default: ({ label }) => <input aria-label={label} /> }));

import UpdatePasswordForm from '../Partials/UpdatePasswordForm';

describe('Update password form', () => {
    it('reveals fields on request and clears them when cancelled', () => {
        render(<UpdatePasswordForm />);
        expect(screen.queryByRole('textbox', { name: 'Current Password' })).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Change password' }));
        expect(screen.getByRole('textbox', { name: 'Current Password' })).toBeInTheDocument();
        expect(screen.getByRole('textbox', { name: 'New Password' })).toBeInTheDocument();
        expect(screen.getByRole('textbox', { name: 'Confirm Password' })).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(reset).toHaveBeenCalled();
        expect(clearErrors).toHaveBeenCalled();
        expect(screen.queryByRole('textbox', { name: 'Current Password' })).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Change password' })).toBeInTheDocument();
    });
});
