import { useState, useRef, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { useToast } from '@/Hooks/useToast';
import { formatDisplayDateTime } from '@/lib/utils';


export default function SystemSettings({ 
    referral_overdue_days,
    chatbot_last_reindexed_at,
}) {
    const [overdueDays, setOverdueDays] = useState(referral_overdue_days);
    const [reindexing, setReindexing] = useState(false);
    const [lastReindexedAt, setLastReindexedAt] = useState(chatbot_last_reindexed_at);
    const toast = useToast();

    const initialRef = useRef({ 
        overdueDays: referral_overdue_days,
    });
    
    const hasDirty = useMemo(() => (
        overdueDays !== initialRef.current.overdueDays
    ), [overdueDays]);
    const { UnsavedModal, bypassNext } = useUnsavedChanges(hasDirty);

    const saveOverdueDays = () => {
        bypassNext();
        router.post(route('admin.system-settings.update'), {
            referral_overdue_days: overdueDays,
        }, {
            preserveScroll: true,
        });
    };

    const handleReindex = async () => {
        setReindexing(true);
        try {
            const res = await fetch(route('admin.system-settings.reindex-chatbot'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (data.success) {
                setLastReindexedAt(data.last_reindexed_at);
                toast.success(data.message);
            } else {
                toast.error(data.message || 'Refresh failed.');
            }
        } catch {
            toast.error('Failed to reach the server. Please try again.');
        } finally {
            setReindexing(false);
        }
    };

    return (
        <AppLayout title="System Settings">
            <Head title="System Settings" />
            <div data-tour="settings-header" className="mb-8">
                <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">System Settings</h1>
                <p className="text-sm text-slate-400 font-body mt-0.5">Manage system-wide configuration and preferences.</p>
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
                    <h3 className="text-base font-semibold text-slate-900 mb-4">Chatbot Knowledge</h3>
                    <p className="text-sm text-slate-600 mb-4">
                        Refresh the helpdesk articles used by the chatbot. Public agency information is read directly from the directory.
                    </p>
                    {lastReindexedAt && (
                        <p className="text-xs text-slate-400 mb-4">
                            Last updated: {formatDisplayDateTime(lastReindexedAt)}
                        </p>
                    )}
                    <button
                        onClick={handleReindex}
                        disabled={reindexing}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {reindexing ? (
                            <>
                                <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                Refreshing…
                            </>
                        ) : (
                            'Update Knowledge'
                        )}
                    </button>
                </div>
            </div>
            {UnsavedModal}
        </AppLayout>
    );
}
