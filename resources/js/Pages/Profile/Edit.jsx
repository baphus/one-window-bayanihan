import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
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
                    />
                </div>

                <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div className="bg-white p-4 shadow-sm border border-slate-200 rounded-lg sm:p-8">
                    <DeleteUserForm className="max-w-xl" />
                </div>
            </div>
        </AppLayout>
    );
}
