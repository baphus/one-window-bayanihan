import { useMemo } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { z } from 'zod';
import OfwLayout from '@/Layouts/OfwLayout';
import PasswordInput from '@/Components/PasswordInput';
import createPasswordSchema from '@/utils/createPasswordSchema';
import useClientValidation from '@/Hooks/useClientValidation';

export default function Profile({ user }) {
    const { passwordRules } = usePage().props;

    const { data, setData, put, processing, errors, reset, recentlySuccessful, setError, clearErrors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
        contact_number: user?.contact_number ?? '',
    });

    // OFW password is optional — if left blank, server skips the update.
    const ofwPasswordSchema = useMemo(() => z.object({
        current_password: z.string().min(1, 'Current password is required.'),
        password: createPasswordSchema(passwordRules).or(z.literal('')),
        password_confirmation: z.string(),
    }).refine((data) => {
        // Only validate confirmation match when a new password is provided
        if (!data.password) return true;
        return data.password === data.password_confirmation;
    }, {
        message: 'Passwords do not match.',
        path: ['password_confirmation'],
    }), [passwordRules]);

    const { validate } = useClientValidation(ofwPasswordSchema, data, setError);

    function handleSubmit(e) {
        e.preventDefault();
        clearErrors();
        if (!validate()) return;

        put(route('ofw.profile.update'), {
            preserveScroll: true,
            onSuccess: () => reset('current_password', 'password', 'password_confirmation'),
        });
    }

    return (
        <OfwLayout title="Profile">
            {/* Back to My Cases */}
            <Link
                href={route('ofw.dashboard')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to My Cases
            </Link>

            <div className="mt-4">
                <h1 className="text-xl font-extrabold font-headline tracking-tight text-slate-900">Profile Settings</h1>
                <p className="mt-1 text-sm text-slate-400 font-body">
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
                    <label htmlFor="contact_number" className="block text-sm font-medium text-slate-700">
                        Contact Number
                    </label>
                    <input
                        id="contact_number"
                        type="tel"
                        value={data.contact_number}
                        onChange={(e) => setData('contact_number', e.target.value)}
                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g. 09171234567"
                    />
                    {errors.contact_number && (
                        <p className="mt-1 text-sm text-red-600">{errors.contact_number}</p>
                    )}
                </div>

                {/* Divider */}
                <div className="border-t border-slate-200 pt-6">
                    <h2 className="text-sm font-bold text-slate-900">Change Password</h2>
                    <p className="mt-1 text-xs text-slate-500">Leave blank if you do not want to change your password.</p>
                </div>

                {/* Current password */}
                <PasswordInput
                    id="ofw-current-password"
                    label="Current Password"
                    value={data.current_password}
                    onChange={(v) => setData('current_password', v)}
                    error={errors.current_password}
                    autoComplete="current-password"
                />

                {/* New password */}
                <PasswordInput
                    id="ofw-new-password"
                    label="New Password"
                    value={data.password}
                    onChange={(v) => setData('password', v)}
                    error={errors.password}
                    autoComplete="new-password"
                    showStrengthMeter
                    rules={passwordRules}
                    confirmation={data.password_confirmation}
                />

                {/* Confirm new password */}
                <PasswordInput
                    id="ofw-confirm-password"
                    label="Confirm New Password"
                    value={data.password_confirmation}
                    onChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                    autoComplete="new-password"
                />

                {/* Submit */}
                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-60"
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
