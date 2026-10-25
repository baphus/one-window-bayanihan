import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const postMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a>,
    usePage: () => ({ props: { flash: {} } }),
    useForm: (initial) => ({
        data: { ...initial },
        setData: vi.fn(),
        post: postMock,
        processing: false,
        errors: {},
    }),
}));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Hooks/useToast', () => ({
    useToast: () => ({ success: vi.fn(), error: vi.fn() }),
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
