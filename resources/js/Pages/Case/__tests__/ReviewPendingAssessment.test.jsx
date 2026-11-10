import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
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
    },
  ],
  current_page: 1,
  last_page: 1,
  from: 1,
  to: 1,
  total: 1,
};

function openReview() {
  render(<ReviewPending cases={pendingCases} filters={{}} assessmentOptions={['LEGAL_ASSISTANCE', 'OTHER']} />);
  fireEvent.click(screen.getByRole('button', { name: /review/i }));
}

describe('Review pending intake', () => {
  it('shows case-owned details without category or issue controls', () => {
    openReview();

    expect(screen.getByText('Juan Dela Cruz')).toBeInTheDocument();
    expect(screen.getAllByText(/Needs help with unpaid wages/).length).toBeGreaterThan(0);
    expect(screen.queryByText(/case issue/i)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/issue \/ concern/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/categories \*/i)).not.toBeInTheDocument();
  });

  it('keeps approval disabled until a confirmed assessment and reviewed document are set', () => {
    openReview();

    const approve = screen.getByRole('button', { name: /approve case/i });
    expect(approve).toBeDisabled();

    fireEvent.click(screen.getByRole('checkbox', { name: /legal assistance/i }));
    // Assessment alone is not enough; the uploaded document must be reviewed.
    expect(approve).toBeDisabled();

    fireEvent.click(screen.getAllByRole('checkbox', { name: /passport front page/i })[0]);
    expect(approve).not.toBeDisabled();
  });
});
