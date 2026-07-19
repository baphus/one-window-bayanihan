import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const setData = vi.fn();
const pageProps = { existingDraft: null, categories: [], caseIssues: [], positionOptions: [], auth: { user: { id: 'user-1' } } };
const draft = { id: 'draft-123', case_number: 'CASE-1', tracker_number: 'OWBAP-TEST123', client_type: 'OFW', draft_client_data: {
  first_name: 'Draft', last_name: 'Client', date_of_birth: '1990-01-01', sex: 'Male', email: 'draft@example.test', contact_number: '09170000000', consent: true,
  address: { region: 'Region VII', province: 'Cebu', city_municipality: 'Cebu City', barangay: 'Lahug' },
  employment: { employer_name: 'Employer', position: 'Worker', country: 'UAE', start_date: '2020-01-02', end_date: '2024-03-04', date_of_arrival: '2024-03-10' },
} };
pageProps.existingDraft = draft;

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/AddressDropdowns', () => ({ default: () => <div /> }));
vi.mock('@/Components/CountrySelect', () => ({ default: () => <div /> }));
vi.mock('@/Components/PhoneInput', () => ({ default: () => <div /> }));
vi.mock('@/Components/SearchableSelect', () => ({ default: () => <div /> }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));
vi.mock('@/Components/ClientProfileSummaryModal', () => ({ default: () => null }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useAutoSave', () => ({ default: () => ({ autoSaveStatus: 'idle', draftId: 'draft-123', cancelPendingSave: vi.fn() }) }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ default: () => ({ hasLocalBackup: false, localBackup: null, clearLocalBackup: vi.fn() }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: () => '' }));
vi.mock('@inertiajs/react', async () => {
  const React = await vi.importActual('react');
  const initialData = { client_type: 'OFW', category_ids: [], vulnerability_indicator: 'None', nok_vulnerability_indicator: 'None', summary: '', client: { first_name: 'Draft', last_name: 'Client', middle_initial: '', suffix: '', date_of_birth: '1990-01-01', sex: 'Male', email: 'draft@example.test', contact_number: '09170000000' }, address: { region: 'Region VII', province: 'Cebu', city_municipality: 'Cebu City', barangay: 'Lahug', street: '' }, employment: { employer_name: '', position: '', country: '', last_country: '', last_position: '', start_date: '', end_date: '', date_of_arrival: '', is_present: false }, next_of_kin: [], selected_nok_index: '', consent: false, selected_client_id: '', is_draft: true, case_issue_id: '' };
  return {
  Head: () => null, Link: ({ children }) => <a href="#">{children}</a>,
  usePage: () => ({ props: pageProps }),
    useForm: () => {
      const [data, setState] = React.useState(initialData);
      const update = (key, value) => { setData?.(key, value); setState((current) => ({ ...current, [key]: value })); };
      return { data, setData: update, post: vi.fn(), put: vi.fn(), processing: false, errors: {}, setError: vi.fn(), clearErrors: vi.fn() };
    },
  };
});

globalThis.route = (name, id) => id ? `/cases/${id}/${name}` : `/${name}`;
globalThis.window.axios = { get: vi.fn(() => Promise.resolve({ data: { data: [] } })), post: vi.fn() };

import CaseCreate from '../Create';

describe('rendered draft create behavior', () => {
  afterEach(() => cleanup());
  beforeEach(() => {
    pageProps.existingDraft = JSON.parse(JSON.stringify(draft));
    setData.mockClear();
  });
  it('hydrates the employment object through the rendered form update path', () => {
    render(<CaseCreate />);

    expect(setData).toHaveBeenCalledWith('employment', expect.objectContaining({
      start_date: '2020-01-02',
      end_date: '2024-03-04',
      date_of_arrival: '2024-03-10',
      is_present: false,
    }));
  });

  it('handles null employment data without crashing', () => {
    pageProps.existingDraft = { ...draft, draft_client_data: { ...draft.draft_client_data, employment: null } };

    expect(() => render(<CaseCreate />)).not.toThrow();
  });

  it('renders Update Draft disabled while the draft is clean', () => {
    render(<CaseCreate />);

    expect(screen.getByRole('button', { name: /Update Draft/ })).toBeDisabled();
  });

  it('preserves explicit false present state from JSON hydration', () => {
    pageProps.existingDraft.draft_client_data.employment.is_present = false;
    render(<CaseCreate />);

    expect(setData).toHaveBeenCalledWith('employment', expect.objectContaining({ is_present: false }));
  });

  it('allows a fresh Create Case form to proceed without draft guards', () => {
    pageProps.existingDraft = null;
    render(<CaseCreate />);

    // Step 1 shows Save as Draft and Next — neither should be disabled by draft guards
    expect(screen.getByRole('button', { name: /Save as Draft/ })).not.toBeDisabled();
  });
});
