import React from 'react';
import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mockPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children, href }) => <a href={href}>{children}</a>,
  useForm: (initial) => {
    const [data, update] = React.useState(initial);
    return { data, setData: (key, value) => update((current) => ({ ...current, [key]: value })), post: mockPost, processing: false, errors: {}, clearErrors: vi.fn(), transform: vi.fn() };
  },
  router: { get: vi.fn(), post: vi.fn() },
  usePage: () => ({ props: { auth: { user: { id: 'u-1', role: 'CASE_MANAGER' } }, just_published: false } }),
}));

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: () => null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useClientValidation', () => ({ default: () => ({ validate: () => true }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/lib/utils', () => ({ formatDisplayDate: (v) => v, formatDisplayTime: (v) => v }));

globalThis.route = () => '/mocked';

import ReferralCreate from '../Create';

afterEach(cleanup);

const agencies = [{ id: 'agcy-1', name: 'DOLE' }];
const categories = [{ id: 'cat-1', name: 'Legal', suggested_agency_ids: ['agcy-1'] }];
const cases = { data: [{ id: 'case-1', case_number: 'C001', first_name: 'Juan', last_name: 'Cruz', created_at: '2026-11-01', status: 'open', categories: [{ id: 'cat-1', name: 'Legal' }] }], current_page: 1, last_page: 1, from: 1, to: 1, total: 1 };

describe('Referral Create concern presets', () => {
  it('shows preset buttons on step 3', () => {
    render(<ReferralCreate case_id="case-1" agencies={agencies} cases={cases} categories={categories} filters={{}} />);
    
    fireEvent.click(screen.getByRole('button', { name: /DOLE/i }));
    fireEvent.click(screen.getByRole('button', { name: /next/i }));
    
    expect(screen.getByText('Workplace discrimination')).toBeInTheDocument();
    expect(screen.getByText('Repatriation')).toBeInTheDocument();
    expect(screen.getByText('Local job placement')).toBeInTheDocument();
    expect(screen.getByText('Medical assistance')).toBeInTheDocument();
    expect(screen.getByText('Skills training')).toBeInTheDocument();
    expect(screen.getByText('Legal assistance')).toBeInTheDocument();
  });

  it('fills concern textarea when preset clicked', () => {
    render(<ReferralCreate case_id="case-1" agencies={agencies} cases={cases} categories={categories} filters={{}} />);
    fireEvent.click(screen.getByRole('button', { name: /DOLE/i }));
    fireEvent.click(screen.getByRole('button', { name: /next/i }));
    
    const concernTextarea = screen.getByLabelText(/concern \*/i);
    expect(concernTextarea.value).toBe('');
    
    fireEvent.click(screen.getByRole('button', { name: 'Workplace discrimination' }));
    expect(concernTextarea.value).toBe('Workplace discrimination');
  });

  it('allows editing preset after selection', () => {
    render(<ReferralCreate case_id="case-1" agencies={agencies} cases={cases} categories={categories} filters={{}} />);
    fireEvent.click(screen.getByRole('button', { name: /DOLE/i }));
    fireEvent.click(screen.getByRole('button', { name: /next/i }));
    
    const concernTextarea = screen.getByLabelText(/concern \*/i);
    fireEvent.click(screen.getByRole('button', { name: 'Legal assistance' }));
    expect(concernTextarea.value).toBe('Legal assistance');
    
    fireEvent.change(concernTextarea, { target: { value: 'Legal assistance - contract dispute' } });
    expect(concernTextarea.value).toBe('Legal assistance - contract dispute');
  });
});

  it('confirms all recipients and shared documents before sending', () => {
    const allAgencies = [...agencies, { id: 'agcy-2', name: 'OWWA' }];
    const allCategories = [{ ...categories[0], suggested_agency_ids: ['agcy-1', 'agcy-2'] }];
    render(<ReferralCreate case_id="case-1" agencies={allAgencies} cases={cases} categories={allCategories} />);
    expect(screen.queryByText('Referral category')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /DOLE/i }));
    fireEvent.click(screen.getByRole('button', { name: /OWWA/i }));
    fireEvent.click(screen.getByRole('button', { name: /next/i }));
    fireEvent.change(screen.getByLabelText(/concern \*/i), { target: { value: 'Help needed' } });
    const file = new File(['proof'], 'proof.pdf', { type: 'application/pdf' });
    fireEvent.change(document.querySelector('input[type="file"]'), { target: { files: [file] } });
    fireEvent.click(screen.getByRole('button', { name: /review referrals/i }));
    const dialog = screen.getByRole('dialog');
    expect(dialog).toHaveTextContent('DOLE');
    expect(dialog).toHaveTextContent('OWWA');
    expect(dialog).toHaveTextContent('proof.pdf');
    expect(mockPost).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: /send referrals/i }));
    expect(mockPost).toHaveBeenCalled();
  });
