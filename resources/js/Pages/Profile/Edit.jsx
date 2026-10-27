import { useRef, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import DashboardBanner from '@/Components/DashboardBanner';
import ChangeEmailForm from '@/Pages/Profile/Partials/ChangeEmailForm';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import AgencyInfoCard from '@/Components/AgencyInfoCard';
import CropImageModal from '@/Components/CropImageModal';
import MfaSetup from '@/Components/MfaSetup';
import ProfileHeader from '@/Components/ProfileHeader';
import ToggleSwitch from '@/Components/ui/ToggleSwitch';
import {
    ProfileCard,
    ProfileField,
    ProfileSaveBar,
    ProfileSection,
    ProfileStaticField,
    ProfileTextInput,
    PROFILE_INPUT_CLASS,
    PROFILE_TEXTAREA_CLASS,
} from '@/Components/ui/ProfileCard';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useClientValidation from '@/Hooks/useClientValidation';
import { useToast } from '@/Hooks/useToast';
import { profileSchema } from '@/Schemas/profileSchemas';

/**
 * My Profile — staff (case manager, agency focal, system admin).
 *
 * One card holds everything the user can edit about themselves — account,
 * role details, emergency contact and notification preferences — with a
 * single Save and a single Discard.
 *
 * Credential and account-lifecycle actions (email change, password, two-factor
 * authentication, account deletion) each run through their own endpoint, so
 * they live in a separate Security card: nothing there is submitted by the
 * profile Save bar.
 */

const ROLE_LABELS = {
    CASE_MANAGER: 'Case Manager',
    AGENCY: 'Agency Focal',
    ADMIN: 'System Admin',
};

const TIMEZONES = [
    'Asia/Manila', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Seoul',
    'Asia/Shanghai', 'Pacific/Guam', 'Australia/Sydney',
    'America/New_York', 'America/Los_Angeles', 'Europe/London', 'UTC',
];

export default function Edit({ mustVerifyEmail, status, mfaEnabled, defaultAgency, notificationPrefs }) {
    const user = usePage().props.auth.user;
    const { email_change_step, email_change_hint } = usePage().props;

    const buildInitialData = () => ({
        name: user.name,
        email: user.email,
        position: user.position || '',
        department: user.department || '',
        office_location: user.office_location || '',
        bio: user.bio || '',
        timezone: user.timezone || 'Asia/Manila',
        contact_number: user.contact_number || '',
        emergency_contact: {
            name: user.emergency_contact?.name || '',
            relation: user.emergency_contact?.relation || '',
            phone: user.emergency_contact?.phone || '',
        },
        notifications_config: { ...notificationPrefs },
    });

    const { data, setData, patch, errors, processing, setError, clearErrors } = useForm(buildInitialData());

    const toast = useToast();
    const [avatarPreview, setAvatarPreview] = useState(null);
    const [pendingAvatarFile, setPendingAvatarFile] = useState(null);
    const [avatarUploading, setAvatarUploading] = useState(false);
    const [cropFile, setCropFile] = useState(null);
    const { validate } = useClientValidation(profileSchema, data, setError);

    // The avatar is uploaded on its own endpoint, so only the text fields feed
    // the dirty check and the Save bar.
    const savedSnapshot = useRef(JSON.stringify(buildInitialData()));
    const isDirty = JSON.stringify(data) !== savedSnapshot.current;
    const { UnsavedModal, bypassNext } = useUnsavedChanges(isDirty);

    const roleLabel = ROLE_LABELS[user.role] || user.role;
    const agency = user?.agency ?? defaultAgency ?? null;

    /* ---------- Avatar ---------- */

    function handleAvatarSelect(file) {
        setCropFile(file);
    }

    function handleCropComplete(croppedFile) {
        setPendingAvatarFile(croppedFile);
        setAvatarPreview(URL.createObjectURL(croppedFile));
        setCropFile(null);
    }

    function handleCancelCrop() {
        setCropFile(null);
    }

    function handleCancelPendingAvatar() {
        if (avatarPreview) URL.revokeObjectURL(avatarPreview);
        setPendingAvatarFile(null);
        setAvatarPreview(null);
    }

    function handleConfirmAvatar() {
        if (!pendingAvatarFile) return;
        setAvatarUploading(true);
        setError('avatar', null);

        const formData = new FormData();
        formData.append('profile_picture', pendingAvatarFile);

        router.post(route('profile.avatar.store'), formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Profile picture updated successfully.');
                setPendingAvatarFile(null);
                if (avatarPreview) URL.revokeObjectURL(avatarPreview);
                setAvatarPreview(null);
            },
            onFinish: () => setAvatarUploading(false),
            onError: (errs) => {
                if (errs.profile_picture || errs.avatar) {
                    setError('avatar', errs.profile_picture || errs.avatar);
                }
                setAvatarUploading(false);
            },
        });
    }

    function handleRemoveAvatar() {
        setAvatarUploading(true);
        setError('avatar', null);

        router.delete(route('profile.avatar.destroy'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Profile picture removed.'),
            onFinish: () => setAvatarUploading(false),
        });
    }

    /* ---------- Form ---------- */

    function handleNotificationToggle(key, value) {
        setData('notifications_config', { ...data.notifications_config, [key]: value });
    }

    function setEmergency(field, value) {
        setData('emergency_contact', { ...data.emergency_contact, [field]: value });
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (!isDirty || processing) return;

        bypassNext();
        clearErrors();
        if (!validate()) return;

        patch(route('profile.update'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Re-baseline against what was just saved so the Save bar
                // returns to its resting state.
                savedSnapshot.current = JSON.stringify(data);
                toast.success('Profile updated successfully.');
            },
        });
    }

    function handleDiscard() {
        clearErrors();
        setData(buildInitialData());
    }

    return (
        <AppLayout title="Profile">
            <Head title="Profile" />

            <p className="text-[12px] text-slate-500">Home / My Profile</p>

            <h1 className="mt-2 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                My Profile
            </h1>
            <p className="mt-1.5 text-[14px] text-slate-600">
                Manage your account details and preferences.
            </p>

            <DashboardBanner
                onSkip={() => { bypassNext(); router.post(route('onboarding.skip-profile'), {}, { preserveState: true, preserveScroll: true }); }}
                onDismiss={() => { bypassNext(); router.post(route('onboarding.skip-profile'), {}, { preserveState: true, preserveScroll: true }); }}
            />

            <form onSubmit={handleSubmit} className="mt-5 space-y-6">
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    {/* ---------- The editable profile card ---------- */}
                    <ProfileCard>
                        {/* Account */}
                        <ProfileSection
                            title="Account"
                            description="Your photo and the details colleagues see on your records."
                        >
                            <ProfileHeader
                                onAvatarSelect={handleAvatarSelect}
                                avatarPreview={avatarPreview}
                                saving={processing || avatarUploading}
                            />

                            {errors.avatar ? (
                                <p className="mt-3 flex items-center gap-1.5 text-[12px] font-medium text-rose-600">
                                    <span className="material-symbols-outlined text-[15px] leading-none" aria-hidden="true">error</span>
                                    {errors.avatar}
                                </p>
                            ) : null}

                            {pendingAvatarFile ? (
                                <div className="mt-3 flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={handleConfirmAvatar}
                                        disabled={avatarUploading}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-[12px] font-bold text-white transition-colors hover:bg-primary-container disabled:opacity-50"
                                    >
                                        {avatarUploading ? 'Saving…' : 'Save photo'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleCancelPendingAvatar}
                                        disabled={avatarUploading}
                                        className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-[12px] font-bold text-slate-700 transition-colors hover:border-slate-300 disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            ) : null}

                            {!pendingAvatarFile && user.avatar_url ? (
                                <div className="mt-3">
                                    <button
                                        type="button"
                                        onClick={handleRemoveAvatar}
                                        disabled={avatarUploading}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-[12px] font-bold text-rose-600 transition-colors hover:border-rose-200 hover:bg-rose-50 disabled:opacity-50"
                                    >
                                        <span className="material-symbols-outlined text-[15px] leading-none" aria-hidden="true">delete</span>
                                        Remove photo
                                    </button>
                                </div>
                            ) : null}

                            <div className="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-4">
                                <ProfileStaticField label="Role" value={roleLabel} />
                                <ProfileStaticField label="Email" value={user.email} />
                                <ProfileStaticField label="Agency" value={agency?.name} />
                                <ProfileStaticField label="Office" value={data.office_location} />
                            </div>
                        </ProfileSection>

                        {/* Personal information */}
                        <ProfileSection
                            title="Personal information"
                            description="Your name and role details. Email changes use the Security section below."
                        >
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <ProfileTextInput
                                    id="name"
                                    label="Full name"
                                    value={data.name}
                                    onChange={(v) => setData('name', v)}
                                    error={errors.name}
                                    autoComplete="name"
                                    placeholder="e.g. Juan Dela Cruz"
                                />
                                <ProfileTextInput
                                    id="position"
                                    label="Occupation / Title"
                                    value={data.position}
                                    onChange={(v) => setData('position', v)}
                                    error={errors.position}
                                    placeholder="e.g. Case Management Officer"
                                />
                                <ProfileTextInput
                                    id="department"
                                    label="Department"
                                    value={data.department}
                                    onChange={(v) => setData('department', v)}
                                    error={errors.department}
                                    placeholder="e.g. Migrant Services"
                                />
                                <ProfileTextInput
                                    id="office_location"
                                    label="Office location"
                                    value={data.office_location}
                                    onChange={(v) => setData('office_location', v)}
                                    error={errors.office_location}
                                    placeholder="e.g. Cebu City"
                                />
                                <ProfileTextInput
                                    id="contact_number"
                                    label="Contact number"
                                    type="tel"
                                    value={data.contact_number}
                                    onChange={(v) => setData('contact_number', v)}
                                    error={errors.contact_number}
                                    placeholder="e.g. +63 912 345 6789"
                                    autoComplete="tel"
                                />
                                <ProfileField label="Timezone" htmlFor="timezone" error={errors.timezone}>
                                    <select
                                        id="timezone"
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                        className={PROFILE_INPUT_CLASS}
                                    >
                                        {TIMEZONES.map((tz) => (
                                            <option key={tz} value={tz}>{tz}</option>
                                        ))}
                                    </select>
                                </ProfileField>
                                <div className="sm:col-span-2">
                                    <ProfileField label="Bio / About me" htmlFor="bio" error={errors.bio}>
                                        <textarea
                                            id="bio"
                                            rows={3}
                                            value={data.bio}
                                            onChange={(e) => setData('bio', e.target.value)}
                                            className={PROFILE_TEXTAREA_CLASS}
                                            placeholder="A brief description of your role and professional background."
                                        />
                                    </ProfileField>
                                </div>
                            </div>
                        </ProfileSection>

                        {/* Emergency contact */}
                        <ProfileSection
                            title="Emergency contact"
                            description="Who we should reach if something happens during duty."
                        >
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <ProfileTextInput
                                    id="emergency_contact_name"
                                    label="Full name"
                                    value={data.emergency_contact?.name ?? ''}
                                    onChange={(v) => setEmergency('name', v)}
                                    error={errors['emergency_contact.name']}
                                    placeholder="e.g. Maria Dela Cruz"
                                />
                                <ProfileTextInput
                                    id="emergency_contact_relation"
                                    label="Relationship"
                                    value={data.emergency_contact?.relation ?? ''}
                                    onChange={(v) => setEmergency('relation', v)}
                                    error={errors['emergency_contact.relation']}
                                    placeholder="e.g. Spouse"
                                />
                                <ProfileTextInput
                                    id="emergency_contact_phone"
                                    label="Phone number"
                                    type="tel"
                                    value={data.emergency_contact?.phone ?? ''}
                                    onChange={(v) => setEmergency('phone', v)}
                                    error={errors['emergency_contact.phone']}
                                    placeholder="e.g. +63 912 345 6789"
                                />
                            </div>
                        </ProfileSection>

                        {/* Notification preferences */}
                        <ProfileSection
                            title="Notifications"
                            description="Choose which updates reach you by email and in the app."
                        >
                            <div className="space-y-3">
                                <ToggleSwitch
                                    checked={data.notifications_config?.email_on_case_assigned ?? false}
                                    onChange={(v) => handleNotificationToggle('email_on_case_assigned', v)}
                                    label="Email when a case is assigned to me"
                                    description="Receive an email when a new case is assigned to you."
                                />
                                <ToggleSwitch
                                    checked={data.notifications_config?.email_on_case_status_change ?? false}
                                    onChange={(v) => handleNotificationToggle('email_on_case_status_change', v)}
                                    label="Email when case status changes"
                                    description="Get notified when the status of a case you're involved with changes."
                                />
                                <ToggleSwitch
                                    checked={data.notifications_config?.email_on_referral ?? false}
                                    onChange={(v) => handleNotificationToggle('email_on_referral', v)}
                                    label="Email when I receive a referral"
                                    description="Receive an email when another user refers a case to you."
                                />
                                <ToggleSwitch
                                    checked={data.notifications_config?.in_app_notifications ?? false}
                                    onChange={(v) => handleNotificationToggle('in_app_notifications', v)}
                                    label="In-app notifications"
                                    description="Show notification badges and alerts within the application."
                                />
                            </div>
                        </ProfileSection>

                        {/* The single Save / Discard bar for this card */}
                        <ProfileSaveBar
                            dirty={isDirty}
                            processing={processing}
                            onDiscard={handleDiscard}
                        />
                    </ProfileCard>

                    {/* ---------- Aside: agency context ---------- */}
                    <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
                        <AgencyInfoCard agency={defaultAgency} />
                    </aside>
                </div>
            </form>

            {/* ---------- Security (own endpoints) ---------- */}
            <div className="mt-6">
                <ProfileCard>
                    <ProfileSection
                        title="Security"
                        description="Each of these actions applies immediately and is not part of the profile save above."
                    >
                        <div className="space-y-6">
                            <ChangeEmailForm
                                initialStep={email_change_step || 'start'}
                                hint={email_change_hint || ''}
                            />
                            <UpdatePasswordForm onBypass={bypassNext} />
                        </div>
                    </ProfileSection>

                    <ProfileSection
                        title="Two-factor authentication"
                        description="Add a second step when signing in."
                    >
                        <MfaSetup mfaEnabled={mfaEnabled} />
                    </ProfileSection>

                    <ProfileSection
                        title="Account"
                        description="Closing your account cannot be undone."
                    >
                        <DeleteUserForm onBypass={bypassNext} />
                    </ProfileSection>
                </ProfileCard>
            </div>

            {UnsavedModal}

            <CropImageModal
                file={cropFile}
                open={cropFile !== null}
                onCropComplete={handleCropComplete}
                onCancel={handleCancelCrop}
            />
        </AppLayout>
    );
}
