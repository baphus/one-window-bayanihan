import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function Edit({ mustVerifyEmail, status }) {
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

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                        onDirtyChange={onDirty1}
                        onBypass={bypassNext}
                    />
                </div>

                <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                    <UpdatePasswordForm className="max-w-xl" onDirtyChange={onDirty2} onBypass={bypassNext} />
                </div>

                <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                    <DeleteUserForm className="max-w-xl" onDirtyChange={onDirty3} onBypass={bypassNext} />
                </div>
            </div>
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
