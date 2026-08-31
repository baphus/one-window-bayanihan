import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import IntakeIndex from '@/Pages/Intake/Index';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  usePage: () => ({
    url: '/intake',
    props: {
      auth: { user: null },
      turnstile: { enabled: false, site_key: '' },
    },
  }),
}));

// The intake page seam includes the wizard, not the shared landing-page shell.
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/ChatBot', () => ({ default: () => null }));

globalThis.route = vi.fn((name) => `/mock/${name}`);

const completeDraft = {
  email: 'juan@example.com',
  otp: '',
  client: {
    first_name: 'Juan',
    last_name: 'Dela Cruz',
    middle_name: '',
    suffix: '',
    date_of_birth: '1990-01-01',
    sex: 'Male',
    contact_number: '+639171234567',
  },
  address: {
    region: '0700000000',
    province: '0722000000',
    city_municipality: '0722170000',
    barangay: '0722170010',
    street: '',
  },
  employment: {
    employer_name: '',
    position: '',
    country: '',
    start_date: '',
    end_date: '',
    is_present: false,
    last_country: '',
    last_position: '',
    date_of_arrival: '',
  },
  vulnerability: [],
  next_of_kin: [{
    first_name: '', last_name: '', middle_name: '', relationship: '', phone_number: '', email: '',
    is_primary: true, region: '', province: '', city_municipality: '', barangay: '', street: '',
  }],
  summary: '',
  consent: false,
};

function renderAtStartWithCompleteDraft(props = {}) {
  sessionStorage.setItem('ofw_intake_form_data', JSON.stringify(completeDraft));
  globalThis.fetch = vi.fn(async () => ({ ok: true, status: 200, json: async () => [] }));

  return render(
    <IntakeIndex occupationOptions={[]} existingClient={null} skipVerification={false} {...props} />,
  );
}

function advanceToEmailVerification() {
  fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
  expect(screen.getByRole('heading', { name: 'Home Address' })).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
  expect(screen.getByRole('heading', { name: 'Employment Details' })).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
  expect(screen.getByRole('heading', { name: 'Emergency Contact (Next of Kin)' })).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
  expect(screen.getByRole('heading', { name: 'Verify Your Email' })).toBeInTheDocument();
}

describe('File Your Case wizard', () => {
  beforeEach(() => {
    sessionStorage.clear();
    vi.clearAllMocks();
    window.scrollTo = vi.fn();
  });

  it('starts an anonymous filing with personal information instead of email verification', () => {
    render(<IntakeIndex occupationOptions={[]} existingClient={null} skipVerification={false} />);

    expect(screen.getByRole('heading', { name: 'Personal Information' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Verify Your Email' })).not.toBeInTheDocument();
  });

  it('preserves an anonymous filer draft before email verification', async () => {
    const { container } = render(<IntakeIndex occupationOptions={[]} existingClient={null} skipVerification={false} />);
    const firstName = container.querySelector('input[type="text"]');

    fireEvent.change(firstName, { target: { value: 'Juan' } });

    await waitFor(() => {
      const saved = JSON.parse(sessionStorage.getItem('ofw_intake_form_data'));
      expect(saved.client.first_name).toBe('Juan');
    });
  });

  it('lets a filer return from the late verification step to next of kin', () => {
    renderAtStartWithCompleteDraft();
    advanceToEmailVerification();

    fireEvent.click(screen.getByRole('button', { name: 'Back' }));

    expect(screen.getByRole('heading', { name: 'Emergency Contact (Next of Kin)' })).toBeInTheDocument();
  });

  it('lets a filer continue without adding a next of kin', () => {
    sessionStorage.setItem('ofw_intake_form_data', JSON.stringify({ ...completeDraft, next_of_kin: [] }));
    globalThis.fetch = vi.fn(async () => ({ ok: true, status: 200, json: async () => [] }));
    render(<IntakeIndex occupationOptions={[]} existingClient={null} skipVerification={false} />);

    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

    expect(screen.getByText('No emergency contact added. You can continue without one.')).toBeInTheDocument();
    expect(screen.queryByText('Contact #1')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    expect(screen.getByRole('heading', { name: 'Verify Your Email' })).toBeInTheDocument();
  });

  it('lets a filer remove the only next-of-kin contact', () => {
    renderAtStartWithCompleteDraft();

    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));

    expect(screen.queryByText('Contact #1')).not.toBeInTheDocument();
    expect(screen.getByText('No emergency contact added. You can continue without one.')).toBeInTheDocument();
  });

  it('validates every partially completed next-of-kin contact', () => {
    sessionStorage.setItem('ofw_intake_form_data', JSON.stringify({
      ...completeDraft,
      next_of_kin: [
        { ...completeDraft.next_of_kin[0], first_name: 'Maria' },
        { ...completeDraft.next_of_kin[0], is_primary: false, phone_number: '+639181234567' },
      ],
    }));
    globalThis.fetch = vi.fn(async () => ({ ok: true, status: 200, json: async () => [] }));
    render(<IntakeIndex occupationOptions={[]} existingClient={null} skipVerification={false} />);

    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

    expect(screen.getByText('Emergency contact name is required when a contact is provided.')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Emergency Contact (Next of Kin)' })).toBeInTheDocument();
  });

  it('prefills returning-client next of kin without relying on a default row', () => {
    const existingClient = {
      ...completeDraft.client,
      email: completeDraft.email,
      address: completeDraft.address,
      employment: completeDraft.employment,
      next_of_kin: [{ first_name: 'Maria', last_name: null, relationship: 'Spouse' }],
    };
    globalThis.fetch = vi.fn(async () => ({ ok: true, status: 200, json: async () => [] }));
    render(<IntakeIndex occupationOptions={[]} existingClient={existingClient} skipVerification />);

    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

    expect(screen.getByDisplayValue('Maria')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Spouse')).toBeInTheDocument();
  });

  it('opens review after verification without replacing a returning filer\'s entered details', async () => {
    const { container } = renderAtStartWithCompleteDraft();
    advanceToEmailVerification();
    globalThis.fetch = vi.fn(async (url) => {
      if (String(url).includes('intake.verify-email')) {
        return { ok: true, status: 200, json: async () => ({ sent: true, hint: 'ju**@example.com' }) };
      }
      if (String(url).includes('intake.check-duplicate')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            verified: true,
            duplicate: false,
            existing_client: {
              first_name: 'Server',
              last_name: 'Profile',
              date_of_birth: '1985-05-05',
              sex: 'Female',
            },
          }),
        };
      }
      return { ok: true, status: 200, json: async () => [] };
    });

    fireEvent.click(screen.getByRole('button', { name: 'Send Verification Code' }));
    fireEvent.change(await screen.findByPlaceholderText('000000'), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: 'Verify & Continue' }));
    expect(await screen.findByRole('heading', { name: 'Review & Submit' })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    fireEvent.click(screen.getByRole('button', { name: 'Back' }));

    expect(container.querySelector('input[type="text"]')).toHaveValue('Juan');
  });

  it('shows signed-in OFWs a progress path with no email-verification step', () => {
    renderAtStartWithCompleteDraft({ skipVerification: true });

    const progress = screen.getByRole('list', { name: 'File Your Case progress' });
    expect(within(progress).queryByText('Email Verification')).not.toBeInTheDocument();
    expect(within(progress).getByText('Personal Information').closest('[role="listitem"]')).toHaveAttribute('aria-current', 'step');
  });

  it('blocks review for an active duplicate and lets the filer return to the form', async () => {
    renderAtStartWithCompleteDraft();
    advanceToEmailVerification();
    globalThis.fetch = vi.fn(async (url) => {
      if (String(url).includes('intake.verify-email')) {
        return { ok: true, status: 200, json: async () => ({ sent: true, hint: 'ju**@example.com' }) };
      }
      if (String(url).includes('intake.check-duplicate')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            verified: true,
            duplicate: true,
            message: 'You already have an active case.',
          }),
        };
      }
      return { ok: true, status: 200, json: async () => [] };
    });

    fireEvent.click(screen.getByRole('button', { name: 'Send Verification Code' }));
    fireEvent.change(await screen.findByPlaceholderText('000000'), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: 'Verify & Continue' }));
    expect(await screen.findByRole('heading', { name: 'Active Case Found' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Review & Submit' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    expect(screen.getByRole('heading', { name: 'Emergency Contact (Next of Kin)' })).toBeInTheDocument();
  });
});
