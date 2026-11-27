import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UserFormModal from '../UserFormModal';
import ServiceFormModal from '../ServiceFormModal';

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    usePage: () => ({ props: { auth: { user: { id: 'user-1' } }, roles: { ADMIN: 'ADMIN', CASE_MANAGER: 'CASE_MANAGER', AGENCY: 'AGENCY', OFW: 'OFW' }, passwordRules: {} } }),
    useForm: (initial) => {
      const [data, setDataState] = React.useState(initial);
      const setData = (key, value) => typeof key === 'object' ? setDataState(key) : setDataState((old) => ({ ...old, [key]: value }));
      return { data, setData, post: vi.fn(), patch: vi.fn(), processing: false, errors: {}, clearErrors: vi.fn(), setError: vi.fn(), transform: vi.fn() };
    },
  };
});
vi.mock('@/Hooks/useClientValidation', () => ({ default: () => ({ validate: () => true }) }));
vi.mock('@/Hooks/useMfaStatus', () => ({ default: () => ({ status: null }) }));
vi.mock('@/Components/MfaConfirmDialog', () => ({ default: () => null }));
vi.mock('@/Components/IdentityFields', async () => {
  const React = await import('react');
  return { emptyIdentity: () => ({ first_name: '', middle_name: '', last_name: '', suffix: '', birth_date: '', gender: '', civil_status: '' }), default: () => React.createElement('div') };
});
vi.mock('@/Components/AvatarPicker', () => ({ default: () => null }));
vi.mock('@/Components/PasswordStrengthMeter', () => ({ default: () => null }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));
vi.mock('@/Components/Select', () => ({ default: (props) => <select {...props} /> }));
vi.mock('@/Components/DraftControls', () => ({ default: ({ onClear }) => <button type="button" onClick={onClear}>Clear saved draft</button> }));

globalThis.route = (name, id) => id === undefined ? `/${name}` : `/${name}/${id}`;

describe('session drafts on admin forms', () => {
  beforeEach(() => sessionStorage.clear());

  it('keeps user credentials, status, MFA, and photo out of stored draft; clear restores original baseline', async () => {
    const { container } = render(<UserFormModal user={{ id: 'user-9', role: 'ADMIN', email: 'old@example.test', is_active: true }} agencies={[]} />);
    fireEvent.change(screen.getByLabelText(/Email Address/), { target: { value: 'new@example.test' } });
    await waitFor(() => expect(sessionStorage.length).toBeGreaterThan(0));
    const text = [...Array(sessionStorage.length)].map((_, i) => sessionStorage.getItem(sessionStorage.key(i))).join(' ');
    expect(text).toContain('new@example.test');
    expect(text).not.toMatch(/password|is_active|mfa_|profile_picture|remove_profile_picture/i);
    fireEvent.click(screen.getByRole('button', { name: 'Clear saved draft' }));
    await waitFor(() => expect(screen.getByLabelText(/Email Address/)).toHaveValue('old@example.test'));
    expect(container).toBeInTheDocument();
  });

  it('restores nested service requirements and clears draft to original record', async () => {
    const service = { id: 'service-8', name: 'Legal aid', description: 'Original', agcy_id: 'agency-2', processing_days: 5, requirements: [{ id: 'req-4', name: 'Passport', description: 'Valid passport', is_required: true }] };
    sessionStorage.setItem('owb_session_draft_v1_user-1_admin.service_service-8', JSON.stringify({ version: 1, data: { name: 'Draft legal aid', requirements: [{ id: 'req-4', name: 'Draft passport', description: 'Updated', is_required: true }] } }));
    const view = render(<ServiceFormModal service={service} allAgencies={[{ id: 'agency-2', name: 'Agency' }]} />);
    await waitFor(() => expect(screen.getByDisplayValue('Draft legal aid')).toBeInTheDocument());
    expect(screen.getByDisplayValue('Draft passport')).toBeInTheDocument();
    expect(screen.getByDisplayValue('5')).toBeInTheDocument();
    fireEvent.change(screen.getByDisplayValue('5'), { target: { value: '7' } });
    await waitFor(() => expect(JSON.stringify(sessionStorage)).toContain('7'));
    view.unmount();
    render(<ServiceFormModal service={service} allAgencies={[{ id: 'agency-2', name: 'Agency' }]} />);
    await waitFor(() => expect(screen.getByDisplayValue('7')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: 'Clear saved draft' }));
    await waitFor(() => expect(screen.getByDisplayValue('Legal aid')).toBeInTheDocument());
    expect(screen.getByDisplayValue('Passport')).toBeInTheDocument();
    expect(screen.getByDisplayValue('5')).toBeInTheDocument();
  });
});
