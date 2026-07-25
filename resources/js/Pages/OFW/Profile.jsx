import { useForm, usePage } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';

export default function Profile({ user }) {
    const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
        contact_number: user?.contact_number ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(route('ofw.profile.update'), {
            preserveScroll: true,
            onSuccess: () => reset('current_password', 'password', 'password_confirmation'),
        });
    }

    return (
        <OfwLayout title="Profile">
            <div>
                <h1 className="text-xl font-bold text-gray-900">Profile Settings</h1>
                <p className="mt-1 text-sm text-gray-500">
                    Update your password and contact information
                </p>
            </div>

            <form onSubmit={handleSubmit} className="mt-6 max-w-lg space-y-6">
                {/* Success message */}
                {recentlySuccessful && (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        <span className="material-symbols-outlined text-[18px]">check_circle</span>
                        Profile updated successfully.
                    </div>
                )}

                {/* Contact number */}
                <div>
                    <label htmlFor="contact_number" className="block text-sm font-medium text-gray-700">
                        Contact Number
                    </label>
                    <input
                        id="contact_number"
                        type="tel"
                        value={data.contact_number}
                        onChange={(e) => setData('contact_number', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. 09171234567"
                    />
                    {errors.contact_number && (
                        <p className="mt-1 text-sm text-red-600">{errors.contact_number}</p>
                    )}
                </div>

                {/* Divider */}
                <div className="border-t border-gray-200 pt-6">
                    <h2 className="text-sm font-bold text-gray-900">Change Password</h2>
                    <p className="mt-1 text-xs text-gray-500">Leave blank if you don't want to change your password.</p>
                </div>

                {/* Current password */}
                <div>
                    <label htmlFor="current_password" className="block text-sm font-medium text-gray-700">
                        Current Password
                    </label>
                    <input
                        id="current_password"
                        type="password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        autoComplete="current-password"
                    />
                    {errors.current_password && (
                        <p className="mt-1 text-sm text-red-600">{errors.current_password}</p>
                    )}
                </div>

                {/* New password */}
                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                        New Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        autoComplete="new-password"
                    />
                    {errors.password && (
                        <p className="mt-1 text-sm text-red-600">{errors.password}</p>
                    )}
                </div>

                {/* Confirm password */}
                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">
                        Confirm New Password
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>
                    )}
                </div>

                {/* Submit */}
                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {processing && (
                            <span className="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                        )}
                        Save Changes
                    </button>
                </div>
            </form>
        </OfwLayout>
    );
}
