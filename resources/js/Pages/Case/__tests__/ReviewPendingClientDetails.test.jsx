import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }) => <a href={href}>{children}</a>,
  router: { get: vi.fn(), visit: vi.fn(), post: vi.fn() },
  usePage: () => ({ props: { auth: { user: { id: 'u-1', role: 'CASE_MANAGER' } } }, url: '/cases/review-pending' }),
  useForm: () => ({ processing: false, post: vi.fn(), data: {}, setData: vi.fn(), errors: {} }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Hooks/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));
vi.mock('@/lib/utils', () => ({
  formatDisplayDate: (d) => (d ? `date:${d}` : ''),
  formatDisplayTime: (d) => (d ? `time:${d}` : ''),
}));

globalThis.route = (name) => `/mocked/${name}`;

import ReviewPending from '../ReviewPending';

afterEach(cleanup);

const pendingCase = {
  id: 'case-1',
  case_number: 'CASE-20260724-0001',
  tracker_number: 'TRK-0001',
  status: 'PENDING_REVIEW',
  vulnerability_indicator: 'Senior Citizen',
  nok_vulnerability_indicator: 'PWD',
  summary: 'Needs help with unpaid wages.',
  created_at: '2026-07-20T00:00:00.000Z',
  client: {
    first_name: 'Juan',
    middle_name: 'Reyes',
    last_name: 'Dela Cruz',
    suffix: 'Jr.',
    type: 'OFW',
    date_of_birth: '1985-03-04',
    sex: 'MALE',
    email: 'juan@example.test',
    contact_number: '09171234567',
    addresses: [
      {
        id: 'addr-1',
        region: 'Region VII (Central Visayas)',
        province: 'Cebu',
        city_municipality: 'Cebu City',
        barangay: 'Lahug',
        street: '12 Mabini St.',
      },
    ],
    employments: [
      {
        id: 'emp-1',
        employer_name: 'Gulf Contractors Inc.',
        position: 'Welder',
        country: 'Saudi Arabia',
        last_country: 'Saudi Arabia',
        last_position: 'Welder',
        date_of_arrival: '2024-02-01',
        start_date: '2020-01-01',
        end_date: '2024-01-01',
      },
    ],
    nextOfKin: [
      {
        id: 'nok-1',
        first_name: 'Maria',
        middle_name: 'Reyes',
        last_name: 'Dela Cruz',
        relationship: 'Spouse',
        phone_number: '09189998888',
        email: 'maria@example.test',
        is_primary: true,
        region: 'Region VII (Central Visayas)',
        province: 'Cebu',
        city_municipality: 'Cebu City',
        barangay: 'Lahug',
        street: '12 Mabini St.',
      },
    ],
  },
};

const pendingCases = {
  data: [pendingCase],
  current_page: 1,
  last_page: 1,
  from: 1,
  to: 1,
  total: 1,
};

function openReview(caseRow = pendingCase) {
  render(
    <ReviewPending
      cases={{ ...pendingCases, data: [caseRow] }}
      filters={{}}
      categories={[{ id: 'cat-1', name: 'Legal Assistance' }]}
      caseIssues={[{ id: 'issue-1', name: 'Unpaid wages' }]}
    />,
  );
  fireEvent.click(screen.getByRole('button', { name: /review/i }));
}

describe('Review case request — full client details', () => {
  it('renders every client category rather than a summary only', () => {
    openReview();

    // Section headings — substring match because each heading also carries a
    // Material Symbols ligature (e.g. "home_pin Address"). Scoped to headings
    // so they don't collide with the "Address" meta-tile in next of kin.
    const dialog = screen.getByRole('dialog');
    ['Case summary', 'Personal information', 'Address', 'Employment', 'Next of kin', 'Case information', 'Attach a supporting document']
      .forEach((label) => {
        expect(within(dialog).getByRole('heading', { name: new RegExp(label, 'i') })).toBeInTheDocument();
      });

    // Summary is shown in full, not as an excerpt
    expect(within(dialog).getByText('Needs help with unpaid wages.')).toBeInTheDocument();

    // Personal information
    expect(within(dialog).getByText('Juan Reyes Dela Cruz Jr.')).toBeInTheDocument();
    expect(within(dialog).getByText('Overseas Filipino Worker')).toBeInTheDocument();
    expect(within(dialog).getByText('juan@example.test')).toBeInTheDocument();
    expect(within(dialog).getByText('09171234567')).toBeInTheDocument();

    // Address — raw codes are resolved to names
    expect(within(dialog).getAllByText('12 Mabini St.').length).toBeGreaterThan(0);
    expect(within(dialog).getAllByText('Cebu').length).toBeGreaterThan(0);
    expect(within(dialog).getAllByText('Cebu City').length).toBeGreaterThan(0);
    expect(within(dialog).getAllByText('Lahug').length).toBeGreaterThan(0);

    // Employment
    expect(within(dialog).getByText('Gulf Contractors Inc.')).toBeInTheDocument();
    expect(within(dialog).getAllByText('Welder').length).toBeGreaterThan(0);
    expect(within(dialog).getAllByText('Saudi Arabia').length).toBeGreaterThan(0);

    // Next of kin
    expect(within(dialog).getByText('Maria Reyes Dela Cruz')).toBeInTheDocument();
    expect(within(dialog).getByText('Spouse')).toBeInTheDocument();
    expect(within(dialog).getByText('09189998888')).toBeInTheDocument();
    expect(within(dialog).getByText('Primary')).toBeInTheDocument();

    // Case information
    expect(within(dialog).getByText('CASE-20260724-0001')).toBeInTheDocument();
    expect(within(dialog).getByText('TRK-0001')).toBeInTheDocument();
    expect(within(dialog).getByText('Client: Senior Citizen')).toBeInTheDocument();
    expect(within(dialog).getByText('Next of kin: PWD')).toBeInTheDocument();
  });

  it('explains that attachments are shared with every referred agency', () => {
    openReview();

    const dialog = screen.getByRole('dialog');
    const notice = within(dialog).getByText(/shared with every agency this case is referred to/i);
    expect(notice).toBeInTheDocument();
    expect(notice.textContent).toMatch(/without asking for the same file again/i);
  });

  it('shows friendly empty states when profile records are missing', () => {
    openReview({
      ...pendingCase,
      client: { first_name: 'Ana', last_name: 'Santos', type: 'NEXT_OF_KIN' },
      vulnerability_indicator: 'None',
      nok_vulnerability_indicator: 'None',
    });

    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByText('No address recorded')).toBeInTheDocument();
    expect(within(dialog).getByText('No employment history recorded')).toBeInTheDocument();
    expect(within(dialog).getByText('No next of kin recorded')).toBeInTheDocument();
    expect(within(dialog).getByText('None reported')).toBeInTheDocument();
    expect(within(dialog).getByText('Next of Kin')).toBeInTheDocument();
  });

  it('does not link out to the full case page while the case is under review', () => {
    openReview();

    const dialog = screen.getByRole('dialog');
    const caseLinks = within(dialog)
      .queryAllByRole('link')
      .filter((a) => (a.getAttribute('href') || '').includes('/cases/case-1'));
    expect(caseLinks).toHaveLength(0);
  });
});
