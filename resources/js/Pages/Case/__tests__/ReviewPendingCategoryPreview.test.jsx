import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';

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
      documents: [
        { id: 'doc-1', category: 'passport_front_page', file_name: 'passport.pdf' },
        { id: 'doc-2', category: 'arrival_stamp', file_name: 'arrival.pdf' },
        { id: 'doc-3', category: 'distress_certificate', file_name: 'reason.pdf' },
        { id: 'doc-4', category: 'employment_contract', file_name: 'contract.pdf' },
      ],
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
  it('groups intake and document review on left, controls on right, and downloads without previews', () => {
    openReview();
    const dialog = screen.getByRole('dialog');
    const columns = dialog.querySelector('.grid.lg\\:grid-cols-\\[minmax\\(0\\,1fr\\)_24rem\\]');
    expect(columns).toBeInTheDocument();
    expect(columns.children[0]).toHaveTextContent('Latest submitted intake answers');
    expect(columns.children[1]).toHaveTextContent('Confirm assessment');
    expect(dialog.querySelector('img, iframe')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Download Passport front page' })).toHaveAttribute('href', '/mocked/cases.documents.download');
    expect(screen.getByRole('link', { name: 'Download all' })).toHaveTextContent('folder_zip');
  });
  it('shows category preview card in modal', () => {
    openReview();
    expect(screen.getByText('Referral category preview')).toBeInTheDocument();
    expect(screen.getByText(/This is for reference only and does not create or save a referral/i)).toBeInTheDocument();
  });

  it('lists all categories in preview dropdown', () => {
    openReview();
    const select = screen.getByRole('combobox', { name: /category/i });
    fireEvent.click(select);
    expect(screen.getByRole('option', { name: 'Legal' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Skills Development' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Other' })).toBeInTheDocument();
  });

  it('shows suggested agencies when category selected', () => {
    openReview();
    const select = screen.getByRole('combobox', { name: /category/i });
    fireEvent.click(select);
    fireEvent.mouseDown(screen.getByRole('option', { name: 'Legal' }));
    
    expect(screen.getByText('Suggested agencies')).toBeInTheDocument();
    expect(screen.getByText('DOLE Region VII')).toBeInTheDocument();
    expect(screen.getByText('(DOLE)')).toBeInTheDocument();
  });

  it('shows different suggestions for different categories', () => {
    openReview();
    const select = screen.getByRole('combobox', { name: /category/i });
    fireEvent.click(select);
    fireEvent.mouseDown(screen.getByRole('option', { name: 'Skills Development' }));
    
    expect(screen.getByText('TESDA')).toBeInTheDocument();
    expect(screen.queryByText('DOLE Region VII')).not.toBeInTheDocument();
  });

  it('shows no agencies message when category has no suggestions', () => {
    openReview();
    const select = screen.getByRole('combobox', { name: /category/i });
    fireEvent.click(select);
    fireEvent.mouseDown(screen.getByRole('option', { name: 'Other' }));
    
    expect(screen.getByText('No suggested agencies for this category.')).toBeInTheDocument();
  });

  it('requires categories, then submits all selected category IDs with approval', () => {
    openReview();
    screen.getAllByRole('checkbox', { name: /reviewed document/i }).forEach((checkbox) => { if (!checkbox.checked) fireEvent.click(checkbox); });
    fireEvent.click(screen.getByRole('checkbox', { name: /legal assistance/i }));

    const approve = screen.getByRole('button', { name: /approve case/i });
    expect(approve).toBeDisabled();
    expect(screen.getByRole('alert')).toHaveTextContent('Select at least one case category');

    fireEvent.click(screen.getByRole('checkbox', { name: 'Legal' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Skills Development' }));
    expect(approve).not.toBeDisabled();
    fireEvent.click(approve);

    expect(router.post).toHaveBeenCalledWith('/mocked/cases.review-approve', expect.objectContaining({ category_ids: ['cat-1', 'cat-2'] }), expect.any(Object));
  });

  it('does not block approval when category preview used', () => {
    openReview();
    const select = screen.getByLabelText(/category/i);
    fireEvent.change(select, { target: { value: 'cat-1' } });
    
    fireEvent.click(screen.getByRole('checkbox', { name: /legal assistance/i }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Legal' }));
    screen.getAllByRole('checkbox', { name: /reviewed document/i }).forEach((checkbox) => { if (!checkbox.checked) fireEvent.click(checkbox); });

    const approve = screen.getByRole('button', { name: /approve case/i });
    expect(approve).not.toBeDisabled();
  });
});
