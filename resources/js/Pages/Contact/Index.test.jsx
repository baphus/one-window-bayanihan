import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Contact from './Index';

const { authUser } = vi.hoisted(() => ({ authUser: { user: null } }));
vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
  router: { get: vi.fn() },
  usePage: () => ({ props: { auth: authUser, turnstile: { enabled: false } } }),
  useForm: (initial) => ({ data: initial, setData: vi.fn(), post: vi.fn(), processing: false, errors: {}, reset: vi.fn(), setError: vi.fn(), clearErrors: vi.fn() }),
}));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Hooks/useSessionDraft', () => ({ default: ({ userId, fields }) => ({ status: '', clear: vi.fn(), onSuccess: vi.fn(), userId, fields }) }));
vi.mock('@/Components/DraftControls', () => ({ default: () => null }));
vi.mock('@/Hooks/useInView', () => ({ default: () => [null, true] }));
globalThis.route = (name) => `/${name}`;

describe('Contact form identity defaults', () => {
  it('prefills signed-in identity and leaves fields editable', () => {
    authUser.user = { name: 'A User', email: 'a@example.com' };
    render(<Contact />);
    expect(screen.getByLabelText('Your Name')).toHaveValue('A User');
    expect(screen.getByLabelText('Email Address')).toHaveValue('a@example.com');
    expect(screen.getByLabelText('Your Name')).not.toHaveAttribute('readonly');
    expect(screen.getByLabelText('Email Address')).not.toHaveAttribute('readonly');
  });

  it('leaves guest identity fields blank', () => {
    authUser.user = null;
    render(<Contact />);
    expect(screen.getByLabelText('Your Name')).toHaveValue('');
    expect(screen.getByLabelText('Email Address')).toHaveValue('');
  });
});
