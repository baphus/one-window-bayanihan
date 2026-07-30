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
import { useToast } from '@/Hooks/useToast';

import { profileSchema } from '@/Schemas/profileSchemas';
import useClientValidation from '@/Hooks/useClientValidation';
import DashboardBanner from '@/Components/DashboardBanner';
import ChangeEmailForm from '@/Pages/Profile/Partials/ChangeEmailForm';
import CropImageModal from '@/Components/CropImageModal';

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
        notifications_config: { ...notificationPrefs },
    });

    const toast = useToast();
    const [avatarPreview, setAvatarPreview] = useState(null);
    const [pendingAvatarFile, setPendingAvatarFile] = useState(null);
    const [avatarUploading, setAvatarUploading] = useState(false);
    const [cropFile, setCropFile] = useState(null);
    const { validate } = useClientValidation(profileSchema, data, setError);

    // Dirty tracking: avatar is uploaded separately, so only track text fields
    const initialRef = useRef(JSON.parse(JSON.stringify(data)));
    const isDirty = JSON.stringify(data) !== JSON.stringify(initialRef.current);
    const { UnsavedModal, bypassNext } = useUnsavedChanges(isDirty);

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
        formData.append('_method', 'patch');
        formData.append('avatar', pendingAvatarFile);
        // Include current form values so the backend validation passes
        formData.append('name', data.name);
        formData.append('email', data.email);
        formData.append('position', data.position ?? '');
        formData.append('department', data.department ?? '');
        formData.append('office_location', data.office_location ?? '');
        formData.append('bio', data.bio ?? '');
        formData.append('timezone', data.timezone ?? '');
        formData.append('contact_number', data.contact_number ?? '');

        router.post(route('profile.update'), formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success('Profile picture updated successfully.');
                setPendingAvatarFile(null);
                if (avatarPreview) URL.revokeObjectURL(avatarPreview);
                setAvatarPreview(null);
            },
            onFinish: () => setAvatarUploading(false),
            onError: (errors) => {
                if (errors.avatar) {
                    setError('avatar', errors.avatar);
                }
                setAvatarUploading(false);
            },
        });
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

        const submitOptions = {
            preserveScroll: true,
            onSuccess: () => {
                initialRef.current = JSON.parse(JSON.stringify(data));
                toast.success('Profile updated successfully.');
            },
        };

        patch(route('profile.update'), submitOptions);
    }

    return (
        <AppLayout title="Profile">
            <Head title="Profile" />

            <header className="mb-8">
                <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
                    Profile
                </h1>
                <p className="text-sm text-slate-400 font-body mt-0.5">
                    Manage your account settings and preferences.
                </p>
            </header>

            <DashboardBanner
                onSkip={() => { bypassNext(); router.post(route('onboarding.skip-profile'), {}, { preserveState: true, preserveScroll: true }); }}
                onDismiss={() => { bypassNext(); router.post(route('onboarding.skip-profile'), {}, { preserveState: true, preserveScroll: true }); }}
            />

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Profile Photo */}
                <div className="bg-white p-6 shadow-sm border border-slate-200 rounded-lg">
                    <ProfileHeader onAvatarSelect={handleAvatarSelect} avatarPreview={avatarPreview} saving={processing || avatarUploading} />
                    {errors.avatar && (
                        <p className="mt-3 text-sm text-red-600 flex items-center gap-1.5">
                            <svg className="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
                            </svg>
                            {errors.avatar}
                        </p>
                    )}
                    {pendingAvatarFile && (
                        <div className="mt-4 flex items-center gap-3">
                            <button
                                type="button"
                                onClick={handleConfirmAvatar}
                                disabled={avatarUploading}
                                className="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition-colors"
                            >
                                {avatarUploading ? (
                                    <>
                                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Saving...
                                    </>
                                ) : (
                                    'Confirm Profile Picture'
                                )}
                            </button>
                            <button
                                type="button"
                                onClick={handleCancelPendingAvatar}
                                disabled={avatarUploading}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    )}
                </div>

                {/* Agency Info */}
                <AgencyInfoCard agency={defaultAgency} />

                {/* Personal Information & Emergency Contact */}
                <PersonalInfoForm data={data} setData={setData} errors={errors} />

                {/* Save Button */}
                <div className="flex justify-end">
                    <PrimaryButton onClick={handleSubmit} disabled={!isDirty || processing}>
                        {processing ? 'Saving...' : 'Save Changes'}
                    </PrimaryButton>
                </div>

                {/* Notification Preferences */}
                <NotificationPreferencesSection prefs={data.notifications_config} onToggle={handleNotificationToggle} />

                {/* Email Change */}
                <ChangeEmailForm
                    initialStep={email_change_step || 'start'}
                    hint={email_change_hint || ''}
                    debugOtp={email_change_debug_otp}
                />

                {/* Update Password */}
                <UpdatePasswordForm onBypass={bypassNext} />

                {/* Two-Factor Authentication */}
                <MfaSetup mfaEnabled={mfaEnabled} />

                {/* Delete Account */}
                <DeleteUserForm onBypass={bypassNext} />
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
