import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { pageGuides } from '@/Onboarding/registry';
import FormIndex from '../FormIndex';
import ResponseIndex from '../ResponseIndex';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: { get: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@/lib/utils', () => ({ formatDisplayDate: (value) => value }));

globalThis.route = vi.fn((name, id) => `/${name}/${id ?? ''}`);

describe('survey onboarding anchors', () => {
  const selectorsFor = (routeName) => pageGuides[routeName].steps.map((step) => step.element);

  it('renders every Survey Forms registry anchor', () => {
    render(<FormIndex forms={[{ id: 'form-1', title: 'Feedback', questions_count: 1, is_active: true, created_at: 'today' }]} />);
    for (const selector of selectorsFor('survey.forms.index')) {
      expect(document.querySelector(selector)).toBeInTheDocument();
    }
  });

  it('renders every Survey Responses registry anchor', () => {
    render(<ResponseIndex invitations={{ data: [], current_page: 1, last_page: 1, total: 0 }} stats={{ total_sent: 0, total_submitted: 0, response_rate: 0 }} />);
    for (const selector of selectorsFor('survey.responses.index')) {
      expect(document.querySelector(selector)).toBeInTheDocument();
    }
  });
});
