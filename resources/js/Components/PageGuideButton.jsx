import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { route } from 'ziggy-js';
import { useOnboarding } from '@/Onboarding/OnboardingProvider';
import { getPageGuide } from '@/Onboarding/registry';

/**
 * The [?] page-guide launcher, rendered once in the AppLayout header.
 * Visible only when the current route has a registered guide. Pulses on
 * first visit (guide not yet seen) — never auto-opens.
 */
export default function PageGuideButton() {
    const { url } = usePage();
    const { startPageGuide, seenGuides, phase, activePageGuide } = useOnboarding();

    // Resolve the current Ziggy route name; url is a dependency so the
    // lookup re-runs on every Inertia navigation.
    const currentRoute = useMemo(() => {
        try {
            return route().current() ?? null;
        } catch {
            return null;
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url]);

    const guide = currentRoute ? getPageGuide(currentRoute) : null;
    if (!guide) return null;

    // Keep the button visible during the welcome tour so its intro step can
    // highlight it, but don't launch a guide on top of an active overlay.
    const overlayActive = phase === 'touring' || activePageGuide !== null;
    const unseen = !seenGuides.includes(currentRoute) && !overlayActive;

    return (
        <button
            type="button"
            data-tour="page-guide-button"
            onClick={() => { if (!overlayActive) startPageGuide(currentRoute); }}
            title={`Page guide: ${guide.title}`}
            aria-label={`Open the ${guide.title} page guide`}
            className={`flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition-colors hover:border-indigo-300 hover:text-indigo-600 ${unseen ? 'owb-guide-nudge text-indigo-600 border-indigo-300' : ''}`}
        >
            <span
                className="material-symbols-outlined text-[18px]"
                style={{ fontVariationSettings: "'FILL' 0, 'wght' 500" }}
            >
                question_mark
            </span>
        </button>
    );
}
