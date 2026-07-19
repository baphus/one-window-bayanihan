import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, within, cleanup } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const setData = vi.fn();
const pageProps = { existingDraft: null, categories: [], caseIssues: [], positionOptions: [], auth: { user: { id: 'user-1' } } };

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/AddressDropdowns', () => ({ default: () => <div /> }));
vi.mock('@/Components/CountrySelect', () => ({ default: () => <div /> }));
vi.mock('@/Components/PhoneInput', () => ({ default: () => <div /> }));
vi.mock('@/Components/SearchableSelect', () => ({ default: () => <div /> }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));
vi.mock('@/Components/ClientProfileSummaryModal', () => ({ default: () => null }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useAutoSave', () => ({ default: () => ({ autoSaveStatus: 'idle', draftId: null, cancelPendingSave: vi.fn() }) }));
vi.mock('@/Hooks/useLocalStorageDraft', () => ({ default: () => ({ hasLocalBackup: false, localBackup: null, clearLocalBackup: vi.fn() }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: () => '' }));
vi.mock('@inertiajs/react', async () => {
  const React = await vi.importActual('react');
  const initialData = { client_type: 'OFW', category_ids: [], vulnerability_indicator: 'None', nok_vulnerability_indicator: 'None', summary: '', client: { first_name: 'Maria', last_name: 'Santos', middle_initial: '', suffix: '', date_of_birth: '1990-01-01', sex: 'Female', email: 'maria@example.test', contact_number: '09171234567' }, address: { region: 'VII', province: 'Cebu', city_municipality: 'Cebu City', barangay: 'Lahug', street: '' }, employment: { employer_name: 'Example Co.', position: '', country: '', start_date: '2020-01-01', end_date: '2024-01-01', last_country: 'Japan', last_position: 'Caregiver', date_of_arrival: '2024-02-01', is_present: false }, next_of_kin: [], selected_nok_index: '', consent: false, selected_client_id: '', is_draft: false, case_issue_id: '' };
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
globalThis.window.axios = { get: vi.fn(() => new Promise(() => {})), post: vi.fn() };

import CaseCreate from '../Create';

const categories = [
  { id: 'cat-1', name: 'Legal Assistance', color: '#2563eb' },
  { id: 'cat-2', name: 'Repatriation', color: '#16a34a' },
  { id: 'cat-3', name: 'Welfare Concern', color: '#d97706' },
];

function renderCaseSetup() {
  render(<CaseCreate />);
  fireEvent.click(screen.getByRole('button', { name: /^next\b/i }));
}

describe('create case category checkbox dropdown', () => {
  afterEach(() => cleanup());
  beforeEach(() => {
    pageProps.existingDraft = null;
    pageProps.categories = categories;
    setData.mockClear();
  });

  it('renders a category trigger with an empty-state prompt', () => {
    renderCaseSetup();

    const trigger = screen.getByRole('combobox', { name: /case categories/i });
    expect(trigger).toBeInTheDocument();
    expect(trigger).toHaveTextContent(/select categories/i);
    expect(trigger).toHaveAttribute('aria-expanded', 'false');
  });

  it('toggles two categories into category_ids without modifier keys', () => {
    renderCaseSetup();

    const trigger = screen.getByRole('combobox', { name: /case categories/i });
    fireEvent.click(trigger);

    const menu = screen.getByRole('listbox', { name: /categories/i });
    const option1 = within(menu).getByRole('option', { name: /Legal Assistance/ });
    const option2 = within(menu).getByRole('option', { name: /Repatriation/ });

    fireEvent.click(option1);
    fireEvent.click(option2);

    expect(setData).toHaveBeenCalledWith('category_ids', ['cat-1', 'cat-2']);
  });

  it('removes one selected category while preserving the other', () => {
    renderCaseSetup();

    const trigger = screen.getByRole('combobox', { name: /case categories/i });
    fireEvent.click(trigger);

    const menu = screen.getByRole('listbox', { name: /categories/i });
    fireEvent.click(within(menu).getByRole('option', { name: /Legal Assistance/ }));
    fireEvent.click(within(menu).getByRole('option', { name: /Repatriation/ }));

    fireEvent.click(within(menu).getByRole('option', { name: /Legal Assistance/ }));

    expect(setData).toHaveBeenCalledWith('category_ids', ['cat-2']);
  });

  it('reflects restored draft categories as checked options', () => {
    pageProps.existingDraft = {
      id: 'draft-cat', case_number: 'CASE-CAT', tracker_number: 'OWBAP-CATTEST',
      client_type: 'OFW', category_ids: ['cat-1', 'cat-3'], draft_client_data: null,
    };
    renderCaseSetup();

    const trigger = screen.getByRole('combobox', { name: /case categories/i });
    expect(trigger).toBeInTheDocument();
    expect(trigger).toHaveTextContent(/Legal Assistance, Welfare Concern/i);

    fireEvent.click(trigger);
    const menu = screen.getByRole('listbox', { name: /categories/i });
    expect(within(menu).getByRole('option', { name: /Legal Assistance/ })).toHaveAttribute('aria-selected', 'true');
    expect(within(menu).getByRole('option', { name: /Welfare Concern/ })).toHaveAttribute('aria-selected', 'true');
    expect(within(menu).getByRole('option', { name: /Repatriation/ })).toHaveAttribute('aria-selected', 'false');
  });
});
