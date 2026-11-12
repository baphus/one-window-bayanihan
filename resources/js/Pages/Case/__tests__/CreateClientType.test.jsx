import '@testing-library/jest-dom/vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

let latestData;
const pageProps = { categories: [], caseIssues: [], occupationOptions: [], auth: { user: { id: 'user-1' } } };
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/AddressDropdowns', () => ({ default: () => <div /> }));
vi.mock('@/Components/CountrySelect', () => ({ default: () => <div /> }));
vi.mock('@/Components/PhoneInput', () => ({ default: () => <input aria-label="phone" /> }));
vi.mock('@/Components/SearchableSelect', () => ({ default: () => <div /> }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));
vi.mock('@/Components/ClientProfileSummaryModal', () => ({ default: () => null }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }));
vi.mock('@/lib/addressResolver', () => ({ formatResolvedAddress: () => '' }));
vi.mock('@inertiajs/react', async () => {
    const React = await vi.importActual('react');
    return {
        Head: () => null, Link: ({ children }) => <a href="#">{children}</a>, usePage: () => ({ props: pageProps }),
        useForm: () => {
            const [data, setData] = React.useState({ client_type: 'OFW', category_ids: [], vulnerability_indicator: 'None', nok_vulnerability_indicator: 'None', summary: '', client: { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' }, address: { region: '', province: '', city_municipality: '', barangay: '', street: '' }, employment: { employer_name: '', last_country: '', last_position: '', date_of_arrival: '', start_date: '', end_date: '', is_present: false }, next_of_kin: [], selected_nok_index: '', consent: false, selected_client_id: '', case_issue_id: '' });
            latestData = data;
            return { data, setData: (key, value) => setData((current) => typeof key === 'function' ? key(current) : ({ ...current, [key]: value })), post: vi.fn(), transform: vi.fn(), processing: false, errors: {}, setError: vi.fn(), clearErrors: vi.fn() };
        },
    };
});
globalThis.route = (name, id) => id ? `/cases/${id}/${name}` : `/${name}`;
globalThis.window.axios = { get: vi.fn(() => new Promise(() => {})), post: vi.fn() };
import CaseCreate from '../Create';

afterEach(cleanup);

describe('case create client type', () => {
    it('places client type in Step 1 and removes existing/new client picker', () => {
        render(<CaseCreate />);
        expect(screen.getByRole('heading', { name: 'Client Type' })).toBeInTheDocument();
        expect(screen.queryByText('Existing Clients')).not.toBeInTheDocument();
        expect(screen.queryByText('New Client')).not.toBeInTheDocument();
        expect(screen.queryByPlaceholderText(/search clients/i)).not.toBeInTheDocument();
        expect(screen.getByText('OFW Email Address')).toBeInTheDocument();
    });

    it('renders exactly one required NOK form and removes it from OFW view', () => {
        render(<CaseCreate />);
        fireEvent.click(screen.getByRole('radio', { name: 'Next of Kin' }));
        expect(screen.getByText('Complete required details for one next of kin contact.')).toBeInTheDocument();
        expect(screen.getByText('Next of Kin #1')).toBeInTheDocument();
        expect(screen.queryByText('Add Next of Kin')).not.toBeInTheDocument();
        expect(screen.queryByText('Primary')).not.toBeInTheDocument();
        expect(latestData.next_of_kin).toHaveLength(1);
        expect(latestData.selected_nok_index).toBe('0');
        expect(screen.getByRole('button', { name: /^Next / })).toBeDisabled();
        expect(screen.getByText('OFW Email Address')).toBeInTheDocument();
        expect(screen.getAllByLabelText(/phone/i).length).toBeGreaterThan(0);
        fireEvent.click(screen.getByRole('radio', { name: 'Overseas Filipino Worker' }));
        expect(screen.queryByText('Next of Kin #1')).not.toBeInTheDocument();
        expect(latestData.next_of_kin).toEqual([]);
        expect(latestData.selected_nok_index).toBe('');
    });
});
