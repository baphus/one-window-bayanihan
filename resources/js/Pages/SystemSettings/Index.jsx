import { useState, useRef, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function SystemSettings({ 
    debug_otp_enabled,
    debug_tracking_otp_enabled,
    referral_overdue_days,
}) {
    const [debugOtp, setDebugOtp] = useState(debug_otp_enabled);
    const [debugTrackingOtp, setDebugTrackingOtp] = useState(debug_tracking_otp_enabled);
    const [overdueDays, setOverdueDays] = useState(referral_overdue_days);

    const initialRef = useRef({ 
        debugOtp: debug_otp_enabled,
        debugTrackingOtp: debug_tracking_otp_enabled,
        overdueDays: referral_overdue_days,
    });
    
    const hasDirty = useMemo(() => (
        debugOtp !== initialRef.current.debugOtp
        || debugTrackingOtp !== initialRef.current.debugTrackingOtp
        || overdueDays !== initialRef.current.overdueDays
    ), [debugOtp, debugTrackingOtp, overdueDays]);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);

    const toggleDebugOtp = () => {
        const next = !debugOtp;
        setDebugOtp(next);
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            debug_otp_enabled: next,
            referral_overdue_days: overdueDays,
        }, {
            preserveScroll: true,
            onError: () => setDebugOtp(!next),
        });
    };

    const toggleDebugTrackingOtp = () => {
        const next = !debugTrackingOtp;
        setDebugTrackingOtp(next);
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            debug_tracking_otp_enabled: next,
            referral_overdue_days: overdueDays,
        }, {
            preserveScroll: true,
            onError: () => setDebugTrackingOtp(!next),
        });
    };

    const saveOverdueDays = () => {
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            debug_otp_enabled: debugOtp,
            debug_tracking_otp_enabled: debugTrackingOtp,
            referral_overdue_days: overdueDays,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="System Settings">
            <Head title="System Settings" />
            <div data-tour="settings-header" className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">System Settings</h1>
                <p className="text-sm text-slate-500 mt-1">Manage system-wide configuration and preferences.</p>
            </div>

            <div data-tour="settings-form" className="grid grid-cols-1 gap-6 max-w-2xl">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">Application Information</h3>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Application Name</dt>
                            <dd className="font-medium text-slate-900">One Window Bayanihan</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Version</dt>
                            <dd className="font-medium text-slate-900">1.0.0</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Region</dt>
                            <dd className="font-medium text-slate-900">Central Visayas (Region VII)</dd>
                        </div>
                    </dl>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">SERVQUAL Configuration</h3>
                    <p className="text-sm text-slate-600">
                        SERVQUAL (Service Quality) dimensions and parameters are used to measure client satisfaction across agencies.
                        Configuration management will be available in a future update.
                    </p>
                </div>

                <div data-tour="settings-overdue-threshold" className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">Referral Overdue Threshold</h3>
                    <p className="text-sm text-slate-600 mb-4">
                        Referrals are marked overdue when they exceed this number of days without being completed or rejected.
                    </p>
                    <div className="flex items-end gap-3">
                        <div className="flex-1">
                            <label className="block text-sm font-medium text-slate-700 mb-1">Overdue after (days)</label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                value={overdueDays}
                                onChange={(e) => setOverdueDays(parseInt(e.target.value) || 7)}
                                className="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>
                        <button
                            onClick={saveOverdueDays}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500"
                        >
                            Save
                        </button>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <h3 className="text-base font-semibold text-slate-900 mb-4">OTP Settings</h3>
                    <p className="text-sm text-slate-600">
                        One-Time Password settings for login and tracking systems.
                    </p>
                </div>

                <div data-tour="settings-otp-debug" className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">Login OTP Debug Mode</h3>
                            <p className="text-sm text-slate-500 mt-1">
                                When enabled, login OTP values will be auto-filled on the login verification screen for testing purposes.
                            </p>
                            <p className="text-xs text-amber-600 font-medium mt-2">
                                Exposes OTP values in login page responses. Disable in production.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={debugOtp}
                            onClick={toggleDebugOtp}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${debugOtp ? 'bg-amber-500' : 'bg-slate-300'}`}
                        >
                            <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${debugOtp ? 'translate-x-5' : 'translate-x-0'}`} />
                        </button>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">Tracking OTP Debug Mode</h3>
                            <p className="text-sm text-slate-500 mt-1">
                                When enabled, tracking OTP values will be auto-filled on the case tracking verification screen for testing purposes.
                            </p>
                            <p className="text-xs text-amber-600 font-medium mt-2">
                                Exposes OTP values in tracking page responses. Disable in production.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={debugTrackingOtp}
                            onClick={toggleDebugTrackingOtp}
                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${debugTrackingOtp ? 'bg-amber-500' : 'bg-slate-300'}`}
                        >
                            <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition ${debugTrackingOtp ? 'translate-x-5' : 'translate-x-0'}`} />
                        </button>
                    </div>
                </div>
            </div>
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
