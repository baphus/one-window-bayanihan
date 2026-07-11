import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useOnboardingOptional } from './OnboardingProvider';
import { getChecklist } from './checklist';

interface AuthProps {
    auth?: { user?: { role?: string } | null };
}

/**
 * Marks visit-type checklist items complete when the user loads their
 * target route. Mounted once in AppLayout so every authenticated page
 * participates. Progress keeps accruing even after dismissal — the card
 * simply stays hidden.
 */
export default function useChecklistVisitTracking(): void {
    const { url, props } = usePage();
    const role = (props as AuthProps).auth?.user?.role;
    // Optional so layouts rendered without the app shell (tests) no-op.
    const onboarding = useOnboardingOptional();
    const checklistProgress = onboarding?.checklistProgress;
    const markChecklistItem = onboarding?.markChecklistItem;

    useEffect(() => {
        if (!checklistProgress || !markChecklistItem) return;

        let current: string | null = null;
        try {
            current = route().current() ?? null;
        } catch {
            return;
        }
        if (!current) return;

        for (const item of getChecklist(role)) {
            if (item.marking === 'visit' && item.route === current && !checklistProgress.items[item.id]) {
                markChecklistItem(item.id);
            }
        }
        // checklistProgress is intentionally read fresh each navigation; url
        // is the navigation signal.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, role]);
}
