import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import MfaChallenge from '../MfaChallenge';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { errors: {} } }),
    useForm: () => ({ data: { code: '' }, errors: {}, processing: false, setData: vi.fn(), post: vi.fn(), clearErrors: vi.fn() }),
}));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
globalThis.route = (name) => `/${name}`;

afterEach(cleanup);

describe('MFA challenge', () => {
    it('restores focus to recovery-code input after switching modes', () => {
        render(<MfaChallenge />);
        fireEvent.click(screen.getByRole('button', { name: 'Use a recovery code instead' }));
        expect(screen.getByPlaceholderText('Enter your recovery code')).toHaveFocus();
    });
});
