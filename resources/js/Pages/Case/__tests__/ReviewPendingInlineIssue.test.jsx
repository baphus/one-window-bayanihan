import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }) => <a href={href}>{children}</a>,
  router: { get: vi.fn(), visit: vi.fn(), post: vi.fn() },
  usePage: () => ({ props: { auth: { user: { id: 'u-1', role: 'CASE_MANAGER' } } }, url: '/cases/review-pending' }),
  useForm: () => ({ processing: false, post: vi.fn(), data: {}, setData: vi.fn(), errors: {} }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/ui/StatusBadge', () => ({ default: ({ status }) => <span>{status}</span> }));
vi.mock('@/Components/ui/ConfirmDialog', () => ({ default: () => null }));
vi.mock('@/lib/utils', () => ({
  formatDisplayDate: (d) => d,
  formatDisplayTime: (d) => d,
}));

globalThis.route = (name) => `/mocked/${name}`;

import ReviewPending from '../ReviewPending';

afterEach(cleanup);

const pendingCases = {
  data: [
    {
      id: 'case-1',
      case_number: 'CASE-20260724-0001',
      client: { first_name: 'Juan', last_name: 'Dela Cruz' },
      summary: 'Needs help with unpaid wages.',
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
  render(<ReviewPending cases={pendingCases} filters={{}} categories={[{ id: 'cat-1', name: 'Legal Assistance' }]} caseIssues={[{ id: 'issue-1', name: 'Unpaid wages' }]} />);
  fireEvent.click(screen.getByRole('button', { name: /review/i }));
}

describe('Review case request — inline issue add', () => {
  beforeEach(() => {
    globalThis.window.axios = {
      post: vi.fn().mockResolvedValue({ data: { id: 'issue-new', name: 'Lost passport' } }),
      get: vi.fn(),
    };
  });

  it('creates a new issue from the review modal and selects it', async () => {
    openReview();

    fireEvent.click(screen.getByRole('button', { name: /add new issue/i }));

    const input = screen.getByLabelText(/new issue name/i);
    fireEvent.change(input, { target: { value: 'Lost passport' } });
    fireEvent.click(screen.getByRole('button', { name: /^add$/i }));

    await waitFor(() => {
      expect(window.axios.post).toHaveBeenCalledWith('/mocked/case-issues.quick', { name: 'Lost passport' });
    });

    const select = screen.getByLabelText(/issue \/ concern/i);
    await waitFor(() => expect(select).toHaveValue('issue-new'));
    expect(screen.getByRole('option', { name: 'Lost passport' })).toBeInTheDocument();
    expect(screen.queryByLabelText(/new issue name/i)).not.toBeInTheDocument();
  });

  it('shows the duplicate-name error and leaves the modal form open', async () => {
    window.axios.post.mockRejectedValueOnce({
      response: { data: { errors: { name: ['The name has already been taken.'] } } },
    });

    openReview();

    fireEvent.click(screen.getByRole('button', { name: /add new issue/i }));
    fireEvent.change(screen.getByLabelText(/new issue name/i), { target: { value: 'Unpaid wages' } });
    fireEvent.click(screen.getByRole('button', { name: /^add$/i }));

    expect(await screen.findByText('The name has already been taken.')).toBeInTheDocument();
    expect(screen.getByLabelText(/new issue name/i)).toBeInTheDocument();
  });
});
