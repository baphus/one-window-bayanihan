import { beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import UserFormModal from '../UserFormModal';
import AgencyFormModal from '../AgencyFormModal';
import ChangeEmailForm from '@/Pages/Profile/Partials/ChangeEmailForm';

const mocks = vi.hoisted(() => ({ post: vi.fn(), patch: vi.fn() }));
globalThis.route = (name) => name;
vi.mock('@inertiajs/react', () => ({
  router: { post: mocks.post, reload: vi.fn() },
  usePage: () => ({ props: { roles: { CASE_MANAGER: 'case', AGENCY: 'agency', ADMIN: 'admin' } } }),
  useForm: (initial) => {
    const [data, setDataState] = useState(initial);
    const [errors, setErrors] = useState({});
    return {
      data, errors, processing: false, post: mocks.post, patch: mocks.patch, transform: vi.fn(),
      setData: (key, value) => setDataState((previous) => ({ ...previous, [key]: value })),
      setError: (key, value) => setErrors((previous) => ({ ...previous, [key]: value })),
      clearErrors: (...keys) => setErrors((previous) => keys.length ? Object.fromEntries(Object.entries(previous).filter(([key]) => !keys.includes(key))) : {}),
    };
  },
}));
vi.mock('@/Hooks/useMfaStatus', () => ({ default: () => ({ status: null }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ error: vi.fn() }) }));
vi.mock('@/Components/MfaConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Components/AgencyMapView', () => ({ default: () => null }));
vi.mock('../LogoUpload', () => ({ default: () => null }));

describe('email validation', () => {
  beforeEach(() => vi.clearAllMocks());

  it('blocks invalid user email, focuses it, and clears the warning on edit', () => {
    render(<UserFormModal agencies={[]} onClose={vi.fn()} />);
    const email = screen.getByLabelText(/Email Address/);
    fireEvent.change(email, { target: { value: 'bad@' } });
    fireEvent.click(screen.getByRole('button', { name: 'Send Invite' }));
    expect(screen.getByText('Please provide a valid email address.')).toBeInTheDocument();
    expect(email).toHaveFocus();
    expect(mocks.post).not.toHaveBeenCalled();
    fireEvent.change(email, { target: { value: 'valid@example.com' } });
    expect(screen.queryByText('Please provide a valid email address.')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Send Invite' }));
    expect(mocks.post).toHaveBeenCalledOnce();
  });

  it('accepts blank optional agency email and blocks malformed email', () => {
    render(<AgencyFormModal onClose={vi.fn()} />);
    const email = screen.getByLabelText('Email');
    const form = email.closest('form');
    fireEvent.change(email, { target: { value: 'bad@' } });
    fireEvent.invalid(email);
    expect(screen.getByText('Must be a valid email address.')).toBeInTheDocument();
    expect(email).toHaveFocus();
    expect(mocks.post).not.toHaveBeenCalled();
    fireEvent.change(email, { target: { value: 'user@host' } });
    expect(screen.queryByText('Must be a valid email address.')).not.toBeInTheDocument();
    fireEvent.submit(form);
    expect(screen.getByText('Must be a valid email address.')).toBeInTheDocument();
    expect(email).toHaveFocus();
    expect(mocks.post).not.toHaveBeenCalled();
    fireEvent.change(email, { target: { value: '' } });
    const fields = form.querySelectorAll('input[type="text"]');
    fireEvent.change(fields[0], { target: { value: 'Agency' } });
    fireEvent.change(fields[1], { target: { value: 'AG' } });
    fireEvent.submit(form);
    expect(mocks.post).toHaveBeenCalledOnce();
  });

  it('validates profile email before sending and clears server errors on edit', () => {
    render(<ChangeEmailForm initialStep="new-email" />);
    const email = screen.getByLabelText('New Email Address');
    const send = screen.getByRole('button', { name: 'Send Verification Code' });
    fireEvent.click(send);
    expect(screen.getByText('Email is required.')).toBeInTheDocument();
    expect(email).toHaveFocus();
    fireEvent.change(email, { target: { value: 'bad@' } });
    fireEvent.click(send);
    expect(screen.getByText('Please provide a valid email address.')).toBeInTheDocument();
    expect(mocks.post).not.toHaveBeenCalled();
    fireEvent.change(email, { target: { value: 'valid@example.com' } });
    fireEvent.click(send);
    expect(mocks.post).toHaveBeenCalledOnce();
    act(() => mocks.post.mock.calls[0][2].onError({ new_email: 'Already in use.' }));
    expect(screen.getByText('Already in use.')).toBeInTheDocument();
    fireEvent.change(email, { target: { value: 'other@example.com' } });
    expect(screen.queryByText('Already in use.')).not.toBeInTheDocument();
  });
});
