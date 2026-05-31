import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import ProfileHeader from '@/Components/ProfileHeader';
import PersonalInfoForm from '@/Components/PersonalInfoForm';
import AgencyInfoCard from '@/Components/AgencyInfoCard';
import MfaSetup from '@/Components/MfaSetup';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function Edit({ mustVerifyEmail, status, mfaEnabled, defaultAgency }) {
    const [dirty1, setDirty1] = useState(false);
    const [dirty2, setDirty2] = useState(false);
    const [dirty3, setDirty3] = useState(false);
    const isDirty = dirty1 || dirty2 || dirty3;
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(isDirty);

    const onDirty1 = useCallback((d) => setDirty1(d), []);
    const onDirty2 = useCallback((d) => setDirty2(d), []);
    const onDirty3 = useCallback((d) => setDirty3(d), []);

    return (
        <AppLayout title="Profile">
            <Head title="Profile" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Profile</h1>
                <p className="text-sm text-slate-500 mt-1">Manage your account settings and preferences.</p>
            </div>

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Full-width header card */}
                <div className="bg-white p-6 shadow-sm border border-slate-200 rounded-lg">
                    <ProfileHeader />
                </div>

                {/* Two-column grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Left column — main forms */}
                    <div className="md:col-span-2 space-y-6">
                        <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                            <PersonalInfoForm onDirtyChange={onDirty1} onBypass={bypassNext} />
                        </div>

                        <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                            <UpdatePasswordForm className="max-w-xl" onDirtyChange={onDirty2} onBypass={bypassNext} />
                        </div>
                    </div>

                    {/* Right column — sidebar cards */}
                    <div className="space-y-6 md:sticky md:top-6 md:self-start">
                        <AgencyInfoCard />
                        <MfaSetup mfaEnabled={mfaEnabled} />
                    </div>
                </div>

                {/* Full-width danger zone */}
                <div className="bg-white p-4 shadow-sm border border-red-200 rounded-lg sm:p-8">
                    <div className="max-w-xl">
                        <DeleteUserForm className="max-w-xl" onDirtyChange={onDirty3} onBypass={bypassNext} />
                    </div>
                </div>
            </div>

            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
