import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { route } from 'ziggy-js';
import { useOnboarding } from '@/Onboarding/OnboardingProvider';
import { getChecklist } from '@/Onboarding/checklist';
import { dismissChecklist as persistDismiss } from '@/Onboarding/api';

/**
 * Getting-started checklist card shown on the role dashboards.
 * Hidden once dismissed or when every item is complete (after showing a
 * one-time completion state). Item completion is tracked in
 * users.checklist_progress — action items are marked server-side by the
 * domain action, visit items by useChecklistVisitTracking.
 */
export default function GettingStartedChecklist() {
    const { auth } = usePage().props;
    const { checklistProgress, dismissChecklist } = useOnboarding();

    const items = getChecklist(auth.user?.role);
    const doneCount = items.filter((item) => checklistProgress.items[item.id]).length;
    const allDone = items.length > 0 && doneCount === items.length;
    const progressPct = items.length > 0 ? Math.round((doneCount / items.length) * 100) : 0;

    // When everything is complete, show the celebration once and quietly
    // persist a dismissal so the card retires on the next dashboard visit.
    // We call the API directly (not the context method) so the card stays
    // visible for the rest of this view.
    const retiredRef = useRef(false);
    useEffect(() => {
        if (allDone && !checklistProgress.dismissed_at && !retiredRef.current) {
            retiredRef.current = true;
            persistDismiss().catch(() => {});
        }
    }, [allDone, checklistProgress.dismissed_at]);

    if (items.length === 0) return null;
    if (checklistProgress.dismissed_at) return null;

    return (
        <section
            data-tour="getting-started-checklist"
            className="mb-6 overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/70 to-white shadow-sm"
        >
            <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-indigo-400">
                        Getting started
                    </p>
                    <h2 className="mt-0.5 text-[15px] font-extrabold text-slate-900">
                        {allDone ? 'You’re all set! 🎉' : 'Make yourself at home'}
                    </h2>
                    <p className="mt-0.5 text-[12px] text-slate-500">
                        {allDone
                            ? 'You’ve completed every first step. This card will retire itself now.'
                            : `${doneCount} of ${items.length} first steps done — each one ticks off automatically.`}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    <div className="h-1.5 w-28 overflow-hidden rounded-full bg-indigo-100">
                        <div
                            className="h-full rounded-full bg-indigo-600 transition-all duration-500"
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>
                    <button
                        type="button"
                        onClick={dismissChecklist}
                        className="text-[11px] font-bold text-slate-400 transition-colors hover:text-slate-600"
                        title={allDone ? 'Dismiss' : 'Dismiss checklist'}
                    >
                        {allDone ? 'Done' : 'Dismiss'}
                    </button>
                </div>
            </div>

            {!allDone && (
                <ul className="grid grid-cols-1 gap-2 border-t border-indigo-100/70 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((item) => {
                        const done = Boolean(checklistProgress.items[item.id]);
                        return (
                            <li key={item.id}>
                                <Link
                                    href={route(item.route)}
                                    className={`flex items-center gap-2.5 rounded-lg border px-3 py-2.5 transition-all ${
                                        done
                                            ? 'border-emerald-100 bg-emerald-50/60 text-emerald-700'
                                            : 'border-slate-200 bg-white text-slate-700 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-sm'
                                    }`}
                                >
                                    <span
                                        className={`material-symbols-outlined text-[18px] ${done ? 'text-emerald-600' : 'text-indigo-500'}`}
                                        style={{ fontVariationSettings: `'FILL' ${done ? 1 : 0}, 'wght' 500` }}
                                    >
                                        {done ? 'check_circle' : item.icon}
                                    </span>
                                    <span className={`text-[12px] font-bold leading-tight ${done ? 'line-through opacity-70' : ''}`}>
                                        {item.label}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
