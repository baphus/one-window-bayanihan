import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }) => <a href={href}>{children}</a>,
  router: { get: vi.fn(), post: vi.fn() },
  usePage: () => ({ props: { auth: { user: { id: 'u-1', role: 'CASE_MANAGER' } } }, url: '/cases/review-pending' }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/lib/utils', () => ({ formatDisplayDate: (value) => value, formatDisplayTime: (value) => value }));

globalThis.route = (name) => `/mocked/${name}`;

import ReviewPending from '../ReviewPending';

afterEach(cleanup);

const agencies = [
  { id: 'agcy-1', name: 'DOLE Region VII', short: 'DOLE' },
  { id: 'agcy-2', name: 'OWWA', short: 'OWWA' },
  { id: 'agcy-3', name: 'TESDA', short: 'TESDA' },
];

const categories = [
  { id: 'cat-1', name: 'Legal', suggested_agency_ids: ['agcy-1'] },
  { id: 'cat-2', name: 'Skills Development', suggested_agency_ids: ['agcy-3'] },
  { id: 'cat-3', name: 'Other', suggested_agency_ids: [] },
];

const pendingCases = {
  data: [
    {
      id: 'case-1',
      case_number: 'CASE-001',
      first_name: 'Juan',
      last_name: 'Cruz',
      summary: 'Test case',
      return_reason: 'DISTRESSED_WORKER',
      documents: [{ id: 'doc-1', category: 'passport_front_page', file_name: 'passport.pdf' }],
      created_at: '2026-11-01',
    },
  ],
  current_page: 1,
  last_page: 1,
  from: 1,
  to: 1,
  total: 1,
};

function openReview() {
  render(<ReviewPending cases={pendingCases} filters={{}} assessmentOptions={['LEGAL_ASSISTANCE']} agencies={agencies} categories={categories} />);
  fireEvent.click(screen.getByRole('button', { name: /review/i }));
}

describe('Review pending category preview', () => {
  it('shows category preview card in modal', () => {
    openReview();
    expect(screen.getByText('Referral category preview')).toBeInTheDocument();
    expect(screen.getByText(/This is for reference only and does not create or save a referral/i)).toBeInTheDocument();
  });

  it('lists all categories in preview dropdown', () => {
    openReview();
    const select = screen.getByLabelText(/category/i);
    expect(within(select).getByText('Legal')).toBeInTheDocument();
    expect(within(select).getByText('Skills Development')).toBeInTheDocument();
    expect(within(select).getByText('Other')).toBeInTheDocument();
  });

  it('shows suggested agencies when category selected', () => {
    openReview();
    const select = screen.getByLabelText(/category/i);
    
    fireEvent.change(select, { target: { value: 'cat-1' } });
    
    expect(screen.getByText('Suggested agencies')).toBeInTheDocument();
    expect(screen.getByText('DOLE Region VII')).toBeInTheDocument();
    expect(screen.getByText('(DOLE)')).toBeInTheDocument();
  });

  it('shows different suggestions for different categories', () => {
    openReview();
    const select = screen.getByLabelText(/category/i);
    
    fireEvent.change(select, { target: { value: 'cat-2' } });
    
    expect(screen.getByText('TESDA')).toBeInTheDocument();
    expect(screen.queryByText('DOLE Region VII')).not.toBeInTheDocument();
  });

  it('shows no agencies message when category has no suggestions', () => {
    openReview();
    const select = screen.getByLabelText(/category/i);
    
    fireEvent.change(select, { target: { value: 'cat-3' } });
    
    expect(screen.getByText('No suggested agencies for this category.')).toBeInTheDocument();
  });

  it('does not block approval when category preview used', () => {
    openReview();
    const select = screen.getByLabelText(/category/i);
    fireEvent.change(select, { target: { value: 'cat-1' } });
    
    fireEvent.click(screen.getByRole('checkbox', { name: /legal assistance/i }));
    fireEvent.click(screen.getAllByRole('checkbox')[1]);
    
    const approve = screen.getByRole('button', { name: /approve case/i });
    expect(approve).not.toBeDisabled();
  });
});
