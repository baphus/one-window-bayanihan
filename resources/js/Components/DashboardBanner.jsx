import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { X, UserCircle } from 'lucide-react';

export default function DashboardBanner({ onSkip, onDismiss }) {
    const { profile_incomplete } = usePage().props;
    const [dismissed, setDismissed] = useState(false);

    if (!profile_incomplete || dismissed) return null;

    function handleComplete() {
        router.get(route('profile.edit'));
    }

    function handleSkip() {
        if (onSkip) {
            onSkip();
            return;
        }
        router.post(route('onboarding.skip-profile'), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setDismissed(true),
        });
    }

    function handleDismiss() {
        setDismissed(true);
        if (onDismiss) {
            onDismiss();
            return;
        }
        router.post(route('onboarding.skip-profile'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <div className="mb-6 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-blue-50 p-4 shadow-sm">
            <div className="flex items-start gap-4">
                <div className="shrink-0 rounded-full bg-indigo-100 p-2">
                    <UserCircle className="h-6 w-6 text-indigo-600" />
                </div>
                <div className="flex-1 min-w-0">
                    <h3 className="text-sm font-extrabold text-slate-900">Complete your profile</h3>
                    <p className="mt-1 text-[13px] text-slate-600 leading-relaxed">
                        Add your position, department, and contact info so your colleagues can identify and reach you.
                    </p>
                    <div className="mt-3 flex items-center gap-3">
                        <button
                            onClick={handleComplete}
                            className="inline-flex items-center gap-1.5 rounded-[3px] bg-indigo-600 px-4 py-1.5 text-[12px] font-bold text-white hover:bg-indigo-700 transition-colors"
                        >
                            <UserCircle className="h-3.5 w-3.5" />
                            Complete Profile
                        </button>
                        <button
                            onClick={handleSkip}
                            className="rounded-[3px] border border-slate-300 bg-white px-4 py-1.5 text-[12px] font-bold text-slate-600 hover:bg-slate-50 transition-colors"
                        >
                            Skip
                        </button>
                    </div>
                </div>
                <button
                    onClick={handleDismiss}
                    className="shrink-0 rounded-full p-1 text-slate-400 hover:bg-indigo-100 hover:text-slate-600 transition-colors"
                    aria-label="Dismiss"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
