import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { router } from '@inertiajs/react';
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

const pendingCases = {
  data: [
    {
      id: 'case-1',
      case_number: 'CASE-20260724-0001',
      first_name: 'Juan',
      last_name: 'Dela Cruz',
      summary: 'Needs help with unpaid wages.',
      return_reason: 'DISTRESSED_WORKER',
      proposed_assessments: ['LEGAL_ASSISTANCE'],
      documents: [{ id: 'doc-1', category: 'passport_front_page', file_name: 'passport.pdf' }],
      created_at: '2026-07-20T00:00:00.000Z',
      date_of_birth: '2026-10-12',
      employment_date_of_arrival: '2026-10-12',
    },
  ],
  current_page: 1,
  last_page: 1,
  from: 1,
  to: 1,
  total: 1,
};

function openReview() {
  render(<ReviewPending cases={pendingCases} filters={{}} assessmentOptions={['LEGAL_ASSISTANCE', 'OTHER']} categories={[{ id: 'cat-1', name: 'Wage claim' }]} />);
  fireEvent.click(screen.getByRole('button', { name: /review/i }));
}

describe('Review pending intake', () => {
  it('shows case-owned details without legacy issue controls', () => {
    openReview();

    expect(screen.getByText('Juan Dela Cruz')).toBeInTheDocument();
    expect(screen.getAllByText(/Needs help with unpaid wages/).length).toBeGreaterThan(0);
    expect(screen.queryByText(/case issue/i)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/issue \/ concern/i)).not.toBeInTheDocument();
    expect(screen.getByText(/case categories \*/i)).toBeInTheDocument();
  });

  it('shows ZIP download for uploaded documents', () => {
    openReview();
    expect(screen.getByRole('link', { name: /^download all$/i })).toHaveAttribute('href', '/mocked/cases.intake-documents.archive');
  });

  it('prechecks OFW assessment, omits consent, and formats dates and return reason', () => {
    openReview();
    expect(screen.getByRole('checkbox', { name: /legal assistance/i })).toBeChecked();
    expect(screen.queryByText(/consent version|consent method|consent evidence/i)).not.toBeInTheDocument();
    expect(screen.getAllByText('October 12, 2026')).toHaveLength(2);
    expect(screen.getAllByText('Arrival in Philippines')[0]).toBeInTheDocument();
    expect(screen.getAllByText(/Distressed worker/i).length).toBeGreaterThan(0);
  });

  it('sends selected deficient requirements through decline-and-refile', () => {
    openReview();
    fireEvent.click(screen.getByText('Decline and request new filing', { selector: 'summary' }));
    fireEvent.click(screen.getAllByRole('checkbox', { name: 'Passport front page' }).at(-1));
    fireEvent.change(screen.getByPlaceholderText('Required reason for declining and requesting a new filing'), { target: { value: 'Unreadable scan' } });
    fireEvent.click(screen.getByRole('button', { name: 'Decline and request new filing' }));
    expect(router.post).toHaveBeenCalledWith('/mocked/cases.review-reject', { reason: 'Unreadable scan', slots: ['passport_front_page'] }, expect.any(Object));
  });

  it('keeps approval disabled until a confirmed assessment, category, and reviewed document are set', () => {
    openReview();

    const approve = screen.getByRole('button', { name: /approve case/i });
    expect(approve).toBeDisabled();

    // OFW-selected assessment starts checked; category and document review still gate approval.
    expect(screen.getByRole('checkbox', { name: /legal assistance/i })).toBeChecked();
    fireEvent.click(screen.getAllByRole('checkbox', { name: /wage claim/i }).at(-1));
    expect(approve).toBeDisabled();
    fireEvent.click(screen.getAllByLabelText(/Reviewed document/)[0]);
    expect(approve).not.toBeDisabled();
  });
});
