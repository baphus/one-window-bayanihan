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
            // Two useForm() instances exist: the OTP form (has an `otp` key) and
            // the "send code" form (empty). Calling post() on the send form must
            // invoke onSuccess so the review page reveals the OTP input.
            const isOtpForm = Object.keys(initial ?? {}).includes('otp');

            // Reactive state so typing the OTP actually enables the submit button.
            const [data, setData] = useState({ ...initial });

            return {
                data,
                setData: (keyOrObj, value) => setData((prev) => (
                    typeof keyOrObj === 'object' && keyOrObj !== null
                        ? { ...prev, ...keyOrObj }
                        : { ...prev, [keyOrObj]: value }
                )),
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

// The picker delegates cropping to a canvas-based modal that jsdom cannot run.
// Stub it so these tests focus on how RegisterReview submits the chosen File.
vi.mock('@/Components/AvatarPicker', () => ({
    default: ({ onFileChange, error }) => (
        <div>
            <button
                type="button"
                data-testid="pick-photo"
                onClick={() => onFileChange(new File(['x'], 'me.jpg', { type: 'image/jpeg' }))}
            >
                pick
            </button>
            <button type="button" data-testid="clear-photo" onClick={() => onFileChange(null)}>clear</button>
            {error && <p>{error}</p>}
        </div>
    ),
}));

import RegisterReview from '../RegisterReview';

const pending = {
    type: 'OFW',
    suffix: 'Jr',
    date_of_birth: '1995-03-15',
    sex: 'Male',
    contact_number: '+639171234567',
    address: { region: '0700000000', province: 'Cebu', city_municipality: 'Cebu City', barangay: 'Lahug', street: 'Test Street' },
    employment: {
        employer_name: 'Test Employer',
        position: 'Nurse',
        start_date: '2010-04-01',
        end_date: '',
        is_present: true,
        last_country: 'Saudi Arabia',
        last_position: 'Staff Nurse',
        date_of_arrival: '2024-01-10',
    },
};

describe('RegisterReview', () => {
    beforeEach(() => {
        postMock.mockClear();
        global.route = (name) => `/${name}`;
    });

    it('renders the entered profile for review', () => {
        render(<RegisterReview pendingName={{ first_name: 'Juan', middle_name: 'Santos', last_name: 'Cruz' }} pending={pending} />);

        expect(screen.getByText('Juan Santos Cruz Jr')).toBeInTheDocument();
        expect(screen.getByText('Test Employer')).toBeInTheDocument();
        expect(screen.getByText('2010-04-01 – Present')).toBeInTheDocument();
    });

    it('hides the code field until the code is sent', () => {
        render(<RegisterReview pendingName={{}} pending={pending} />);

        expect(screen.queryByPlaceholderText('6-digit code')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /send verification code/i })).toBeInTheDocument();
    });
});

/**
 * The review step is the request that actually creates the account, so the
 * optional profile picture is submitted from here as multipart form data.
 */
describe('RegisterReview profile picture', () => {
    beforeEach(() => {
        postMock.mockClear();
        global.route = (name) => `/${name}`;
    });

    function renderAndRevealCodeField() {
        const utils = render(
            <RegisterReview pendingName={{ first_name: 'Juan', middle_name: '', last_name: 'Cruz' }} pending={pending} />
        );
        // Send the code first: the OTP input (and the submit button) only
        // render after that succeeds.
        fireEvent.click(screen.getByRole('button', { name: /send verification code/i }));
        return utils;
    }

    it('attaches the chosen photo to the multipart submission', () => {
        const { container } = renderAndRevealCodeField();

        const otpInput = container.querySelector('input[inputmode="numeric"]');
        fireEvent.change(otpInput, { target: { value: '123456' } });
        fireEvent.click(screen.getByTestId('pick-photo'));

        fireEvent.click(screen.getByRole('button', { name: /Verify & Finish/i }));

        expect(postMock).toHaveBeenCalledTimes(1);
        const [url, payload, options] = postMock.mock.calls[0];

        expect(url).toBe('/register.review.store');
        expect(payload).toBeInstanceOf(FormData);
        expect(options).toMatchObject({ forceFormData: true });
        expect(payload.get('otp')).toBe('123456');
        expect(payload.get('profile_picture')).toBeInstanceOf(File);
    });

    it('submits without a photo when none is chosen', () => {
        const { container } = renderAndRevealCodeField();

        const otpInput = container.querySelector('input[inputmode="numeric"]');
        fireEvent.change(otpInput, { target: { value: '123456' } });
        fireEvent.click(screen.getByRole('button', { name: /Verify & Finish/i }));

        const [, payload] = postMock.mock.calls[0];
        expect(payload).toBeInstanceOf(FormData);
        expect(payload.get('profile_picture')).toBeNull();
    });

    it('drops the photo again when the selection is cleared', () => {
        const { container } = renderAndRevealCodeField();

        const otpInput = container.querySelector('input[inputmode="numeric"]');
        fireEvent.change(otpInput, { target: { value: '123456' } });

        fireEvent.click(screen.getByTestId('pick-photo'));
        fireEvent.click(screen.getByTestId('clear-photo'));
        fireEvent.click(screen.getByRole('button', { name: /Verify & Finish/i }));

        const [, payload] = postMock.mock.calls[0];
        expect(payload.get('profile_picture')).toBeNull();
    });
});
