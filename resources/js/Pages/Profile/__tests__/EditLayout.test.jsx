import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { page, form, transformCallback } = vi.hoisted(() => ({
    page: { props: { auth: { user: {} }, notificationPrefs: {} } },
    form: { patch: vi.fn(), transform: vi.fn((callback) => { transformCallback.current = callback; }), setData: vi.fn(), errors: {}, processing: false, setError: vi.fn(), clearErrors: vi.fn() },
    transformCallback: { current: null },
}));
vi.mock('ziggy-js', () => ({ route: (name) => name }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: vi.fn(), delete: vi.fn() },
    useForm: (data) => { form.data = { ...data, name: 'Updated User' }; return form; },
    usePage: () => page,
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div data-testid="staff-layout">{children}</div> }));
vi.mock('@/Layouts/OfwLayout', () => ({ default: ({ children }) => <div data-testid="portal-layout">{children}</div> }));
vi.mock('@/Components/DashboardBanner', () => ({ default: () => null }));
vi.mock('@/Pages/Profile/Partials/ChangeEmailForm', () => ({ default: () => null }));
vi.mock('@/Pages/Profile/Partials/DeleteUserForm', () => ({ default: () => null }));
vi.mock('@/Pages/Profile/Partials/UpdatePasswordForm', () => ({ default: () => null }));
vi.mock('@/Components/AgencyInfoCard', () => ({ default: () => null }));
vi.mock('@/Components/CropImageModal', () => ({ default: () => null }));
vi.mock('@/Components/MfaSetup', () => ({ default: ({ mfaEnabled }) => <span>{mfaEnabled ? 'MFA enabled' : 'MFA disabled'}</span> }));
vi.mock('@/Components/ProfileHeader', () => ({ default: () => null }));
vi.mock('@/Components/ui/ToggleSwitch', () => ({ default: () => null }));
vi.mock('@/Components/ui/ProfileCard', () => ({
    ProfileCard: ({ children }) => <div>{children}</div>, ProfileField: ({ children, label, htmlFor }) => <div><label htmlFor={htmlFor}>{label}</label>{children}</div>,
    ProfileSaveBar: ({ processing, note }) => <><button type="submit" disabled={processing}>Save profile</button>{note && <p>{note}</p>}</>, ProfileSection: ({ children, title }) => <section><h2>{title}</h2>{children}</section>,
    ProfileStaticField: () => null, ProfileTextInput: ({ label, id, onChange, ...props }) => <input aria-label={label} id={id} {...props} onChange={onChange ? (event) => onChange(event.target.value) : undefined} />, PROFILE_INPUT_CLASS: '', PROFILE_TEXTAREA_CLASS: '',
}));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));
vi.mock('@/Hooks/useClientValidation', () => ({ default: () => ({ validate: () => true }) }));
vi.mock('@/Hooks/useToast', () => ({ useToast: () => ({ success: vi.fn() }) }));
vi.mock('@/Schemas/profileSchemas', () => ({ profileSchema: {} }));
vi.mock('@/Components/Select', () => ({ default: () => null }));

import Edit from '../Edit';

function renderProfile(role, type, mfaEnabled = true) {
    page.props.auth.user = {
        role, type, id: 'fixture', name: 'Test User', email: 'test@example.com', emergency_contact: {},
    };
    return render(<Edit mfaEnabled={mfaEnabled} mfaRequired={role === 'ADMIN'} notificationPrefs={{}} />);
}

describe('Profile layout selection', () => {
    beforeEach(() => {
        page.props.auth.user = {};
        form.patch.mockClear();
        form.transform.mockClear();
        transformCallback.current = null;
    });

    it('uses OFW portal navigation for OFW-linked next of kin with MFA enabled', () => {
        renderProfile('OFW', 'NEXT_OF_KIN');
        expect(screen.getByTestId('portal-layout')).toBeInTheDocument();
        expect(screen.getByText('MFA enabled')).toBeInTheDocument();
    });

    it('hides staff-only profile details for OFW and next of kin', () => {
        renderProfile('OFW', 'NEXT_OF_KIN');
        for (const label of ['Department', 'Office location', 'Occupation / Title', 'Bio / About me']) {
            expect(screen.queryByLabelText(label)).not.toBeInTheDocument();
        }
        for (const heading of ['Emergency contact', 'Notifications']) {
            expect(screen.queryByRole('heading', { name: heading })).not.toBeInTheDocument();
        }
        expect(screen.getByLabelText(/first name/i)).toBeInTheDocument();
        expect(screen.getByLabelText('Contact number')).toBeInTheDocument();
    });

    it('keeps staff profile fields visible', () => {
        renderProfile('CASE_MANAGER');
        expect(screen.getByTestId('staff-layout')).toBeInTheDocument();
        expect(screen.queryByTestId('portal-layout')).not.toBeInTheDocument();
        for (const label of ['Department', 'Office location', 'Occupation / Title', 'Bio / About me']) {
            expect(screen.getByLabelText(label)).toBeInTheDocument();
        }
        expect(screen.getByRole('heading', { name: 'Emergency contact' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Notifications' })).toBeInTheDocument();
    });

    it('explains why admin profile changes cannot be saved before MFA enrollment', async () => {
        const { fireEvent } = await import('@testing-library/react');
        renderProfile('ADMIN', undefined, false);
        expect(screen.getByText(/enable two-factor authentication before saving profile changes/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save profile' })).toBeDisabled();
        fireEvent.submit(screen.getByRole('button', { name: 'Save profile' }).closest('form'));
        expect(form.patch).not.toHaveBeenCalled();
    });

    it('submits only account fields for OFW through shared profile PATCH', async () => {
        const { fireEvent } = await import('@testing-library/react');
        renderProfile('OFW', 'OFW');
        fireEvent.submit(screen.getByRole('button', { name: 'Save profile' }).closest('form'));
        expect(form.transform).toHaveBeenCalledOnce();
        const payload = transformCallback.current();
        expect(payload).toEqual({
            email: 'test@example.com',
            contact_number: '',
            timezone: 'Asia/Manila',
            first_name: '',
            middle_name: '',
            last_name: '',
            suffix: '',
            date_of_birth: '',
            sex: '',
        });
        expect(payload).not.toHaveProperty('position');
        expect(payload).not.toHaveProperty('department');
        expect(payload).not.toHaveProperty('office_location');
        expect(payload).not.toHaveProperty('bio');
        expect(payload).not.toHaveProperty('emergency_contact');
        expect(payload).not.toHaveProperty('notifications_config');
        expect(form.patch).toHaveBeenCalledWith('profile.update', expect.objectContaining({ preserveScroll: true }));
    });
});
