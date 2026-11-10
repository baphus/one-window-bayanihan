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

import Profile from '../Profile';

describe('OFW account settings', () => {
    it('shows account identity, contact and password only', () => {
        render(<Profile user={{ id: 'u-1', name: 'Juan Cruz', email: 'juan@example.com', contact_number: '09171234567', avatar_url: null }} />);

        expect(screen.getByText('Juan Cruz')).toBeInTheDocument();
        expect(screen.getByText('juan@example.com')).toBeInTheDocument();
        expect(screen.getByLabelText('Contact number')).toBeInTheDocument();
        expect(screen.getByLabelText('New password')).toBeInTheDocument();
        expect(screen.queryByText(/home address/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/employment/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/next of kin/i)).not.toBeInTheDocument();
    });
});
