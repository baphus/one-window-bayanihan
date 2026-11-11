import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import CaseIntakeForm from '../CaseIntakeForm';

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
            return_reason: '',
            documents: {},
            consent_text_version: 'case-intake-v1',
            consent: false,
            consent_method: '',
            consent_evidence: '',
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
    it('email is readonly in public mode', () => {
        const form = createMockForm({ email: 'ofw@example.com' });
        render(<CaseIntakeForm form={form} mode="public" onSubmit={vi.fn()} />);

        const emailInput = screen.getByLabelText(/Email/i);
        expect(emailInput).toHaveAttribute('readonly');
        expect(emailInput).toHaveAttribute('aria-readonly', 'true');
        expect(emailInput).toHaveValue('ofw@example.com');
        expect(screen.getByText(/Your registered account email/i)).toBeInTheDocument();
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
