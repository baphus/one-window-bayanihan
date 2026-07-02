import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, useRef } from 'react';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import ProfileHeader from '@/Components/ProfileHeader';
import PersonalInfoForm from '@/Components/PersonalInfoForm';
import AgencyInfoCard from '@/Components/AgencyInfoCard';
import NotificationPreferencesSection from '@/Components/NotificationPreferencesSection';
import MfaSetup from '@/Components/MfaSetup';
import PrimaryButton from '@/Components/PrimaryButton';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { profileSchema } from '@/Schemas/profileSchemas';
import useClientValidation from '@/Hooks/useClientValidation';
import DashboardBanner from '@/Components/DashboardBanner';
import ChangeEmailForm from '@/Pages/Profile/Partials/ChangeEmailForm';

export default function Edit({ mustVerifyEmail, status, mfaEnabled, defaultAgency, notificationPrefs }) {
    const user = usePage().props.auth.user;
    const { email_change_step, email_change_hint, email_change_debug_otp } = usePage().props;

    const { data, setData, patch, errors, processing, setError, clearErrors } = useForm({
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
        avatar: null,
        notifications_config: { ...notificationPrefs },
    });

    const [avatarPreview, setAvatarPreview] = useState(null);
    const [avatarDirty, setAvatarDirty] = useState(false);
    const { validate } = useClientValidation(profileSchema, data, setError);
    const initialRef = useRef(JSON.parse(JSON.stringify({ ...data, avatar: null })));

    // Dirty tracking: exclude avatar (can be a File object, not JSON-serializable)
    const { avatar: _avatar, ...restData } = data;
    const isDirty = avatarDirty || JSON.stringify(restData) !== JSON.stringify(initialRef.current);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(isDirty);

    function handleAvatarSelect(file) {
        setData('avatar', file);
        setAvatarPreview(URL.createObjectURL(file));
        setAvatarDirty(true);
    }

    function handleNotificationToggle(key, value) {
        setData('notifications_config', {
            ...data.notifications_config,
            [key]: value,
        });
    }

    function handleSubmit(e) {
        e.preventDefault();
        bypassNext();

        clearErrors();
        if (!validate()) return;

        patch(route('profile.update'), {
            preserveScroll: true,
            onSuccess: () => {
                initialRef.current = JSON.parse(JSON.stringify({ ...data, avatar: null }));
                setAvatarPreview(null);
                setAvatarDirty(false);
            },
        });
    }

    return (
        <AppLayout title="Profile">
            <Head title="Profile" />

            <div className="mb-8 flex items-start justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">
                        Profile
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Manage your account settings and preferences.
                    </p>
                </div>

                <PrimaryButton
                    onClick={handleSubmit}
                    disabled={!isDirty || processing}
                >
                    {processing ? 'Saving...' : 'Save Changes'}
                </PrimaryButton>
            </div>

            <DashboardBanner
                onSkip={() => { bypassNext(); router.post(route('onboarding.skip-profile'), {}, { preserveState: true, preserveScroll: true }); }}
                onDismiss={() => { bypassNext(); router.post(route('onboarding.skip-profile'), {}, { preserveState: true, preserveScroll: true }); }}
            />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Profile Photo */}
                <div className="bg-white p-6 shadow-sm border border-slate-200 rounded-lg">
                    <ProfileHeader onAvatarSelect={handleAvatarSelect} avatarPreview={avatarPreview} saving={processing} />
                </div>

                {/* Agency Info */}
                <AgencyInfoCard agency={defaultAgency} />

                {/* Personal Information & Emergency Contact */}
                <PersonalInfoForm data={data} setData={setData} errors={errors} />

                {/* Notification Preferences */}
                <NotificationPreferencesSection prefs={data.notifications_config} onToggle={handleNotificationToggle} />

                {/* Email Change */}
                <ChangeEmailForm
                    initialStep={email_change_step || 'start'}
                    hint={email_change_hint || ''}
                    debugOtp={email_change_debug_otp}
                />

                {/* Save Button */}
                <div className="flex justify-end">
                    <PrimaryButton onClick={handleSubmit} disabled={!isDirty || processing}>
                        {processing ? 'Saving...' : 'Save Changes'}
                    </PrimaryButton>
                </div>

                {/* Update Password */}
                <UpdatePasswordForm onBypass={bypassNext} />

                {/* Two-Factor Authentication */}
                <MfaSetup mfaEnabled={mfaEnabled} />

                {/* Delete Account */}
                <DeleteUserForm onBypass={bypassNext} />
            </div>

            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
