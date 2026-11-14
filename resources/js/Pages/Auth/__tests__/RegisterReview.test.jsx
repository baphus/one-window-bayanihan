import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        Head: () => null,
        Link: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a>,
        usePage: () => ({ props: { flash: {} } }),
        useForm: (initial) => {
            const isOtpForm = Object.keys(initial ?? {}).includes('otp');
            const [data, setData] = useState({ ...initial });

            return {
                data,
                setData: (key, value) => setData((prev) => ({ ...prev, [key]: value })),
                post: isOtpForm ? postMock : vi.fn((_url, options) => options?.onSuccess?.()),
                processing: false,
                errors: {},
            };
        },
        router: { post: postMock },
    };
});
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Hooks/useToast', () => ({
    useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}));

import RegisterReview from '../RegisterReview';

describe('RegisterReview', () => {
    beforeEach(() => {
        postMock.mockClear();
        global.route = (name) => `/${name}`;
    });

    it('shows only the email awaiting verification', () => {
        render(<RegisterReview pending={{ email: 'juan@example.com' }} />);

        expect(screen.getByText(/juan@example.com/)).toBeInTheDocument();
        expect(screen.queryByText(/test employer/i)).not.toBeInTheDocument();
        expect(screen.queryByText(/home address/i)).not.toBeInTheDocument();
    });

    it('hides the code field until the code is sent', () => {
        render(<RegisterReview pending={{ email: 'juan@example.com' }} />);

        expect(screen.queryByPlaceholderText('6-digit code')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /send verification code/i })).toBeInTheDocument();
    });

    it('posts the OTP alone to finish registration', () => {
        const { container } = render(<RegisterReview pending={{ email: 'juan@example.com' }} />);
        fireEvent.click(screen.getByRole('button', { name: /send verification code/i }));

        const otpInput = container.querySelector('input[inputmode="numeric"]');
        fireEvent.change(otpInput, { target: { value: '123456' } });
        fireEvent.click(screen.getByRole('button', { name: /verify and finish/i }));

        expect(postMock).toHaveBeenCalledWith('/register.review.store', { otp: '123456' }, expect.any(Object));
    });
});
