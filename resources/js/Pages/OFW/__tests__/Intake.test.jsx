import { render, screen, renderHook, act, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Intake from '../Intake';
import React from 'react';
import useIntakeSessionDraft, { persistIntakeDraft, removeIntakeDrafts } from '@/Hooks/useIntakeSessionDraft';

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <div data-testid="head">{title}</div>,
    Link: ({ href, children }) => <a href={href}>{children}</a>,
    useForm: vi.fn((initialData) => {
        const [data, setData] = React.useState(initialData);
        const [defaults, setDefaults] = React.useState(initialData);
        return {
            data, setData, errors: {}, processing: false, isDirty: data !== defaults,
            defaults: (value) => setDefaults(value), reset: () => setData(defaults),
            clearErrors: vi.fn(), transform: vi.fn((fn) => fn(data)), post: vi.fn(),
        };
    }),
}));

vi.mock('@/Layouts/OfwLayout', () => ({
    default: ({ children }) => <div data-testid="ofw-layout">{children}</div>,
}));

vi.mock('@/Components/CaseIntakeForm', () => ({
    default: ({ form }) => <div data-testid="intake-form"><span>Email: {form.data.email}</span><button type="button" onClick={() => form.setData((data) => ({ ...data, summary: 'Edited statement' }))}>Edit statement</button></div>,
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

const initial = { first_name: '', email: 'account@example.com', address: { region: '', street: '' }, documents: {}, consent: false, consent_text_version: 'v2', category_ids: [], confirmed_assessments: [], staff_summary: '', complied_onsite_slots: [], missing_document_reasons: {} };

beforeEach(() => sessionStorage.clear());

function useDraftHarness(id) {
    const [data, setData] = React.useState(initial);
    const draft = useIntakeSessionDraft(id, initial, data, setData);
    return { data, setData, draft };
}

describe('intake session drafts', () => {
    it('persists a real edit and restores it after remount', () => {
        const first = renderHook(() => useDraftHarness(1));
        act(() => first.result.current.setData((value) => ({ ...value, summary: 'Need help returning home' })));
        expect(JSON.parse(sessionStorage.getItem('owb_intake_session_v1_1')).data.summary).toBe('Need help returning home');
        first.unmount();
        const restored = renderHook(() => useDraftHarness(1));
        expect(restored.result.current.data.summary).toBe('Need help returning home');
    });

    it('clear resets then allows later edits to save; success suppresses saves', () => {
        const hook = renderHook(() => useDraftHarness(1));
        act(() => hook.result.current.setData((value) => ({ ...value, summary: 'Draft text' })));
        act(() => hook.result.current.draft.clear((defaults) => hook.result.current.setData(defaults)));
        expect(sessionStorage.getItem('owb_intake_session_v1_1')).toBeNull();
        act(() => hook.result.current.setData((value) => ({ ...value, summary: 'New text' })));
        expect(JSON.parse(sessionStorage.getItem('owb_intake_session_v1_1')).data.summary).toBe('New text');
        act(() => hook.result.current.draft.submitted());
        act(() => hook.result.current.setData((value) => ({ ...value, summary: 'After submit' })));
        expect(sessionStorage.getItem('owb_intake_session_v1_1')).toBeNull();
    });

    it('restores valid inputs but excludes files, consent, and staff fields', () => {
        const data = { ...initial, first_name: 'A', address: { region: '07', street: 'Road', province_code: 'leak' }, documents: { passport: new File(['x'], 'x.pdf') }, consent: true, consent_text_version: 'old', category_ids: ['staff'], staff_summary: 'private' };
        persistIntakeDraft('owb_intake_session_v1_1', data);
        const setter = vi.fn();
        const { result } = renderHook(() => useIntakeSessionDraft(1, initial, initial, setter));
        expect(setter.mock.calls[0][0]).toMatchObject({ first_name: 'A', address: { region: '07', street: 'Road' }, documents: {}, consent: false, consent_text_version: 'v2', category_ids: [], staff_summary: '' });
        expect(JSON.parse(sessionStorage.getItem('owb_intake_session_v1_1')).data.address).toEqual({ region: '07', province: '', city_municipality: '', barangay: '', street: 'Road' });
    });

    it('handles malformed and unavailable storage without throwing', () => {
        sessionStorage.setItem('owb_intake_session_v1_1', '{');
        expect(() => renderHook(() => useIntakeSessionDraft(1, initial, initial, vi.fn()))).not.toThrow();
        const setter = vi.fn();
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('blocked'); });
        const { result } = renderHook(() => useIntakeSessionDraft(2, initial, initial, setter));
        act(() => result.current.clear());
        act(() => result.current.submitted());
        expect(result.current.status).toBe('');
        vi.restoreAllMocks();
    });

    it('does not write previous account data under new account key', () => {
        const hook = renderHook(({ id, data }) => useIntakeSessionDraft(id, initial, data, vi.fn()), { initialProps: { id: 1, data: initial } });
        hook.rerender({ id: 2, data: { ...initial, summary: 'Old account' } });
        expect(sessionStorage.getItem('owb_intake_session_v1_2')).toBeNull();
    });

    it('removes drafts on logout cleanup', () => {
        sessionStorage.setItem('owb_intake_session_v1_1', 'x');
        removeIntakeDrafts();
        expect(sessionStorage.length).toBe(0);
    });
});

describe('Intake page clear action', () => {
    it('clears saved draft, resets dirty form, and saves later edits', () => {
        const props = { auth: { user: { id: 4, email: 'ofw@example.com' } }, consentTextVersion: 'case-intake-v1', documentSlots: [], returnReasons: [], civilStatuses: [], deploymentModes: [], employmentStatuses: [], assessmentOptions: [], vulnerabilityOptions: [], occupationOptions: [] };
        render(<Intake {...props} />);
        fireEvent.click(screen.getByRole('button', { name: 'Edit statement' }));
        expect(sessionStorage.getItem('owb_intake_session_v1_4')).not.toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Clear draft' }));
        expect(sessionStorage.getItem('owb_intake_session_v1_4')).toBeNull();
        expect(screen.getByRole('status').textContent).toContain('Draft cleared');
        fireEvent.click(screen.getByRole('button', { name: 'Edit statement' }));
        expect(JSON.parse(sessionStorage.getItem('owb_intake_session_v1_4')).data.summary).toBe('Edited statement');
    });
});

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

        expect(screen.getByTestId('intake-form').querySelector('span').textContent).toBe('Email: ');
    });
});
