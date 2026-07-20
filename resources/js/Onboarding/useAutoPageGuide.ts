import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useOnboardingOptional } from './OnboardingProvider';
import { getPageGuide } from './registry';

/**
 * Automatically launches a per‑page guide the first time a case manager
 * visits a registered route, once the page has its guide anchors.
 * Mounted once in AppLayout so every authenticated page participates.
 *
 * Launch conditions (all must be true):
 *   - user role is CASE_MANAGER
 *   - a guide is registered for the current canonical route
 *   - the guide hasn't been seen for this role+route (or legacy plain route)
 *   - no welcome tour or page guide currently owns the overlay
 *   - at least one guide anchor is present on the page
 *
 * The guide is marked seen optimistically when it opens.
 */
export default function useAutoPageGuide(): void {
    const { url, props } = usePage();
    const onboarding = useOnboardingOptional();
    const auth = (props as { auth?: { user?: { role?: string } } }).auth;
    const role = auth?.user?.role;
    const launchedRef = useRef<Set<string>>(new Set());

    useEffect(() => {
        if (!onboarding || role !== 'CASE_MANAGER') return;

        const { phase, seenGuides, startPageGuide } = onboarding;

        // Don't launch while a welcome tour or another guide is active.
        if (phase === 'touring' || phase === 'welcome') return;

        // Resolve the current canonical route name.
        let currentRoute: string | null = null;
        try {
            currentRoute = route().current() ?? null;
        } catch {
            return;
        }
        if (!currentRoute) return;

        // Check a guide is registered for this route.
        const guide = getPageGuide(currentRoute);
        if (!guide) return;

        // Check whether it has already been seen — role-qualified or plain legacy.
        const qualifiedKey = `${role}:${currentRoute}`;
        const isSeen = seenGuides.includes(qualifiedKey) || seenGuides.includes(currentRoute);
        if (isSeen) return;

        // Prevent repeated launch attempts within the same session.
        if (launchedRef.current.has(qualifiedKey)) return;

        // At least one anchor must be present on the page.
        const hasAnchors = guide.steps.some((step) => document.querySelector(step.element));
        if (!hasAnchors) {
            // Wait for anchors to appear (deferred content, SPA render, etc.).
            const observer = new MutationObserver(() => {
                const found = guide.steps.some((step) => document.querySelector(step.element));
                if (found) {
                    observer.disconnect();
                    launchedRef.current.add(qualifiedKey);
                    startPageGuide(currentRoute, qualifiedKey);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
            // Safety timeout so the observer doesn't leak.
            const timer = setTimeout(() => observer.disconnect(), 15_000);
            return () => {
                observer.disconnect();
                clearTimeout(timer);
            };
        }

        launchedRef.current.add(qualifiedKey);
        startPageGuide(currentRoute, qualifiedKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, role, onboarding?.phase, onboarding?.seenGuides]);
}
