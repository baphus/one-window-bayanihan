import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import Intake from '../Intake';

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <div data-testid="head">{title}</div>,
    Link: ({ href, children }) => <a href={href}>{children}</a>,
    useForm: vi.fn((initialData) => ({
        data: initialData,
        setData: vi.fn(),
        errors: {},
        processing: false,
        isDirty: false,
        clearErrors: vi.fn(),
        transform: vi.fn((fn) => fn(initialData)),
        post: vi.fn(),
    })),
}));

vi.mock('@/Layouts/OfwLayout', () => ({
    default: ({ children }) => <div data-testid="ofw-layout">{children}</div>,
}));

vi.mock('@/Components/CaseIntakeForm', () => ({
    default: ({ form }) => <div data-testid="intake-form">Email: {form.data.email}</div>,
    buildIntakePayload: vi.fn(),
    emptyIntakeData: ({ intakeDate, consentTextVersion, email }) => ({
        client_type: 'OFW',
        intake_date: intakeDate,
        email: email || '',
        consent_text_version: consentTextVersion,
    }),
}));

vi.mock('@/Hooks/useUnsavedChanges', () => ({
    default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }),
}));

vi.mock('@/Hooks/useToast', () => ({
    useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}));

vi.mock('@/utils/safeRoute', () => ({
    default: (name, params, fallback) => fallback || `/${name}`,
}));

describe('Intake page email initialization', () => {
    it('initializes email from auth.user.email', () => {
        const props = {
            auth: { user: { email: 'ofw@registered.com' } },
            consentTextVersion: 'case-intake-v1',
            documentSlots: [],
            returnReasons: [],
            civilStatuses: [],
            deploymentModes: [],
            employmentStatuses: [],
            assessmentOptions: [],
            vulnerabilityOptions: [],
            occupationOptions: [],
        };

        render(<Intake {...props} />);

        expect(screen.getByTestId('intake-form').textContent).toContain('ofw@registered.com');
    });

    it('initializes with empty email when auth.user.email is missing', () => {
        const props = {
            auth: { user: {} },
            consentTextVersion: 'case-intake-v1',
            documentSlots: [],
            returnReasons: [],
            civilStatuses: [],
            deploymentModes: [],
            employmentStatuses: [],
            assessmentOptions: [],
            vulnerabilityOptions: [],
            occupationOptions: [],
        };

        render(<Intake {...props} />);

        expect(screen.getByTestId('intake-form').textContent).toBe('Email: ');
    });
});
