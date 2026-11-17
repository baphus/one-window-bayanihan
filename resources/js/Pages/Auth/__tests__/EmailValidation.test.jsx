import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useState } from 'react';
import Login from '../Login';
import StaffLogin from '../StaffLogin';
import ForgotPassword from '../ForgotPassword';
import Portal from '../../Tracking/Portal';
import Contact from '../../Contact/Index';

const post = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({
  Head: () => null, Link: ({ children, href }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: {} }), router: { post, get: vi.fn(), on: () => () => {} },
  useForm: (initial) => {
    const [data, update] = useState(initial);
    const [errors, updateErrors] = useState({});
    return { data, errors, processing: false, post, reset: () => {},
      setData: (key, value) => update((old) => ({ ...old, [key]: value })),
      setError: (key, value) => updateErrors((old) => ({ ...old, [key]: value })),
      clearErrors: (key) => updateErrors((old) => key ? { ...old, [key]: undefined } : {}) };
  },
}));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => null }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => null }));
vi.mock('@/Components/ChatBot', () => ({ default: () => null }));
vi.mock('@/Components/TurnstileWidget', () => ({ default: () => null }));
vi.mock('@/Hooks/useInView', () => ({ default: () => [null, true] }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: () => null, bypassNext: () => {} }) }));

beforeEach(() => { post.mockClear(); global.route = (name) => `/${name}`; });
afterEach(cleanup);

describe.each([['Login', Login], ['StaffLogin', StaffLogin], ['ForgotPassword', ForgotPassword], ['Portal', Portal], ['Contact', Contact]])('%s email', (_, Page) => {
  it('blocks empty and malformed email, clears errors on edit, and submits valid data', () => {
    render(<Page />);
    const email = document.querySelector('input[type="email"]');
    const form = email.closest('form');
    for (const input of form.querySelectorAll('input[required], textarea[required]')) {
      if (input !== email) fireEvent.change(input, { target: { value: input.type === 'password' ? 'secret' : 'Example' } });
    }
    const password = form.querySelector('input[type="password"]');
    if (password) fireEvent.change(password, { target: { value: 'secret' } });
    fireEvent.click(form.querySelector('button[type="submit"]'));
    expect(screen.getByText('Email is required.')).toBeInTheDocument();
    expect(document.activeElement).toBe(email);
    expect(post).not.toHaveBeenCalled();
    fireEvent.change(email, { target: { value: 'bad' } });
    expect(screen.queryByText('Email is required.')).not.toBeInTheDocument();
    fireEvent.click(form.querySelector('button[type="submit"]'));
    expect(screen.getByText('Please provide a valid email address.')).toBeInTheDocument();
    expect(document.activeElement).toBe(email);
    expect(post).not.toHaveBeenCalled();
    fireEvent.change(email, { target: { value: 'user@localhost' } });
    fireEvent.click(form.querySelector('button[type="submit"]'));
    expect(post).not.toHaveBeenCalled();
    expect(document.activeElement).toBe(email);
    fireEvent.change(email, { target: { value: 'user@example.com' } });
    expect(screen.queryByText('Please provide a valid email address.')).not.toBeInTheDocument();
    fireEvent.click(form.querySelector('button[type="submit"]'));
    expect(post).toHaveBeenCalledOnce();
  });
});

it('keeps contact name and message native required checks', () => {
  render(<Contact />);
  const email = screen.getByLabelText('Email Address');
  fireEvent.change(email, { target: { value: 'user@example.com' } });
  fireEvent.click(email.closest('form').querySelector('button[type="submit"]'));
  expect(post).not.toHaveBeenCalled();
  expect(screen.getByLabelText('Your Name').validity.valueMissing).toBe(true);
  expect(screen.getByLabelText('Message').validity.valueMissing).toBe(true);
});
