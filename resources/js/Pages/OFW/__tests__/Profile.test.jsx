import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const { pageProps } = vi.hoisted(() => ({ pageProps: { passwordRules: { min_length: 8 }, occupationOptions: [] } }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    useForm: (initial) => ({ data: initial, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }),
}));
vi.mock('@/Layouts/OfwPortalLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ dirty: false, UnsavedModal: null }) }));

import Profile from '../Profile';

describe('OFW Profile identity', () => {
    it('appends suffix to name and does not show a separate suffix field', () => {
        const { rerender } = render(<Profile user={{ name: 'Juan Cruz', email: 'juan@example.com' }} client={{ first_name: 'Juan', last_name: 'Cruz', suffix: 'Jr' }} />);

        expect(screen.getByRole('heading', { name: 'Juan Cruz Jr' })).toBeInTheDocument();
        expect(screen.queryByText('Assigned office')).not.toBeInTheDocument();
        expect(screen.queryByText('DMW Region VII')).not.toBeInTheDocument();
        expect(screen.queryByText('Suffix', { selector: 'p' })).not.toBeInTheDocument();

        rerender(<Profile user={{ name: 'Juan Cruz', email: 'juan@example.com' }} client={{ first_name: 'Juan', last_name: 'Cruz' }} />);
        expect(screen.getByRole('heading', { name: 'Juan Cruz' })).toBeInTheDocument();
        expect(screen.queryByText('Suffix', { selector: 'p' })).not.toBeInTheDocument();
    });
});
