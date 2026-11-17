import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import CaseIntakeForm, { buildIntakePayload } from '../CaseIntakeForm';

vi.mock('@inertiajs/react', () => ({
    router: { get: vi.fn() },
    usePage: () => ({ props: {} }),
}));

function createMockForm(data = {}) {
    return {
        data: {
            client_type: 'OFW',
            intake_date: '',
            first_name: '',
            middle_name: '',
            last_name: '',
            suffix: '',
            date_of_birth: '',
            sex: '',
            email: '',
            contact_number: '',
            civil_status: '',
            civil_status_other: '',
            deployment_mode: '',
            employment_status: '',
            address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
            employment_employer_name: '',
            employment_employer_address: '',
            employment_position: '',
            employment_country: '',
            employment_last_position: '',
            employment_date_of_arrival: '',
            vulnerability_indicator: 'None',
            summary: '',
            proposed_assessments: [],
            proposed_assessment_other: '',
            category_ids: [],
            confirmed_assessments: [],
            confirmed_assessment_other: '',
            return_reason: '',
            documents: {},
            consent_text_version: 'case-intake-v1',
            consent: false,
            complied_onsite_slots: [],
            staff_summary: '',
            nok_name: '',
            nok_relationship: '',
            nok_contact_number: '',
            nok_email: '',
            nok_vulnerability_indicator: 'None',
            missing_document_reasons: {},
            ...data,
        },
        setData: vi.fn(),
        errors: {},
        processing: false,
    };
}

describe('CaseIntakeForm email field', () => {
    it('blocks malformed editable emails, focuses and clears errors on edit', () => {
        const submit = vi.fn((event) => event.preventDefault());
        const form = createMockForm({ email: 'bad', client_type: 'NEXT_OF_KIN', nok_email: 'also-bad' });
        render(<CaseIntakeForm form={form} mode="staff" onSubmit={submit} />);
        const email = document.querySelector('#intake-email');
        const nok = document.querySelector('#intake-nok-email');
        fireEvent.submit(email.closest('form'));
        expect(submit).not.toHaveBeenCalled();
        expect(email).toHaveFocus();
        expect(email).toHaveAccessibleDescription('Enter a valid email address.');
        expect(nok).toHaveAccessibleDescription('Enter a valid email address.');
        fireEvent.change(email, { target: { value: 'valid@example.com' } });
        expect(email).not.toHaveAttribute('aria-describedby');
    });

    it('allows readonly email and valid editable email submissions', () => {
        const submit = vi.fn((event) => event.preventDefault());
        const { rerender } = render(<CaseIntakeForm form={createMockForm({ email: 'bad', proposed_assessments: ['LEGAL_ASSISTANCE'] })} mode="public" onSubmit={submit} />);
        fireEvent.submit(document.querySelector('#intake-email').closest('form'));
        rerender(<CaseIntakeForm form={createMockForm({ email: 'valid@example.com' })} mode="staff" onSubmit={submit} />);
        fireEvent.submit(document.querySelector('#intake-email').closest('form'));
        expect(submit).toHaveBeenCalledTimes(2);
    });

    it('email is readonly in public mode', () => {
        const form = createMockForm({ email: 'ofw@example.com' });
        render(<CaseIntakeForm form={form} mode="public" onSubmit={vi.fn()} />);

        const emailInput = screen.getByLabelText(/Email/i);
        expect(emailInput).toHaveAttribute('readonly');
        expect(emailInput).toHaveAttribute('aria-readonly', 'true');
        expect(emailInput).toHaveValue('ofw@example.com');
        expect(screen.getByText(/Your registered account email/i)).toBeInTheDocument();
    });

    it('sex picker uses values accepted by case filing', () => {
        render(<CaseIntakeForm form={createMockForm()} mode="staff" onSubmit={vi.fn()} />);
        const sex = document.querySelector('select[aria-hidden="true"]:has(option[value="MALE"])');
        expect(sex).not.toBeNull();
        expect([...sex.options].map((option) => option.value)).toEqual(['', 'MALE', 'FEMALE']);
    });

    it('email is editable in staff mode', () => {
        const form = createMockForm({ email: '' });
        render(<CaseIntakeForm form={form} mode="staff" onSubmit={vi.fn()} />);

        const emailInput = screen.getByLabelText(/Email/i);
        expect(emailInput).not.toHaveAttribute('readonly');
        expect(emailInput).not.toHaveAttribute('aria-readonly', 'true');
    });

    it('readonly email field has visual indicator', () => {
        const form = createMockForm({ email: 'locked@example.com' });
        render(<CaseIntakeForm form={form} mode="public" onSubmit={vi.fn()} />);

        const emailInput = screen.getByLabelText(/Email/i);
        expect(emailInput.className).toContain('bg-slate-50');
        expect(emailInput.className).toContain('cursor-not-allowed');
    });
});

describe('case intake choices', () => {
    it('requires at least one requested assistance only in public mode', () => {
        const submit = vi.fn((event) => event.preventDefault());
        const form = createMockForm();
        const { rerender } = render(<CaseIntakeForm form={form} mode="public" onSubmit={submit} />);
        const group = screen.getByRole('group', { name: 'Requested assistance (required)' });
        const choice = within(group).getByLabelText('Legal assistance');
        fireEvent.submit(choice.closest('form'));
        expect(submit).not.toHaveBeenCalled();
        expect(group).toHaveAccessibleDescription('Select at least one type of assistance.');
        expect(within(group).getAllByRole('checkbox')[0]).toHaveFocus();
        fireEvent.click(choice);
        expect(form.setData).toHaveBeenCalledWith('proposed_assessments', ['LEGAL_ASSISTANCE']);
        expect(group).not.toHaveAccessibleDescription();
        rerender(<CaseIntakeForm form={createMockForm({ proposed_assessments: ['LEGAL_ASSISTANCE'] })} mode="public" onSubmit={submit} />);
        fireEvent.submit(choice.closest('form'));
        rerender(<CaseIntakeForm form={createMockForm()} mode="staff" onSubmit={submit} />);
        fireEvent.submit(choice.closest('form'));
        expect(submit).toHaveBeenCalledTimes(2);
    });

    it.each(['public', 'staff'])('offers common occupations and custom entry in %s mode', (mode) => {
        const form = createMockForm();
        render(<CaseIntakeForm form={form} mode={mode} onSubmit={vi.fn()} />);
        expect(screen.getByText('Domestic Helper / Household Service Worker')).toBeInTheDocument();
        expect(screen.getByText('Nurse (Registered Nurse)')).toBeInTheDocument();
        const occupation = document.querySelector('#intake-last-position + select');
        expect(occupation?.lastElementChild).toHaveValue('OTHER');
        fireEvent.change(occupation, { target: { value: 'OTHER' } });
        expect(form.setData).toHaveBeenCalledWith('employment_last_position', '');
        expect(screen.getByLabelText('Specify last occupation')).toBeInTheDocument();
    });

    it('shows one upload only after evidence selection and clears previous evidence on change', () => {
        const form = createMockForm({ documents: { passport_front_page: new File(['passport'], 'passport.pdf') } });
        render(<CaseIntakeForm form={form} mode="public" onSubmit={vi.fn()} />);
        expect(screen.queryByLabelText('Employment contract abroad')).not.toBeInTheDocument();
        const evidence = document.querySelector('select[aria-hidden="true"]:has(option[value="employment_contract"])');
        fireEvent.change(evidence, { target: { value: 'employment_contract' } });
        expect(screen.getByLabelText('Employment contract abroad')).toBeInTheDocument();
        expect(form.setData).toHaveBeenCalledWith('documents', { passport_front_page: form.data.documents.passport_front_page });
    });
});

describe('staff classification', () => {
    it('keeps category and confirmed choices separate from requested assistance', () => {
        const form = createMockForm({ confirmed_assessments: ['OTHER'] });
        render(<CaseIntakeForm form={form} mode="staff" options={{ categories: [{ id: 'cat-1', name: 'Welfare' }], assessmentOptions: ['OTHER'] }} onSubmit={vi.fn()} />);
        fireEvent.click(screen.getByLabelText('Welfare'));
        expect(form.setData).toHaveBeenCalledWith('category_ids', ['cat-1']);
        const confirmed = screen.getByRole('group', { name: 'Confirmed assessments *' });
        fireEvent.click(within(confirmed).getByLabelText('Other'));
        expect(form.setData).toHaveBeenCalledWith('confirmed_assessments', []);
        expect(screen.getByLabelText('Other confirmed assessment *')).toBeRequired();
        expect(screen.getByRole('heading', { name: 'Requested assistance' })).toBeInTheDocument();
    });

    it('explains when no categories are available', () => {
        render(<CaseIntakeForm form={createMockForm()} mode="staff" onSubmit={vi.fn()} />);
        expect(screen.getByText(/No active case categories are available/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Submit case' })).toBeDisabled();
    });

    it('omits staff controls from public intake', () => {
        render(<CaseIntakeForm form={createMockForm()} mode="public" options={{ categories: [{ id: 'cat-1', name: 'Welfare' }] }} onSubmit={vi.fn()} />);
        expect(screen.queryByText('Staff classification')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Welfare')).not.toBeInTheDocument();
    });

    it('sends staff classification only in staff payload', () => {
        const data = createMockForm({ category_ids: ['cat-1'], proposed_assessments: ['LEGAL_ASSISTANCE'], confirmed_assessments: ['OTHER'], confirmed_assessment_other: '  Assessment  ' }).data;
        expect(buildIntakePayload(data, 'staff')).toMatchObject({ category_ids: ['cat-1'], proposed_assessments: ['LEGAL_ASSISTANCE'], confirmed_assessments: ['OTHER'], confirmed_assessment_other: 'Assessment' });
        const publicPayload = buildIntakePayload(data, 'public');
        expect(publicPayload).not.toHaveProperty('category_ids');
        expect(publicPayload).not.toHaveProperty('confirmed_assessments');
        expect(publicPayload).not.toHaveProperty('confirmed_assessment_other');
        expect(buildIntakePayload({ ...data, confirmed_assessments: [] }, 'staff').confirmed_assessment_other).toBe('');
    });
});

describe('buildIntakePayload consent contract', () => {
    it('staff payload carries consent and onsite slots, not consent method or evidence', () => {
        const data = { ...createMockForm().data, consent: true, complied_onsite_slots: ['arrival_stamp'] };

        const payload = buildIntakePayload(data, 'staff');

        expect(payload.consent).toBe(true);
        expect(payload.complied_onsite_slots).toEqual(['arrival_stamp']);
        expect(payload).not.toHaveProperty('consent_method');
        expect(payload).not.toHaveProperty('consent_evidence');
    });

    it('public payload carries consent only', () => {
        const data = { ...createMockForm().data, consent: true };

        const payload = buildIntakePayload(data, 'public');

        expect(payload.consent).toBe(true);
        expect(payload).not.toHaveProperty('consent_method');
        expect(payload).not.toHaveProperty('consent_evidence');
        expect(payload).not.toHaveProperty('complied_onsite_slots');
    });
});
