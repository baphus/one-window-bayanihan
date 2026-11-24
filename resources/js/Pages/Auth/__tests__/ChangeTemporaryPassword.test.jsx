import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const postMock = vi.fn();

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
        usePage: () => ({ props: {} }),
        useForm: (initial) => {
            const [state, setState] = useState(initial);
            return {
                data: state,
                setData: (key, value) => setState((prev) => ({ ...prev, [key]: value })),
                post: (url, options) => postMock(url, { ...options, data: state }),
                processing: false,
                errors: {},
                reset: vi.fn(),
            };
        },
    };
});

vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/PasswordInput', () => ({
    default: ({ id, label, value, onChange, error, required }) => (
        <div>
            <label htmlFor={id}>{label}</label>
            <input id={id} value={value} onChange={(event) => onChange(event.target.value)} required={required} />
            {error && <p>{error}</p>}
        </div>
    ),
}));

import ChangeTemporaryPassword from '../ChangeTemporaryPassword';

beforeEach(() => {
    postMock.mockClear();
    globalThis.route = (name) => `/${name}`;
});

describe('temporary password page', () => {
    it('posts the current and replacement passwords and offers sign out', () => {
        render(<ChangeTemporaryPassword />);

        fireEvent.change(screen.getByLabelText('Temporary Password'), { target: { value: 'Temp-pass1!' } });
        fireEvent.change(screen.getByLabelText('New Password'), { target: { value: 'New-pass1!' } });
        fireEvent.change(screen.getByLabelText('Confirm New Password'), { target: { value: 'New-pass1!' } });
        fireEvent.click(screen.getByRole('button', { name: 'Set New Password' }));

        expect(postMock).toHaveBeenCalledWith('/password.temporary.update', expect.objectContaining({
            data: { current_password: 'Temp-pass1!', password: 'New-pass1!', password_confirmation: 'New-pass1!' },
        }));
        expect(screen.getByRole('link', { name: 'Sign Out' })).toHaveAttribute('href', '/logout');
    });
});
